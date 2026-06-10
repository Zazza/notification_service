<?php

declare(strict_types=1);

namespace App\Presentation\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

class SendBulkNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'channel' => 'required|string|in:sms,email',
            'priority' => 'required|string|in:high,normal,low',
            'recipient_ids' => 'required|array|min:1',
            'recipient_ids.*' => 'required|string',
            'content' => 'required|string|min:1|max:5000',
        ];
    }

    public function messages(): array
    {
        return [
            'channel.required' => 'Канал обязателен',
            'channel.in' => 'Канал должен быть: sms или email',
            'priority.required' => 'Приоритет обязателен',
            'priority.in' => 'Приоритет должен быть: high, normal или low',
            'recipient_ids.required' => 'Список получателей обязателен',
            'recipient_ids.array' => 'Список получателей должен быть массивом',
            'recipient_ids.min' => 'Нужен хотя бы один получатель',
            'content.required' => 'Текст сообщения обязателен',
            'content.max' => 'Текст сообщения не должен превышать 5000 символов',
        ];
    }
}
