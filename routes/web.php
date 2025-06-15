<?php

use App\Http\Controllers\TelegramController;
use Illuminate\Support\Facades\Route;


Route::get('/', function () {
    return view('welcome');
});

Route::prefix('telegram')->controller(TelegramController::class)->group(function () {
    Route::get('set-webhook', 'setWebhook');
    Route::get('remove-webhook', 'removeWebhook');
    Route::post('webhook', 'handleWebhook');
    Route::post('send-message', 'sendMessage');
});
