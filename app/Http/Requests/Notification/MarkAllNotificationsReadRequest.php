<?php

namespace App\Http\Requests\Notification;

use Illuminate\Foundation\Http\FormRequest;

class MarkAllNotificationsReadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('notifications.access');
    }

    public function rules(): array
    {
        return [];
    }
}
