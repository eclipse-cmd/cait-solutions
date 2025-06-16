<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Telegram extends Model
{
    use HasFactory;

    protected $table = 'telegram_bot_users';

    protected $fillable = ['telegram_id', 'username', 'first_name', 'last_name'];

    public function tasks()
    {
        return $this->hasMany(Task::class, 'user_id', 'id');
    }

    public function groups()
    {
        return $this->belongsToMany(TelegramGroup::class, 'telegram_group_users', 'user_id', 'group_id', 'id', 'id')
            ->withPivot(['notifications_enabled', 'joined_at', 'role', 'is_active'])
            ->withTimestamps();
    }

    public function getFullNameAttribute()
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function getActiveGroupsAttribute()
    {
        return $this->groups()->where('telegram_groups.is_active', true)
            ->wherePivot('is_active', true)
            ->get();
    }
}
