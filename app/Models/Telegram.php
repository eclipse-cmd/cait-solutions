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
}
