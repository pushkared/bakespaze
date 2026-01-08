<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'yearly_allowance',
        'active',
    ];

    public function requests()
    {
        return $this->hasMany(LeaveRequest::class, 'category_id');
    }
}
