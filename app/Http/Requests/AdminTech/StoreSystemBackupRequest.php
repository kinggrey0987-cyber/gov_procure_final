<?php

namespace App\Http\Requests\AdminTech;

use Illuminate\Foundation\Http\FormRequest;

class StoreSystemBackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('admin.system-backup.manage') ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'backup_type' => ['required', 'string', 'in:database_backup,server_backup,storage_backup'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}

