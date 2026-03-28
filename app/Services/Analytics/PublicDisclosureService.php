<?php

namespace App\Services\Analytics;

use App\Models\ApprovalHistory;
use App\Models\PublicDisclosure;
use App\Models\StatusHistory;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublicDisclosureService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly NotificationService $notificationService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createDraft(User $actor, array $payload): PublicDisclosure
    {
        $this->assertExecutive($actor);

        return DB::transaction(function () use ($actor, $payload): PublicDisclosure {
            $disclosure = PublicDisclosure::query()->create([
                'title' => (string) $payload['title'],
                'type' => strtoupper((string) $payload['type']),
                'reference_type' => $payload['reference_type'] ?? null,
                'reference_id' => $payload['reference_id'] ?? null,
                'description' => $payload['description'] ?? null,
                'published_by' => null,
                'published_at' => null,
                'file_path' => $payload['file_path'] ?? null,
                'status' => 'DRAFT',
            ]);

            $this->recordStatus($disclosure, null, 'DRAFT', $actor, 'Disclosure draft created.');
            $this->recordAudit($actor, 'CREATE_DRAFT', $disclosure, []);

            return $disclosure;
        });
    }

    public function publish(PublicDisclosure $disclosure, User $actor, ?string $remarks = null): PublicDisclosure
    {
        $this->assertExecutive($actor);

        if ((string) $disclosure->status === 'PUBLISHED') {
            throw ValidationException::withMessages([
                'status' => 'Disclosure is already published.',
            ]);
        }

        if ((string) $disclosure->status !== 'DRAFT') {
            throw ValidationException::withMessages([
                'status' => 'Only DRAFT disclosures can be published.',
            ]);
        }

        return DB::transaction(function () use ($disclosure, $actor, $remarks): PublicDisclosure {
            $oldStatus = (string) $disclosure->status;

            $disclosure->forceFill([
                'status' => 'PUBLISHED',
                'published_by' => (int) $actor->id,
                'published_at' => now(),
                'description' => $remarks ?? $disclosure->description,
            ])->save();

            $this->recordStatus($disclosure, $oldStatus, 'PUBLISHED', $actor, 'Disclosure published.');
            $this->recordApproval($disclosure, $actor, 'PUBLISHED', 'Disclosure publication approved.');
            $this->recordAudit($actor, 'PUBLISH', $disclosure, []);

            $publicUsers = User::query()->whereHas('role', fn ($q) => $q->where('code', 'PUBLIC'))->get();
            $this->notificationService->notifyUsers(
                users: $publicUsers,
                title: 'New Public Disclosure',
                message: $disclosure->title,
                notificationType: 'PUBLIC_DISCLOSURE_PUBLISHED',
                referenceType: PublicDisclosure::class,
                referenceId: (int) $disclosure->disclosure_id,
            );

            return $disclosure->fresh(['publisher']) ?? $disclosure;
        });
    }

    private function assertExecutive(User $actor): void
    {
        if (!$actor->isExecutive()) {
            throw ValidationException::withMessages([
                'role' => 'Only EXEC_OFFICER can manage disclosure publishing.',
            ]);
        }
    }

    private function recordStatus(PublicDisclosure $disclosure, ?string $oldStatus, string $newStatus, User $actor, string $remarks): void
    {
        StatusHistory::query()->create([
            'reference_type' => PublicDisclosure::class,
            'reference_id' => (int) $disclosure->disclosure_id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by' => (int) $actor->id,
            'remarks' => $remarks,
            'changed_at' => now(),
        ]);
    }

    private function recordApproval(PublicDisclosure $disclosure, User $actor, string $status, string $remarks): void
    {
        ApprovalHistory::query()->create([
            'reference_type' => PublicDisclosure::class,
            'reference_id' => (int) $disclosure->disclosure_id,
            'approved_by' => (int) $actor->id,
            'status' => $status,
            'remarks' => $remarks,
            'approved_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function recordAudit(User $actor, string $actionType, PublicDisclosure $disclosure, array $details): void
    {
        $this->auditLogService->log(
            actor: $actor,
            moduleName: 'Executive.PublicDisclosure',
            actionType: $actionType,
            referenceType: PublicDisclosure::class,
            referenceId: (int) $disclosure->disclosure_id,
            details: array_merge([
                'type' => $disclosure->type,
                'status' => $disclosure->status,
                'title' => $disclosure->title,
            ], $details),
            logDomain: 'BUSINESS',
            officeId: $actor->office_id,
        );
    }
}
