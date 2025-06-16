<?php

namespace App\Services;

use App\Models\Telegram as TelegramModel;
use App\Models\TelegramGroup;
use Telegram\Bot\Laravel\Facades\Telegram;
use App\Models\Task;
use Illuminate\Support\Facades\Log;

class TelegramTaskService
{
  public function createTask(TelegramModel $user, $title, $description)
  {
    $task = $user->tasks()->create([
      'title' => $title,
      'description' => $description,
      'status' => 'PENDING',
    ]);

    // Notify groups about new task
    $this->notifyGroupsAboutTaskUpdate($user, $task, "📝 New task created: {$title}");

    return $task;
  }

  public function listTasks(TelegramModel $user, $limit = 20)
  {
    return $user->tasks()->latest()->take($limit)->get();
  }

  public function updateTaskStatus(Task $task, $status)
  {
    $oldStatus = $task->status;
    $task->update(['status' => $status]);

    // Notify groups about status change
    $user = $task->user;
    $message = "🔄 Task status updated\n\nTask: {$task->title}\nFrom: {$oldStatus->value} → To: {$status}";
    $this->notifyGroupsAboutTaskUpdate($user, $task, $message);

    return $task;
  }

  public function updateTaskTitle($task, string $title)
  {
    $oldTitle = $task->title;
    $task->update(['title' => $title]);

    // Notify groups about title change
    $user = $task->user;
    $message = "✏️ Task title updated\n\nFrom: {$oldTitle}\nTo: {$title}";
    $this->notifyGroupsAboutTaskUpdate($user, $task, $message);

    return $task;
  }

  public function deleteTask(Task $task)
  {
    $task->delete();
    return true;
  }

  public function updateTaskDescription(Task $task, string $description)
  {
    $task->update(['description' => $description]);

    // Notify groups about description change
    $user = $task->user;
    $message = "📄 Task description updated\n\nTask: {$task->title}";
    $this->notifyGroupsAboutTaskUpdate($user, $task, $message);

    return $task;
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

  public function searchTasks(TelegramModel $user, string $query)
  {
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

  public function getGroupTasks(TelegramGroup $group)
  {
    $userIds = $group->users()->pluck('telegrams.id');

    return Task::whereIn('user_id', $userIds)
      ->with('user')
      ->orderBy('created_at', 'desc')
      ->get();
  }

  public function searchGroupTasks(TelegramGroup $group, string $query)
  {
    $userIds = $group->users()->pluck('telegrams.id');

    return Task::whereIn('user_id', $userIds)
      ->where(function ($q) use ($query) {
        $q->where('title', 'LIKE', "%{$query}%")
          ->orWhere('description', 'LIKE', "%{$query}%");
      })
      ->with('user')
      ->orderBy('created_at', 'desc')
      ->get();
  }

  public function notifyGroupsAboutTaskUpdate(TelegramModel $user, ?Task $task, string $message)
  {
    try {
      // Get all active groups the user belongs to
      $groups = $user->groups()
        ->where('telegram_groups.is_active', true)
        ->wherePivot('is_active', true)
        ->get();

      foreach ($groups as $group) {
        // Check if notifications are enabled for this group
        $notificationUsers = $group->users()
          ->wherePivot('notifications_enabled', true)
          ->orWherePivotNull('notifications_enabled') // Default to enabled
          ->count();

        if ($notificationUsers > 0) {
          $fullMessage = "🔔 Task Notification\n\n{$message}\n\n👤 By: {$user->full_name}";

          // Add task link/ID if task exists
          if ($task) {
            $fullMessage .= "\n🆔 Task ID: #{$task->id}";
          }

          Telegram::sendMessage([
            'chat_id' => $group->chat_id,
            'text' => $fullMessage,
            'parse_mode' => 'HTML'
          ]);

          Log::info("Task notification sent to group {$group->chat_id}: {$message}");
        }
      }
    } catch (\Exception $e) {
      Log::error("Failed to send group notifications: " . $e->getMessage());
    }
  }

  public function toggleGroupNotifications(TelegramGroup $group, TelegramModel $user, bool $enabled)
  {
    $group->users()->updateExistingPivot($user->id, [
      'notifications_enabled' => $enabled
    ]);

    return $enabled;
  }

  public function getUserNotificationStatus(TelegramGroup $group, TelegramModel $user)
  {
    $pivot = $group->users()->where('telegrams.id', $user->id)->first();
    return $pivot ? ($pivot->pivot->notifications_enabled ?? true) : true;
  }

  public function getTaskStats(TelegramModel $user)
  {
    $tasks = $user->tasks();

    return [
      'total' => $tasks->count(),
      'pending' => $tasks->where('status', 'PENDING')->count(),
      'in_progress' => $tasks->where('status', 'IN_PROGRESS')->count(),
      'completed' => $tasks->where('status', 'COMPLETED')->count(),
    ];
  }

  public function getGroupTaskStats(TelegramGroup $group)
  {
    $userIds = $group->users()->pluck('telegrams.id');
    $tasks = Task::whereIn('user_id', $userIds);

    return [
      'total' => $tasks->count(),
      'pending' => $tasks->where('status', 'PENDING')->count(),
      'in_progress' => $tasks->where('status', 'IN_PROGRESS')->count(),
      'completed' => $tasks->where('status', 'COMPLETED')->count(),
      'users_count' => $group->users()->count(),
    ];
  }
}
