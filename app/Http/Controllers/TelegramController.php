<?php

namespace App\Http\Controllers;

use App\Models\Telegram as TelegramModel;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;
use Telegram\Bot\Laravel\Facades\Telegram;
use Illuminate\Support\Facades\Log;
use App\Services\TelegramTaskService;

class TelegramController extends Controller
{
    public function handleWebhook()
    {
        $taskService = app(TelegramTaskService::class);
        try {
            $update = Telegram::getWebhookUpdate();
            Log::info('Telegram Update Received:', $update->toArray());

            if ($update->isType('message')) {
                $message = $update->getMessage();
                $chat = $message->getChat();
                $telegramId = $chat->getId();
                $payload = [
                    'username' => $chat->getUsername(),
                    'first_name' => $chat->getFirstName(),
                    'last_name' => $chat->getLastName(),
                ];
                $taskService = app(TelegramTaskService::class);

                $user = TelegramModel::where('telegram_id', $telegramId)->first();
                if (!$user && !in_array($message->getText(), ['/start', '/help'])) {
                    Telegram::sendMessage([
                        'chat_id' => $chat->getId(),
                        'text' => 'You are not authorized. Please use /start to register.'
                    ]);
                    return response('OK', 200);
                }

                // Attachment file 
                $state = cache("tg:{$telegramId}:state");
                if ($state === 'awaiting_file_upload' && ($message->getDocument() || $message->getPhoto())) {
                    $taskId = cache("tg:{$telegramId}:attach_task_id");
                    $task = $user ? $user->tasks()->find($taskId) : null;
                    if ($task) {
                        if ($message->getDocument()) {
                            $fileId = $message->getDocument()->getFileId();
                            $fileName = $message->getDocument()->getFileName();
                            $fileType = $message->getDocument()->getMimeType();
                        } else {
                            $photo = $message->getPhoto();
                            $fileId = $photo[count($photo) - 1]->getFileId();
                            $fileName = null;
                            $fileType = 'photo';
                        }
                        $taskService->attachFileToTask($task, $fileId, $fileType, $fileName);
                        Telegram::sendMessage([
                            'chat_id' => $chat->getId(),
                            'text' => 'File uploaded has been attached to file..'
                        ]);
                    } else {
                        Telegram::sendMessage([
                            'chat_id' => $chat->getId(),
                            'text' => 'Task not found or not you are not authorized.'
                        ]);
                    }
                    cache()->forget("tg:{$telegramId}:state");
                    cache()->forget("tg:{$telegramId}:attach_task_id");
                    return response('OK', 200);
                }
                $text = $message->getText();
                $this->handleMessageAction($chat, $text, $telegramId, $payload, $taskService);
            } elseif ($update->isType('callback_query')) {
                $callback = $update->getCallbackQuery();
                $this->handleCallbackAction($callback, $taskService);
            }
        } catch (\Exception $e) {
            Log::error('Telegram Webhook Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
        }
        return response('OK', 200);
    }

    public function setWebhook()
    {
        $url = config('app.url') . '/telegram/webhook';
        try {
            $response = Telegram::setWebhook(['url' => $url]);
            return ApiResponseService::success('Webhook set successfully!', $response);
        } catch (\Exception $e) {
            return ApiResponseService::error($e->getMessage());
        }
    }

    public function removeWebhook()
    {
        try {
            $response = Telegram::removeWebhook();
            return ApiResponseService::success('Webhook removed successfully!', $response);
        } catch (\Exception $e) {
            return ApiResponseService::error($e->getMessage());
        }
    }

    public function sendMessage(Request $request)
    {
        $chatId = $request->input('chat_id');
        $messageText = $request->input('text');

        if (!$chatId || !$messageText) return ApiResponseService::error('Chat ID and message text are required.');

        try {
            Telegram::sendMessage(['chat_id' => $chatId, 'text' => $messageText]);
            return ApiResponseService::success('Message sent successfully!');
        } catch (\Exception $e) {
            return ApiResponseService::error($e->getMessage());
        }
    }

    public static function handleMessageAction($chat, $text, $telegramId, $payload, $taskService)
    {
        switch ($text) {
            case '/start':
                TelegramModel::updateOrCreate(['telegram_id' => $telegramId], $payload);
                $greetingName = strtoupper($chat->getFirstName()) ?: 'There';
                $response = "Hello, $greetingName! Welcome to CAIT Solution Telegram answering bot.\nType /help to see available commands.";
                Telegram::sendMessage(['chat_id' => $chat->getId(), 'text' => $response]);
                break;
            case '/help':
                $response = "Here are the commands you can use:\n\n"
                    . "/start - Greet the bot and save your info.\n"
                    . "/help - Display this help message.\n"
                    . "/newtask - Create a new task.\n"
                    . "/mytasks - List your tasks.\n"
                    . "/searchtasks - Search for your tasks.\n"
                    . "/attachfile - Attach a file to one of your tasks.\n";
                Telegram::sendMessage(['chat_id' => $chat->getId(), 'text' => $response]);
                break;
            case '/newtask':
                Telegram::sendMessage([
                    'chat_id' => $chat->getId(),
                    'text' => 'Please enter the task title.'
                ]);
                // Store state in cache or DB for next message
                cache(["tg:{$telegramId}:state" => 'awaiting_task_title'], now()->addMinutes(10));
                break;
            case '/mytasks':
                $user = TelegramModel::where('telegram_id', $telegramId)->first();
                if (!$user) {
                    Telegram::sendMessage(['chat_id' => $chat->getId(), 'text' => 'User not found. Please /start first.']);
                    break;
                }
                $tasks = $taskService->listTasks($user);
                if ($tasks->isEmpty()) {
                    Telegram::sendMessage(['chat_id' => $chat->getId(), 'text' => 'You have no tasks. Use /newtask to create one.']);
                    break;
                }
                foreach ($tasks as $task) {
                    $keyboard = self::taskActionKeyboard($task->id);
                    $textMsg = "TASK DATA\n\nID: {$task->id}\nTitle: {$task->title}\nStatus: {$task->status->value}\nDescription: {$task->description}";
                    Telegram::sendMessage([
                        'chat_id' => $chat->getId(),
                        'text' => $textMsg,
                        'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
                    ]);
                }
                break;
            case '/searchtasks':
                Telegram::sendMessage([
                    'chat_id' => $chat->getId(),
                    'text' => 'Please enter your search query.'
                ]);
                cache(["tg:{$telegramId}:state" => 'awaiting_search_query'], now()->addMinutes(10));
                break;
            case '/attachfile':
                Telegram::sendMessage([
                    'chat_id' => $chat->getId(),
                    'text' => 'Please enter the Task ID you want to attach a file to.'
                ]);
                cache(["tg:{$telegramId}:state" => 'awaiting_attach_task_id'], now()->addMinutes(10));
                break;
            default:
                $state = cache("tg:{$telegramId}:state");
                if ($state === 'awaiting_task_title') {
                    cache(["tg:{$telegramId}:task_title" => $text], now()->addMinutes(10));
                    cache(["tg:{$telegramId}:state" => 'awaiting_task_description'], now()->addMinutes(10));
                    Telegram::sendMessage([
                        'chat_id' => $chat->getId(),
                        'text' => 'Please send the task description.'
                    ]);
                } elseif ($state === 'awaiting_task_description') {
                    $title = cache("tg:{$telegramId}:task_title");
                    $user = TelegramModel::where('telegram_id', $telegramId)->first();
                    if ($user && $title) {
                        $task = $taskService->createTask($user, $title, $text);
                        Telegram::sendMessage([
                            'chat_id' => $chat->getId(),
                            'text' => "Task '{$task->title}' created!\n\nUse /mytasks to see all your tasks or /newtask to create a new one."
                        ]);
                    } else {
                        Telegram::sendMessage([
                            'chat_id' => $chat->getId(),
                            'text' => 'Could not create task. Please try again.'
                        ]);
                    }
                    cache()->forget("tg:{$telegramId}:state");
                    cache()->forget("tg:{$telegramId}:task_title");
                } elseif ($state === 'awaiting_edit_title') {
                    $taskId = cache("tg:{$telegramId}:edit_task_id");
                    $user = TelegramModel::where('telegram_id', $telegramId)->first();
                    $task = $user ? $user->tasks()->find($taskId) : null;
                    if ($task) {
                        $taskService->updateTaskTitle($task, $text);
                        Telegram::sendMessage([
                            'chat_id' => $chat->getId(),
                            'text' => "Task title updated to '{$task->title}'."
                        ]);
                    } else {
                        Telegram::sendMessage([
                            'chat_id' => $chat->getId(),
                            'text' => 'Could not update task title.'
                        ]);
                    }
                    cache()->forget("tg:{$telegramId}:state");
                    cache()->forget("tg:{$telegramId}:edit_task_id");
                } elseif ($state === 'awaiting_edit_description') {
                    $taskId = cache("tg:{$telegramId}:edit_task_id");
                    $user = TelegramModel::where('telegram_id', $telegramId)->first();
                    $task = $user ? $user->tasks()->find($taskId) : null;
                    if ($task) {
                        $taskService->updateTaskDescription($task, $text);
                        Telegram::sendMessage([
                            'chat_id' => $chat->getId(),
                            'text' => "Task description updated."
                        ]);
                    } else {
                        Telegram::sendMessage([
                            'chat_id' => $chat->getId(),
                            'text' => 'Could not update task description.'
                        ]);
                    }
                    cache()->forget("tg:{$telegramId}:state");
                    cache()->forget("tg:{$telegramId}:edit_task_id");
                } elseif ($state === 'awaiting_search_query') {
                    $user = TelegramModel::where('telegram_id', $telegramId)->first();
                    $results = $taskService->searchTasks($user, $text);
                    if ($results->isEmpty()) {
                        Telegram::sendMessage([
                            'chat_id' => $chat->getId(),
                            'text' => 'No tasks found for your search.'
                        ]);
                    } else {
                        foreach ($results as $task) {
                            $keyboard = self::taskActionKeyboard($task->id);
                            $textMsg = "Task: {$task->title}\nStatus: {$task->status->value}\n{$task->description}";
                            Telegram::sendMessage([
                                'chat_id' => $chat->getId(),
                                'text' => $textMsg,
                                'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
                            ]);
                        }
                    }
                    cache()->forget("tg:{$telegramId}:state");
                } elseif ($state === 'awaiting_attach_task_id') {
                    $user = TelegramModel::where('telegram_id', $telegramId)->first();
                    $task = $user ? $user->tasks()->find($text) : null;
                    if ($task) {
                        cache(["tg:{$telegramId}:attach_task_id" => $task->id], now()->addMinutes(10));
                        cache(["tg:{$telegramId}:state" => 'awaiting_file_upload'], now()->addMinutes(10));
                        Telegram::sendMessage([
                            'chat_id' => $chat->getId(),
                            'text' => 'Please send the file you want to attach to this task.'
                        ]);
                    } else {
                        Telegram::sendMessage([
                            'chat_id' => $chat->getId(),
                            'text' => 'Task not found or not authorized.'
                        ]);
                        cache()->forget("tg:{$telegramId}:state");
                    }
                } elseif ($state === 'awaiting_file_upload') {
                    // I am handling file upload inside the message handler for non-file attachments
                    Telegram::sendMessage([
                        'chat_id' => $chat->getId(),
                        'text' => 'Please send a file (document, photo, etc.) to attach.'
                    ]);
                } else {
                    Telegram::sendMessage([
                        'chat_id' => $chat->getId(),
                        'text' => "You said: '$text'"
                    ]);
                }
                break;
        }
    }

    public static function handleCallbackAction($callback, $taskService)
    {
        $data = $callback->getData();
        $chatId = $callback->getMessage()->getChat()->getId();
        $user = TelegramModel::where('telegram_id', $chatId)->first();
        if (str_starts_with($data, 'edit_task_')) {
            $taskId = str_replace('edit_task_', '', $data);
            $task = $user->tasks()->find($taskId);
            if ($task) {
                $keyboard = [
                    [
                        ['text' => 'Edit Title', 'callback_data' => 'edit_title_' . $taskId],
                        ['text' => 'Edit Description', 'callback_data' => 'edit_desc_' . $taskId],
                    ]
                ];
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'What do you want to edit?',
                    'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
                ]);
            }
        } elseif (str_starts_with($data, 'edit_title_')) {
            $taskId = str_replace('edit_title_', '', $data);
            cache(["tg:{$chatId}:edit_task_id" => $taskId], now()->addMinutes(10));
            cache(["tg:{$chatId}:state" => 'awaiting_edit_title'], now()->addMinutes(10));
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'Enter new title for the task.'
            ]);
        } elseif (str_starts_with($data, 'edit_desc_')) {
            $taskId = str_replace('edit_desc_', '', $data);
            cache(["tg:{$chatId}:edit_task_id" => $taskId], now()->addMinutes(10));
            cache(["tg:{$chatId}:state" => 'awaiting_edit_description'], now()->addMinutes(10));
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'Enter new description for the task.'
            ]);
        } elseif (str_starts_with($data, 'delete_task_')) {
            $taskId = str_replace('delete_task_', '', $data);
            $task = $user->tasks()->find($taskId);
            if ($task) {
                $taskService->deleteTask($task);
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'Task "' . $task->title . '" deleted.'
                ]);
            }
        } elseif (str_starts_with($data, 'status_task_')) {
            $taskId = str_replace('status_task_', '', $data);
            $task = $user->tasks()->find($taskId);
            if ($task) {
                $statuses = [
                    ['text' => 'Pending', 'callback_data' => 'setstatus_' . $taskId . '_PENDING'],
                    ['text' => 'In Progress', 'callback_data' => 'setstatus_' . $taskId . '_IN_PROGRESS'],
                    ['text' => 'Completed', 'callback_data' => 'setstatus_' . $taskId . '_COMPLETED'],
                ];
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'Choose new status:',
                    'reply_markup' => json_encode(['inline_keyboard' => [$statuses]])
                ]);
            }
        } elseif (str_starts_with($data, 'setstatus_')) {
            [$prefix, $taskId, $status] = explode('_', $data, 3);
            $task = $user->tasks()->find($taskId);
            if ($task) {
                $taskService->updateTaskStatus($task, $status);
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => "Task status updated to $status."
                ]);
            }
        }
    }

    public static function taskActionKeyboard($taskId)
    {
        return [
            [
                ['text' => 'Edit', 'callback_data' => 'edit_task_' . $taskId],
                ['text' => 'Delete', 'callback_data' => 'delete_task_' . $taskId],
                ['text' => 'Status', 'callback_data' => 'status_task_' . $taskId],
            ]
        ];
    }
}
