<?php

namespace Database\Seeders;

use App\Models\Document;
use App\Models\DistributionRecord;
use App\Models\Ppmp;
use App\Models\PublicDisclosure;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Services\Analytics\PublicDisclosureService;
use App\Services\Executive\PublicDisclosurePackageService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class ExecutiveDisclosureDemoSeeder extends Seeder
{
    private function publicDisk(): string
    {
        return (string) config('filesystems.public_disk', 'public');
    }

    public function run(): void
    {
        $executive = User::query()
            ->whereHas('role', fn ($query) => $query->where('code', 'EXEC_OFFICER'))
            ->first();

        if (! $executive instanceof User) {
            $this->command?->warn('ExecutiveDisclosureDemoSeeder skipped: EXEC_OFFICER user not found.');

            return;
        }

        $approvedPpmps = Ppmp::query()
            ->where('status', 'APPROVED')
            ->latest('updated_at')
            ->take(4)
            ->get();

        $purchaseOrders = PurchaseOrder::query()
            ->with(['purchaseRequest.office', 'supplyRequest.ppmp.office', 'supplier', 'items', 'deliveries.distributionRecords.office'])
            ->where(function ($query): void {
                $query->where('status', 'COMPLETED')
                    ->orWhere('status', 'PLACED');
            })
            ->whereHas('purchaseRequest')
            ->latest('updated_at')
            ->take(4)
            ->get();

        $receivedDistributions = DistributionRecord::query()
            ->where(function ($query): void {
                $query->where('status', 'RECEIVED')
                    ->orWhere('status', 'RELEASED');
            })
            ->latest('updated_at')
            ->take(4)
            ->get();

        $ppmp = $approvedPpmps->first();
        $purchaseOrder = $purchaseOrders->first();
        $distribution = $receivedDistributions->first();

        if (! $ppmp || ! $purchaseOrder || ! $distribution) {
            $this->command?->warn('ExecutiveDisclosureDemoSeeder skipped: eligible workflow records are incomplete.');

            return;
        }

        $service = app(PublicDisclosureService::class);
        $packageService = app(PublicDisclosurePackageService::class);

        $primaryPpmp = $approvedPpmps->first();
        $secondaryPpmp = $approvedPpmps->skip(1)->first();
        $tertiaryPpmp = $approvedPpmps->skip(2)->first();
        $secondaryPurchaseOrder = $purchaseOrders->skip(1)->first();
        $primaryDistribution = $receivedDistributions->first();
        $secondaryDistribution = $receivedDistributions->skip(1)->first();

        $publishedDefinitions = [
            [
                'title' => sprintf('Procurement Disclosure Package - %s', (string) ($purchaseOrder->po_number ?: 'PO-'.$purchaseOrder->purchase_order_id)),
                'type' => 'PROCUREMENT_PACKAGE',
                'reference_type' => PurchaseOrder::class,
                'reference_id' => (int) $purchaseOrder->purchase_order_id,
                'description' => sprintf(
                    'Official public-safe procurement package linking %s to PR-%d and related procurement records.',
                    (string) ($purchaseOrder->po_number ?: 'PO-'.$purchaseOrder->purchase_order_id),
                    (int) $purchaseOrder->purchase_request_id,
                ),
                'remarks' => 'Published as the canonical PR-PO disclosure package for public transparency.',
                'attachments' => [
                    ['title' => 'PR-PO Certification', 'suffix' => 'pr-po-certification.txt', 'body' => "PR-PO Certification\nPO: ".($purchaseOrder->po_number ?: 'PO-'.$purchaseOrder->purchase_order_id)."\nPR: PR-{$purchaseOrder->purchase_request_id}\nStatus: {$purchaseOrder->status}\n"],
                ],
            ],
            [
                'title' => 'Procurement Summary Notice',
                'type' => 'PROCUREMENT_SUMMARY',
                'reference_type' => Ppmp::class,
                'reference_id' => (int) $ppmp->ppmp_id,
                'description' => 'Executive-approved PPMP procurement summary for public transparency.',
                'remarks' => 'Published as part of executive transparency briefing.',
                'attachments' => [
                    ['title' => 'Procurement Summary CSV', 'suffix' => 'procurement-summary.csv', 'body' => "office,doc_type,reference_id,generated_at\nMULTI-OFFICE,PROCUREMENT_SUMMARY,{$ppmp->ppmp_id},".now()->toIso8601String()."\n"],
                    ['title' => 'Procurement Certification', 'suffix' => 'procurement-certification.txt', 'body' => "Certification\nSource record: PPMP #{$ppmp->ppmp_id}\nStatus: APPROVED\n"],
                ],
            ],
            [
                'title' => 'Office Supply Distribution Summary',
                'type' => 'DISTRIBUTION_SUMMARY',
                'reference_type' => DistributionRecord::class,
                'reference_id' => (int) $distribution->distribution_record_id,
                'description' => 'Completed office supply distribution summary for public visibility.',
                'remarks' => 'Published after delivery verification completion.',
                'attachments' => [
                    ['title' => 'Distribution Register', 'suffix' => 'distribution-register.csv', 'body' => "distribution_record_id,status,generated_at\n{$distribution->distribution_record_id},{$distribution->status},".now()->toIso8601String()."\n"],
                ],
            ],
        ];

        if ($secondaryPpmp instanceof Ppmp) {
            $publishedDefinitions[] = [
                'title' => 'Quarterly Procurement Brief',
                'type' => 'PROCUREMENT_SUMMARY',
                'reference_type' => Ppmp::class,
                'reference_id' => (int) $secondaryPpmp->ppmp_id,
                'description' => 'Quarterly procurement summary for a different approved office plan.',
                'remarks' => 'Published for public-facing procurement transparency.',
                'attachments' => [
                    ['title' => 'Q1 Procurement Breakdown', 'suffix' => 'q1-procurement-breakdown.txt', 'body' => "Quarterly Breakdown\nPPMP: {$secondaryPpmp->ppmp_id}\nPublished: ".now()->toDateString()."\n"],
                ],
            ];
        }

        if ($primaryDistribution instanceof DistributionRecord) {
            $publishedDefinitions[] = [
                'title' => 'Distribution Release Brief',
                'type' => 'DISTRIBUTION_SUMMARY',
                'reference_type' => DistributionRecord::class,
                'reference_id' => (int) $primaryDistribution->distribution_record_id,
                'description' => 'Published office distribution brief for public transparency.',
                'remarks' => 'Published from a real received distribution record.',
                'attachments' => [
                    ['title' => 'Release Acknowledgement', 'suffix' => 'distribution-release-ack.txt', 'body' => "Distribution Acknowledgement\nDistribution: {$primaryDistribution->distribution_record_id}\nStatus: {$primaryDistribution->status}\n"],
                ],
            ];
        }

        foreach ($publishedDefinitions as $definition) {
            $reportPath = $this->writeDisclosureMainFile(
                title: (string) $definition['title'],
                type: (string) $definition['type'],
                referenceType: (string) $definition['reference_type'],
                referenceId: (int) $definition['reference_id'],
            );

            $disclosure = $this->ensurePublishedDisclosure(
                title: (string) $definition['title'],
                actor: $executive,
                payload: [
                    'title' => (string) $definition['title'],
                    'type' => (string) $definition['type'],
                    'reference_type' => (string) $definition['reference_type'],
                    'reference_id' => (int) $definition['reference_id'],
                    'description' => (string) $definition['description'],
                    'file_path' => $reportPath,
                    'status' => 'PUBLISHED',
                    'remarks' => (string) $definition['remarks'],
                ],
                service: $service,
            );

            $this->syncDisclosureAttachments(
                disclosure: $disclosure,
                actor: $executive,
                attachments: $definition['attachments'] ?? [],
            );

            $packageService->ensureOfficialPackageAttachment($disclosure, $executive);
        }

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

        if ($tertiaryPpmp instanceof Ppmp) {
            $this->ensureDraftDisclosure(
                title: 'Pending Budget Transparency Note',
                actor: $executive,
                payload: [
                    'title' => 'Pending Budget Transparency Note',
                    'type' => 'PROCUREMENT_SUMMARY',
                    'reference_type' => Ppmp::class,
                    'reference_id' => (int) $tertiaryPpmp->ppmp_id,
                    'description' => 'Draft transparency note retained for executive review.',
                    'file_path' => null,
                    'status' => 'DRAFT',
                ],
                service: $service,
            );
        }

        if ($secondaryPurchaseOrder instanceof PurchaseOrder) {
            $this->ensureDraftDisclosure(
                title: 'Pending Procurement Notice',
                actor: $executive,
                payload: [
                    'title' => 'Pending Procurement Notice',
                    'type' => 'PROCUREMENT_SUMMARY',
                    'reference_type' => PurchaseOrder::class,
                    'reference_id' => (int) $secondaryPurchaseOrder->purchase_order_id,
                    'description' => 'Draft procurement notice waiting on final executive signoff.',
                    'file_path' => null,
                    'status' => 'DRAFT',
                ],
                service: $service,
            );
        }

        $linkedPurchaseRequest = PurchaseRequest::query()
            ->whereKey((int) $purchaseOrder->purchase_request_id)
            ->first();

        if ($linkedPurchaseRequest instanceof PurchaseRequest) {
            $this->command?->info(sprintf(
                'ExecutiveDisclosureDemoSeeder linked public disclosure package to real PR-%d and %s.',
                (int) $linkedPurchaseRequest->purchase_request_id,
                (string) ($purchaseOrder->po_number ?: 'PO-'.$purchaseOrder->purchase_order_id),
            ));
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function ensurePublishedDisclosure(string $title, User $actor, array $payload, PublicDisclosureService $service): PublicDisclosure
    {
        $existing = PublicDisclosure::query()->where('title', $title)->latest('disclosure_id')->first();

        if (! $existing instanceof PublicDisclosure) {
            $created = $service->createFromPayload($actor, $payload);

            return $created->fresh() ?? $created;
        }

        if ((string) $existing->status === 'DRAFT') {
            $service->updateDraft($existing, $actor, $payload);
            $published = $service->publish($existing->fresh() ?? $existing, $actor, $payload['remarks'] ?? null);

            return $published->fresh() ?? $published;
        }

        $existing->forceFill([
            'type' => strtoupper((string) ($payload['type'] ?? $existing->type)),
            'reference_type' => $payload['reference_type'] ?? $existing->reference_type,
            'reference_id' => $payload['reference_id'] ?? $existing->reference_id,
            'description' => $payload['description'] ?? $existing->description,
            'file_path' => $payload['file_path'] ?? $existing->file_path,
            'status' => 'PUBLISHED',
            'published_by' => (int) ($existing->published_by ?? $actor->id),
            'published_at' => $existing->published_at ?? now(),
        ])->save();

        return $existing->fresh() ?? $existing;
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

    private function writeDisclosureMainFile(string $title, string $type, string $referenceType, int $referenceId): string
    {
        $slug = strtolower(str_replace(' ', '-', trim($title)));
        $path = sprintf('reports/public_disclosures/%s.txt', $slug);

        Storage::disk($this->publicDisk())->put(
            $path,
            implode("\n", [
                'GovSupply Connect Public Disclosure Report',
                'Title: '.$title,
                'Type: '.$type,
                'Reference Type: '.$referenceType,
                'Reference ID: '.$referenceId,
                'Generated At: '.Carbon::now()->toIso8601String(),
            ])."\n"
        );

        return $path;
    }

    /**
     * @param  array<int, array{title: string, suffix: string, body: string}>  $attachments
     */
    private function syncDisclosureAttachments(PublicDisclosure $disclosure, User $actor, array $attachments): void
    {
        foreach ($attachments as $attachment) {
            $path = sprintf(
                'documents/public_disclosures/%d/%s',
                (int) $disclosure->disclosure_id,
                (string) $attachment['suffix'],
            );

            Storage::disk($this->publicDisk())->put($path, (string) $attachment['body']);

            Document::query()->updateOrCreate(
                [
                    'related_module' => 'public_disclosures',
                    'related_reference_id' => (int) $disclosure->disclosure_id,
                    'title' => (string) $attachment['title'],
                ],
                [
                    'uploaded_by' => (int) $actor->id,
                    'file_name' => basename($path),
                    'document_kind' => 'PUBLIC_DISCLOSURE_ATTACHMENT',
                    'file_path' => $path,
                    'uploaded_at' => now(),
                ],
            );
        }
    }
}
