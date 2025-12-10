<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'name',
        'description',
    ];

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function memberships()
    {
        return $this->hasMany(Membership::class);
    }
}
