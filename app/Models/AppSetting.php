<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class AppSetting extends Model
{
    protected $fillable = [
        'punch_in_start',
        'punch_in_end',
        'break_duration_minutes',
        'timezone',
    ];

    public static function defaults(): array
    {
        return [
            'punch_in_start' => '09:00:00',
            'punch_in_end' => '11:00:00',
            'break_duration_minutes' => 30,
            'timezone' => 'Asia/Kolkata',
        ];
    }

    public static function current(): self
    {
        if (!Schema::hasTable('app_settings')) {
            return new self(self::defaults());
        }

        $setting = self::query()->first();
        if ($setting) {
            return $setting;
        }

        return self::query()->create(self::defaults());
    }
}
