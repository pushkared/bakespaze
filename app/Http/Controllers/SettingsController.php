<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\LeaveCategory;
use App\Models\LeaveHoliday;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = AppSetting::current();
        $timezones = \DateTimeZone::listIdentifiers();
        $breakOptions = [30, 60, 90, 120];
        $user = auth()->user();
        $isAdmin = $user && in_array($user->role, ['admin', 'super_admin'], true);
        $leaveCategories = collect();
        $leaveHolidays = collect();
        if ($isAdmin) {
            $leaveCategories = $this->ensureDefaultLeaveCategories();
            $leaveHolidays = LeaveHoliday::orderByDesc('created_at')->get();
        }

        return view('settings.index', [
            'settings' => $settings,
            'timezones' => $timezones,
            'breakOptions' => $breakOptions,
            'isAdmin' => $isAdmin,
            'leaveCategories' => $leaveCategories,
            'leaveHolidays' => $leaveHolidays,
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'punch_in_start' => ['required', 'date_format:H:i'],
            'punch_in_end' => ['required', 'date_format:H:i', 'after:punch_in_start'],
            'break_duration_minutes' => ['required', 'integer', 'in:30,60,90,120'],
            'timezone' => ['required', 'string'],
        ]);

        $settings = AppSetting::current();
        $settings->update([
            'punch_in_start' => $data['punch_in_start'] . ':00',
            'punch_in_end' => $data['punch_in_end'] . ':00',
            'break_duration_minutes' => $data['break_duration_minutes'],
            'timezone' => $data['timezone'],
        ]);

        return back()->with('status', 'Settings updated.');
    }

    protected function ensureDefaultLeaveCategories()
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
}
