<?php

namespace App\Services\Public;

use App\Models\Document;
use App\Models\PublicDisclosure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class PublicLandingService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        if (! Schema::hasTable('public_disclosures') || ! Schema::hasTable('documents')) {
            return $this->emptySummary();
        }

        $publishedDisclosuresQuery = PublicDisclosure::query()->published();

        $publishedDisclosureCount = (clone $publishedDisclosuresQuery)->count();
        $publishedTypeCount = (clone $publishedDisclosuresQuery)->distinct('type')->count('type');
        $currentMonthCount = (clone $publishedDisclosuresQuery)
            ->where('published_at', '>=', now()->startOfMonth())
            ->count();

        $publicReportCount = Document::query()
            ->where('related_module', 'public_disclosures')
            ->whereIn('related_reference_id', PublicDisclosure::query()->published()->select('disclosure_id'))
            ->count();

        $latestDisclosures = PublicDisclosure::query()
            ->published()
            ->withCount('documents as attachment_count')
            ->orderByDesc('published_at')
            ->limit(6)
            ->get(['disclosure_id', 'title', 'type', 'published_at'])
            ->map(fn (PublicDisclosure $disclosure): array => [
                'disclosure_id' => (int) $disclosure->disclosure_id,
                'title' => (string) $disclosure->title,
                'type' => (string) $disclosure->type,
                'published_at' => optional($disclosure->published_at)?->toIso8601String(),
                'attachment_count' => (int) ($disclosure->attachment_count ?? 0),
            ])->values();

        $monthlyDisclosures = $this->buildMonthlyDisclosureTrend();
        $typeBreakdown = $this->buildTypeBreakdown();

        $publicReports = Document::query()
            ->join('public_disclosures', 'public_disclosures.disclosure_id', '=', 'documents.related_reference_id')
            ->where('documents.related_module', 'public_disclosures')
            ->where('public_disclosures.status', 'PUBLISHED')
            ->whereNotNull('public_disclosures.published_at')
            ->orderByDesc('documents.uploaded_at')
            ->orderByDesc('documents.document_id')
            ->limit(8)
            ->get([
                'documents.document_id',
                'documents.title',
                'documents.file_name',
                'documents.uploaded_at',
                'public_disclosures.disclosure_id',
                'public_disclosures.title as disclosure_title',
            ])
            ->map(fn (Document $document): array => [
                'document_id' => (int) $document->document_id,
                'title' => (string) $document->title,
                'file_name' => (string) $document->file_name,
                'uploaded_at' => optional($document->uploaded_at)?->toIso8601String(),
                'disclosure_id' => (int) $document->disclosure_id,
                'disclosure_title' => (string) $document->disclosure_title,
            ])->values();

        return [
            'summaryStats' => [
                ['label' => 'Published Disclosures', 'value' => number_format($publishedDisclosureCount), 'accent' => 'red'],
                ['label' => 'Disclosure Types Published', 'value' => number_format($publishedTypeCount), 'accent' => 'green'],
                ['label' => 'Published This Month', 'value' => number_format($currentMonthCount), 'accent' => 'blue'],
                ['label' => 'Public Reports Available', 'value' => number_format($publicReportCount), 'accent' => 'amber'],
            ],
            'latestDisclosures' => $latestDisclosures,
            'monthlyDisclosures' => $monthlyDisclosures,
            'typeBreakdown' => $typeBreakdown,
            'publicReports' => $publicReports,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptySummary(): array
    {
        return [
            'summaryStats' => [
                ['label' => 'Published Disclosures', 'value' => '0', 'accent' => 'red'],
                ['label' => 'Disclosure Types Published', 'value' => '0', 'accent' => 'green'],
                ['label' => 'Published This Month', 'value' => '0', 'accent' => 'blue'],
                ['label' => 'Public Reports Available', 'value' => '0', 'accent' => 'amber'],
            ],
            'latestDisclosures' => collect(),
            'monthlyDisclosures' => $this->buildMonthlyDisclosureTrend(),
            'typeBreakdown' => collect(),
            'publicReports' => collect(),
        ];
    }

    /**
     * @return Collection<int, array{label: string, count: int}>
     */
    private function buildMonthlyDisclosureTrend(): Collection
    {
        $months = collect();

        for ($offset = 5; $offset >= 0; $offset--) {
            $month = now()->subMonths($offset)->startOfMonth();
            $months->put($month->format('Y-m'), [
                'label' => $month->format('M Y'),
                'count' => 0,
            ]);
        }

        if (! Schema::hasTable('public_disclosures')) {
            return $months->values();
        }

        $published = PublicDisclosure::query()
            ->published()
            ->where('published_at', '>=', now()->subMonths(5)->startOfMonth())
            ->get(['published_at']);

        foreach ($published as $record) {
            $publishedAt = $record->published_at;

            if (! $publishedAt instanceof Carbon) {
                continue;
            }

            $key = $publishedAt->format('Y-m');

            if (! $months->has($key)) {
                continue;
            }

            $month = $months->get($key);
            $month['count']++;
            $months->put($key, $month);
        }

        return $months->values();
    }

    /**
     * @return Collection<int, array{label: string, count: int}>
     */
    private function buildTypeBreakdown(): Collection
    {
        return PublicDisclosure::query()
            ->published()
            ->selectRaw('type, COUNT(*) as total')
            ->groupBy('type')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn (PublicDisclosure $disclosure): array => [
                'label' => (string) $disclosure->type,
                'count' => (int) ($disclosure->total ?? 0),
            ])
            ->values();
    }
}
