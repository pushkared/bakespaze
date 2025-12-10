<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'plan',
        'created_by',
    ];

    public function workspaces()
    {
        return $this->hasMany(Workspace::class);
    }
}
