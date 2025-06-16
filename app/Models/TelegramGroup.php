<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TelegramGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'chat_id',
        'title',
        'type',
        'is_active',
        'description',
        'invite_link'
    ];

    protected $casts = [
        'chat_id' => 'string',
        'is_active' => 'boolean',
    ];

    public function users()
    {
        return $this->belongsToMany(Telegram::class, 'telegram_group_users', 'group_id', 'user_id', 'id', 'id')
            ->withPivot(['notifications_enabled', 'joined_at', 'role'])
            ->withTimestamps();
    }

    public function tasks()
    {
        return $this->hasManyThrough(Task::class, Telegram::class, 'id', 'user_id', 'id', 'id');
    }

    public function getActiveUsersAttribute()
    {
        return $this->users()->wherePivot('is_active', true)->get();
    }
}
