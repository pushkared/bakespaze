<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'avatar_url',
        'type',
        'created_by',
    ];

    public function participants()
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role', 'joined_at', 'last_read_message_id', 'last_delivered_message_id'])
            ->withTimestamps();
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
