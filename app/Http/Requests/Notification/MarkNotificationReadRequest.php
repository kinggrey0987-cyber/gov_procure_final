<?php

namespace App\Http\Requests\Notification;

use Illuminate\Foundation\Http\FormRequest;

class MarkNotificationReadRequest extends FormRequest
{
    public function authorize(): bool
    {
        $notification = $this->route('notification');

        return (bool) $this->user()?->can('notification.update', $notification);
    }

    public function rules(): array
    {
        return [];
    }
}
