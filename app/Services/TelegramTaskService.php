<?php

namespace App\Services;

use App\Models\Telegram as TelegramModel;
use Telegram\Bot\Laravel\Facades\Telegram;
use App\Models\Task;

class TelegramTaskService
{
  public function createTask(TelegramModel $user, $title, $description)
  {
    return $user->tasks()->create([
      'title' => $title,
      'description' => $description,
      'status' => 'PENDING',
    ]);
  }

  public function listTasks(TelegramModel $user, $limit = 20)
  {
    return $user->tasks()->latest()->take($limit)->get();
  }

  public function updateTaskDescription(Task $task, $description)
  {
    $task->description = $description;
    $task->save();
    return $task;
  }

  public function updateTaskStatus(Task $task, $status)
  {
    $task->status = $status;
    $task->save();
    return $task;
  }

  public function deleteTask(Task $task)
  {
    $task->delete();
    return true;
  }

  public function updateTaskTitle(Task $task, $title)
  {
    $task->title = $title;
    $task->save();
    return $task;
  }

  public function searchTasks($user, $query)
  {
    if (!$user) return collect();
    return $user->tasks()
      ->where(function ($q) use ($query) {
        $q->where('title', 'like', "%$query%")
          ->orWhere('description', 'like', "%$query%")
          ->orWhere('status', 'like', "%$query%")
        ;
      })
      ->latest()
      ->get();
  }

  public function attachFileToTask($task, $fileId, $fileType = null, $fileName = null)
  {
    $fileUrl = null;
    try {
      $telegramFile = Telegram::getFile(['file_id' => $fileId]);
      $telegramPath = $telegramFile->getFilePath();
      $ext = $fileName ? pathinfo($fileName, PATHINFO_EXTENSION) : ($fileType === 'photo' ? 'jpg' : 'dat');

      $localName = uniqid('tg_', true) . ($ext ? ".{$ext}" : '');
      $localPath = public_path('telegram_attachments/' . $localName);
      $publicUrl = url('telegram_attachments/' . $localName);
      if (!file_exists(public_path('telegram_attachments'))) {
        mkdir(public_path('telegram_attachments'), 0777, true);
      }

      $tgApiUrl = 'https://api.telegram.org/file/bot' . config('telegram.bots.v1_cait_bot.token') . '/' . $telegramPath;
      error_log($tgApiUrl);
      file_put_contents($localPath, file_get_contents($tgApiUrl));
      $fileUrl = $publicUrl;
    } catch (\Exception $e) {
      error_log($e->getMessage());
      $fileUrl = null;
    }
    error_log($fileUrl);
    return $task->attachments()->create([
      'file_id' => $fileId,
      'file_type' => $fileType,
      'file_name' => $fileName,
      'file_url' => $fileUrl,
    ]);
  }
}
