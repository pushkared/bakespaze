<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\TaskActivity;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'creator_id',
        'title',
        'description',
        'due_date',
        'status',
        'accepted_at',
        'accepted_by_user_id',
    ];

    protected $casts = [
        'due_date' => 'date',
        'accepted_at' => 'datetime',
    ];

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function comments()
    {
        return $this->hasMany(TaskComment::class);
    }

    public function attachments()
    {
        return $this->hasMany(TaskAttachment::class);
    }

    public function assignees()
    {
        return $this->belongsToMany(User::class, 'task_user');
    }

    public function activities()
    {
        return $this->hasMany(TaskActivity::class)->latest();
    }
}
