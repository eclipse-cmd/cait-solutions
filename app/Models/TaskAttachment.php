<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskAttachment extends Model
{
  use HasFactory;

  protected $fillable = [
    'task_id',
    'file_id',
    'file_type',
    'file_name',
    'file_url'
  ];

  public function task()
  {
    return $this->belongsTo(Task::class);
  }
}
