<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
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
}
