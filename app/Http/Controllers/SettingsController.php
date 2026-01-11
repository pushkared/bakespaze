<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\LeaveCategory;
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
        return view('settings.index', [
            'settings' => $settings,
            'timezones' => $timezones,
            'breakOptions' => $breakOptions,
            'isAdmin' => $isAdmin,
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'punch_in_start' => ['required', 'date_format:H:i'],
            'punch_in_end' => ['required', 'date_format:H:i', 'after:punch_in_start'],
            'punch_out_after_hours' => ['required', 'integer', 'min:1', 'max:24'],
            'auto_punch_out_time' => ['required', 'date_format:H:i'],
            'break_duration_minutes' => ['required', 'integer', 'in:30,60,90,120'],
            'timezone' => ['required', 'string'],
        ]);

        $settings = AppSetting::current();
        $settings->update([
            'punch_in_start' => $data['punch_in_start'] . ':00',
            'punch_in_end' => $data['punch_in_end'] . ':00',
            'punch_out_after_hours' => $data['punch_out_after_hours'],
            'auto_punch_out_time' => $data['auto_punch_out_time'] . ':00',
            'break_duration_minutes' => $data['break_duration_minutes'],
            'timezone' => $data['timezone'],
        ]);

        return back()->with('status', 'Settings updated.');
    }

    public function updateNotifications(Request $request)
    {
        $data = $request->validate([
            'notifications_enabled' => ['nullable', 'in:on,1'],
        ]);

        $user = $request->user();
        $user->notifications_enabled = isset($data['notifications_enabled']);
        $user->save();

        return back()->with('status', 'Notification preference updated.');
    }

    public function updateTimezone(Request $request)
    {
        $data = $request->validate([
            'timezone' => ['required', 'string'],
        ]);

        $settings = AppSetting::current();
        $settings->update([
            'timezone' => $data['timezone'],
        ]);

        return back()->with('status', 'Timezone updated.');
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
