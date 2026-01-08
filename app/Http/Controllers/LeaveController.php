<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\LeaveCategory;
use App\Models\LeaveHoliday;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Notifications\LeaveRequestNotification;
use App\Notifications\LeaveStatusNotification;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class LeaveController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $isAdmin = $this->isAdmin($user);

        $categories = $this->ensureDefaultCategories();
        $holidays = LeaveHoliday::orderBy('date')->get();

        $requests = LeaveRequest::with(['category', 'approver'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $pending = collect();
        if ($isAdmin) {
            $pending = LeaveRequest::with(['category', 'user'])
                ->where('status', 'pending')
                ->orderBy('created_at', 'asc')
                ->get();
        }

        $year = Carbon::now($this->timezone())->year;
        $balances = $this->calculateBalances($user->id, $categories, $year);

        return view('leaves.index', [
            'isAdmin' => $isAdmin,
            'categories' => $categories,
            'holidays' => $holidays,
            'requests' => $requests,
            'pending' => $pending,
            'balances' => $balances,
            'year' => $year,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'category_id' => ['required', 'exists:leave_categories,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);
        [$totalDays, $excluded] = $this->countLeaveDays($start, $end);
        if ($totalDays <= 0) {
            return back()->withErrors('Selected dates fall on weekends/holidays.');
        }

        $requestModel = LeaveRequest::create([
            'user_id' => $user->id,
            'category_id' => $data['category_id'],
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'total_days' => $totalDays,
            'excluded_days' => $excluded,
            'status' => 'pending',
            'reason' => $data['reason'] ?? null,
        ]);

        $admins = User::whereIn('role', ['admin', 'super_admin'])->get();
        if ($admins->isNotEmpty()) {
            Notification::send($admins, new LeaveRequestNotification($requestModel));
        }

        return back()->with('status', 'Leave request submitted.');
    }

    public function approve(Request $request, LeaveRequest $leave)
    {
        $this->authorizeAdmin($request->user());

        $leave->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);

        $leave->user?->notify(new LeaveStatusNotification($leave));

        return back()->with('status', 'Leave approved.');
    }

    public function reject(Request $request, LeaveRequest $leave)
    {
        $this->authorizeAdmin($request->user());

        $data = $request->validate([
            'rejection_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $leave->update([
            'status' => 'rejected',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'rejection_reason' => $data['rejection_reason'] ?? null,
        ]);

        $leave->user?->notify(new LeaveStatusNotification($leave));

        return back()->with('status', 'Leave rejected.');
    }

    public function updateCategories(Request $request)
    {
        $this->authorizeAdmin($request->user());

        $data = $request->validate([
            'categories' => ['required', 'array'],
            'categories.*.id' => ['required', 'exists:leave_categories,id'],
            'categories.*.yearly_allowance' => ['required', 'integer', 'min:0', 'max:365'],
        ]);

        foreach ($data['categories'] as $cat) {
            LeaveCategory::where('id', $cat['id'])->update([
                'yearly_allowance' => $cat['yearly_allowance'],
            ]);
        }

        return back()->with('status', 'Leave categories updated.');
    }

    public function storeHoliday(Request $request)
    {
        $this->authorizeAdmin($request->user());

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'date' => ['required', 'date', 'unique:leave_holidays,date'],
        ]);

        LeaveHoliday::create($data);

        return back()->with('status', 'Holiday added.');
    }

    public function deleteHoliday(Request $request, LeaveHoliday $holiday)
    {
        $this->authorizeAdmin($request->user());
        $holiday->delete();
        return back()->with('status', 'Holiday removed.');
    }

    protected function ensureDefaultCategories()
    {
        $defaults = [
            'casual' => ['name' => 'Casual', 'yearly_allowance' => 12],
            'sick' => ['name' => 'Sick', 'yearly_allowance' => 8],
            'personal' => ['name' => 'Personal', 'yearly_allowance' => 6],
        ];

        foreach ($defaults as $code => $data) {
            LeaveCategory::firstOrCreate(
                ['code' => $code],
                ['name' => $data['name'], 'yearly_allowance' => $data['yearly_allowance'], 'active' => true]
            );
        }

        return LeaveCategory::orderBy('name')->get();
    }

    protected function calculateBalances(int $userId, $categories, int $year): array
    {
        $balances = [];
        foreach ($categories as $category) {
            $used = LeaveRequest::where('user_id', $userId)
                ->where('category_id', $category->id)
                ->where('status', 'approved')
                ->whereYear('start_date', $year)
                ->sum('total_days');

            $balances[$category->id] = [
                'allowance' => $category->yearly_allowance,
                'used' => (int) $used,
                'remaining' => max(0, $category->yearly_allowance - (int) $used),
            ];
        }

        return $balances;
    }

    protected function countLeaveDays(Carbon $start, Carbon $end): array
    {
        $holidays = LeaveHoliday::pluck('date')->map(fn($d) => Carbon::parse($d)->toDateString())->all();
        $period = CarbonPeriod::create($start, $end);
        $total = 0;
        $excluded = 0;

        foreach ($period as $date) {
            $isWeekend = $date->isWeekend();
            $isHoliday = in_array($date->toDateString(), $holidays, true);
            if ($isWeekend || $isHoliday) {
                $excluded++;
                continue;
            }
            $total++;
        }

        return [$total, $excluded];
    }

    protected function timezone(): string
    {
        return AppSetting::current()->timezone ?? 'Asia/Kolkata';
    }

    protected function isAdmin(User $user): bool
    {
        return in_array($user->role, ['admin', 'super_admin'], true);
    }

    protected function authorizeAdmin(User $user): void
    {
        abort_unless($this->isAdmin($user), 403);
    }
}
