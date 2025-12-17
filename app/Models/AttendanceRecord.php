<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class AttendanceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'workspace_id',
        'work_date',
        'clock_in',
        'clock_out',
        'lunch_start',
        'lunch_end',
        'minutes_worked',
    ];

    protected $casts = [
        'clock_in' => 'datetime',
        'clock_out' => 'datetime',
        'lunch_start' => 'datetime',
        'lunch_end' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function computeMinutes(): int
    {
        $clockIn = $this->clock_in ? \Carbon\Carbon::parse($this->clock_in) : null;
        $clockOut = $this->clock_out ? \Carbon\Carbon::parse($this->clock_out) : null;
        if (!$clockIn || !$clockOut) {
            return 0;
        }
        $minutes = $clockIn->diffInMinutes($clockOut);
        $lunchStart = $this->lunch_start ? \Carbon\Carbon::parse($this->lunch_start) : null;
        $lunchEnd = $this->lunch_end ? \Carbon\Carbon::parse($this->lunch_end) : null;
        if ($lunchStart && $lunchEnd) {
            $minutes -= $lunchStart->diffInMinutes($lunchEnd);
        }
        return max(0, $minutes);
    }
}
