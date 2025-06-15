<?php

namespace App\Models;

use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['title', 'description', 'status', 'due_date'];

    protected $casts = [
        'status' => TaskStatus::class
    ];

    public function user()
    {
        return $this->belongsTo(Telegram::class);
    }

    public function attachments()
    {
        return $this->hasMany(TaskAttachment::class);
    }
}
