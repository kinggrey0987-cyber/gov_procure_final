<?php

namespace App\Http\Controllers\Governance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Governance\AssignUserRoleRequest;
use App\Http\Requests\Governance\SendGovernanceNotificationRequest;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GovernanceAuthorizationController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'role_code' => $user?->roleCode(),
            'permissions' => [
                'governance_access' => $user?->can('governance.access') ?? false,
                'manage_users' => $user?->can('governance.manage-users') ?? false,
                'view_audit_logs' => $user?->can('governance.view-audit-logs') ?? false,
                'send_notifications' => $user?->can('governance.send-notifications') ?? false,
            ],
        ]);
    }

    public function auditLogs(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('governance.view-audit-logs'), 403);

        $logs = AuditLog::query()
            ->with('user:id,name,email')
            ->latest('created_at')
            ->paginate(20);

        return response()->json($logs);
    }

    public function assignUserRole(
        AssignUserRoleRequest $request,
        AuditLogService $auditLogService,
        NotificationService $notificationService
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        /** @var User $targetUser */
        $targetUser = User::query()->with('role')->findOrFail($request->integer('user_id'));
        $newRole = Role::query()->where('code', $request->string('role_code')->toString())->firstOrFail();

        DB::transaction(function () use (
            $request,
            $actor,
            $targetUser,
            $newRole,
            $auditLogService,
            $notificationService
        ): void {
            $previousRoleCode = $targetUser->role?->code;

            $targetUser->forceFill([
                'role_id' => $newRole->id,
                'office_id' => $request->input('office_id', $targetUser->office_id),
            ])->save();

            $auditLogService->log(
                actor: $actor,
                moduleName: 'Governance',
                actionType: 'ASSIGN_USER_ROLE',
                referenceType: User::class,
                referenceId: $targetUser->id,
                details: [
                    'previous_role_code' => $previousRoleCode,
                    'new_role_code' => $newRole->code,
                    'office_id' => $targetUser->office_id,
                    'remarks' => $request->input('remarks'),
                ],
            );

            $notificationService->notifyUser(
                user: $targetUser,
                title: 'Role Assignment Updated',
                message: 'Your governance role was updated to '.$newRole->name.'.',
                notificationType: 'ROLE_ASSIGNMENT',
                referenceType: User::class,
                referenceId: $targetUser->id,
            );
        });

        return response()->json([
            'message' => 'User role assignment updated successfully.',
            'user_id' => $targetUser->id,
            'role_code' => $newRole->code,
        ]);
    }

    public function sendNotification(
        SendGovernanceNotificationRequest $request,
        AuditLogService $auditLogService,
        NotificationService $notificationService
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $users = User::query()
            ->whereIn('id', $request->input('user_ids', []))
            ->get();

        DB::transaction(function () use ($request, $actor, $users, $auditLogService, $notificationService): void {
            $notificationService->notifyUsers(
                users: $users,
                title: $request->string('title')->toString(),
                message: $request->string('message')->toString(),
                notificationType: $request->string('notification_type')->toString(),
                referenceType: $request->input('reference_type'),
                referenceId: $request->integer('reference_id') ?: null,
            );

            $auditLogService->log(
                actor: $actor,
                moduleName: 'Governance',
                actionType: 'SEND_NOTIFICATION',
                referenceType: User::class,
                referenceId: $actor->id,
                details: [
                    'recipients_count' => $users->count(),
                    'notification_type' => $request->string('notification_type')->toString(),
                    'reference_type' => $request->input('reference_type'),
                    'reference_id' => $request->input('reference_id'),
                ],
            );
        });

        return response()->json([
            'message' => 'Governance notifications dispatched successfully.',
            'recipients' => $users->count(),
        ]);
    }
}
