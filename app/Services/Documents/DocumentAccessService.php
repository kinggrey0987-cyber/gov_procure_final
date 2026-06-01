<?php

namespace App\Services\Documents;

use App\Models\Claim;
use App\Models\Delivery;
use App\Models\Document;
use App\Models\Ppmp;
use App\Models\PublicDisclosure;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\SupplyRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class DocumentAccessService
{
    /**
     * @var array<string, array{model: class-string<Model>, key: string, view_ability: string, manage_ability: string}>
     */
    private const MODULE_MAP = [
        'ppmp' => [
            'model' => Ppmp::class,
            'key' => 'ppmp_id',
            'view_ability' => 'office.ppmp.view',
            'manage_ability' => 'office.ppmp.view',
        ],
        'supply_requests' => [
            'model' => SupplyRequest::class,
            'key' => 'supply_request_id',
            'view_ability' => 'office.supply-request.view',
            'manage_ability' => 'office.supply-request.view',
        ],
        'purchase_requests' => [
            'model' => PurchaseRequest::class,
            'key' => 'purchase_request_id',
            'view_ability' => 'procurement.purchase-request.view',
            'manage_ability' => 'procurement.purchase-request.view',
        ],
        'purchase_orders' => [
            'model' => PurchaseOrder::class,
            'key' => 'purchase_order_id',
            'view_ability' => 'procurement.purchase-order.view',
            'manage_ability' => 'procurement.purchase-order.view',
        ],
        'deliveries' => [
            'model' => Delivery::class,
            'key' => 'delivery_id',
            'view_ability' => 'delivery.view',
            'manage_ability' => 'delivery.update',
        ],
        'claims' => [
            'model' => Claim::class,
            'key' => 'claims_id',
            'view_ability' => 'claim.view',
            'manage_ability' => 'claim.attachments.manage',
        ],
        'public_disclosures' => [
            'model' => PublicDisclosure::class,
            'key' => 'disclosure_id',
            'view_ability' => 'public-disclosure.view',
            'manage_ability' => 'public-disclosure.manage',
        ],
    ];

    public function normalizeModule(string $module): string
    {
        return strtolower($module);
    }

    /**
     * @return array<int, string>
     */
    public function allowedModules(): array
    {
        return array_keys(self::MODULE_MAP);
    }

    public function resolveParentOrFail(string $module, int $referenceId): Model
    {
        $definition = $this->moduleDefinition($module);

        return $definition['model']::query()
            ->where($definition['key'], $referenceId)
            ->firstOrFail();
    }

    public function canView(?User $actor, string $module, Model $parent): bool
    {
        $module = $this->normalizeModule($module);

        if ($module === 'ppmp' && $actor instanceof User && $actor->can('executive.ppmp-approval.view-any')) {
            return true;
        }

        if ($module === 'public_disclosures') {
            $isPublished = (string) ($parent->getAttribute('status') ?? '') === 'PUBLISHED'
                && (bool) $parent->getAttribute('published_at');

            if ($actor === null) {
                return $isPublished;
            }

            if ($actor->can('public-disclosure.manage')) {
                return true;
            }

            if ($actor->can('public-disclosure.view')) {
                return true;
            }

            return $actor->isPublicUser() && $isPublished;
        }

        if (! $actor instanceof User) {
            return false;
        }

        $definition = $this->moduleDefinition($module);

        return $actor->can($definition['view_ability'], $parent);
    }

    public function canManage(?User $actor, string $module, Model $parent): bool
    {
        if (! $actor instanceof User) {
            return false;
        }

        $module = $this->normalizeModule($module);

        if ($module === 'public_disclosures') {
            return $actor->can('public-disclosure.manage');
        }

        $definition = $this->moduleDefinition($module);

        return $actor->can($definition['manage_ability'], $parent);
    }

    public function resolveParentForDocument(Document $document): Model
    {
        return $this->resolveParentOrFail((string) $document->related_module, (int) $document->related_reference_id);
    }

    /**
     * @return array{model: class-string<Model>, key: string, view_ability: string, manage_ability: string}
     */
    private function moduleDefinition(string $module): array
    {
        $module = $this->normalizeModule($module);

        if (! array_key_exists($module, self::MODULE_MAP)) {
            throw ValidationException::withMessages([
                'related_module' => 'Unsupported related module for documents.',
            ]);
        }

        return self::MODULE_MAP[$module];
    }
}
