<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Enums\TaskStatus;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'sometimes',
            'description' => 'sometimes|nullable',
            'status' => ['sometimes', Rule::enum(TaskStatus::class)],
            'due_date' => 'sometimes|nullable|date',
            'user_id' => 'required|exists:telegram_bot_users,telegram_id',
        ];
    }
}
