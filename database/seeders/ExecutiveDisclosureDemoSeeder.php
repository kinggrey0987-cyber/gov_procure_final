<?php

namespace Database\Seeders;

use App\Models\DistributionRecord;
use App\Models\Ppmp;
use App\Models\PublicDisclosure;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\Analytics\PublicDisclosureService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class ExecutiveDisclosureDemoSeeder extends Seeder
{
    public function run(): void
    {
        $executive = User::query()
            ->whereHas('role', fn ($query) => $query->where('code', 'EXEC_OFFICER'))
            ->first();

        if (! $executive instanceof User) {
            $this->command?->warn('ExecutiveDisclosureDemoSeeder skipped: EXEC_OFFICER user not found.');

            return;
        }

        $ppmp = Ppmp::query()->where('status', 'APPROVED')->latest('updated_at')->first();
        $purchaseOrder = PurchaseOrder::query()->whereIn('status', ['COMPLETED', 'PLACED'])->latest('updated_at')->first();
        $distribution = DistributionRecord::query()->where('status', 'RECEIVED')->latest('updated_at')->first();

        if (! $ppmp || ! $purchaseOrder || ! $distribution) {
            $this->command?->warn('ExecutiveDisclosureDemoSeeder skipped: eligible workflow records are incomplete.');

            return;
        }

        Storage::disk('public')->put(
            'public_disclosures/procurement-summary-notice.txt',
            "GovSupply Connect procurement summary notice\nGenerated for executive DSS demo seeding.\n"
        );

        Storage::disk('public')->put(
            'public_disclosures/distribution-summary-notice.txt',
            "GovSupply Connect distribution summary notice\nGenerated for executive DSS demo seeding.\n"
        );

        $service = app(PublicDisclosureService::class);

        $this->ensurePublishedDisclosure(
            title: 'Procurement Summary Notice',
            actor: $executive,
            payload: [
                'title' => 'Procurement Summary Notice',
                'type' => 'PROCUREMENT_SUMMARY',
                'reference_type' => Ppmp::class,
                'reference_id' => (int) $ppmp->ppmp_id,
                'description' => 'Executive-approved PPMP procurement summary for public transparency.',
                'file_path' => 'public_disclosures/procurement-summary-notice.txt',
                'status' => 'PUBLISHED',
                'remarks' => 'Published as part of executive transparency briefing.',
            ],
            service: $service,
        );

        $this->ensurePublishedDisclosure(
            title: 'Office Supply Distribution Summary',
            actor: $executive,
            payload: [
                'title' => 'Office Supply Distribution Summary',
                'type' => 'DISTRIBUTION_SUMMARY',
                'reference_type' => DistributionRecord::class,
                'reference_id' => (int) $distribution->distribution_record_id,
                'description' => 'Completed office supply distribution summary for public visibility.',
                'file_path' => 'public_disclosures/distribution-summary-notice.txt',
                'status' => 'PUBLISHED',
                'remarks' => 'Published after delivery verification completion.',
            ],
            service: $service,
        );

        $this->ensureDraftDisclosure(
            title: 'Draft Transparency Notice',
            actor: $executive,
            payload: [
                'title' => 'Draft Transparency Notice',
                'type' => 'PROCUREMENT_SUMMARY',
                'reference_type' => PurchaseOrder::class,
                'reference_id' => (int) $purchaseOrder->purchase_order_id,
                'description' => 'Draft notice pending final executive review before publication.',
                'file_path' => null,
                'status' => 'DRAFT',
            ],
            service: $service,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function ensurePublishedDisclosure(string $title, User $actor, array $payload, PublicDisclosureService $service): void
    {
        $existing = PublicDisclosure::query()->where('title', $title)->latest('disclosure_id')->first();

        if (! $existing instanceof PublicDisclosure) {
            $service->createFromPayload($actor, $payload);

            return;
        }

        if ((string) $existing->status === 'PUBLISHED') {
            return;
        }

        $service->publish($existing, $actor, $payload['remarks'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function ensureDraftDisclosure(string $title, User $actor, array $payload, PublicDisclosureService $service): void
    {
        $existingDraft = PublicDisclosure::query()
            ->where('title', $title)
            ->where('status', 'DRAFT')
            ->first();

        if ($existingDraft instanceof PublicDisclosure) {
            return;
        }

        $service->createFromPayload($actor, $payload);
    }
}
