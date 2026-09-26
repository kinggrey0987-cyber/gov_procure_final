<?php

use App\Models\Delivery;
use App\Models\DistributionRecord;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            // 1. Distribution records: 3-digit sequence (DIST-YYYY-MM-XXX) -> 4-digit sequence (DIST-YYYY-MM-XXXX)
            DistributionRecord::query()
                ->where('distribution_code', 'like', 'DIST-%')
                ->orderBy('distribution_record_id')
                ->each(function (DistributionRecord $record): void {
                    if (preg_match('/^DIST-(\d{4})-(\d{2})-(\d{1,3})$/', (string) $record->distribution_code, $matches) === 1) {
                        $normalized = sprintf('DIST-%s-%s-%04d', $matches[1], $matches[2], (int) $matches[3]);
                        if ($normalized !== $record->distribution_code) {
                            $record->distribution_code = $normalized;
                            $record->saveQuietly();
                        }
                    }
                });

            // 2. Distribution records: legacy 5-digit year without month (DIST-YYYY-XXXXX) -> standard (DIST-YYYY-MM-XXXX)
            DistributionRecord::query()
                ->where('distribution_code', 'like', 'DIST-%')
                ->orderBy('distribution_record_id')
                ->each(function (DistributionRecord $record): void {
                    if (preg_match('/^DIST-(\d{4})-(\d{5})$/', (string) $record->distribution_code, $matches) === 1) {
                        $date = $record->release_date
                            ? Carbon::parse($record->release_date)
                            : ($record->created_at ? Carbon::parse($record->created_at) : now());

                        $prefix = sprintf('DIST-%s-', $date->format('Y-m'));

                        $codes = DistributionRecord::query()
                            ->where('distribution_code', 'like', $prefix.'%')
                            ->where('distribution_record_id', '!=', $record->distribution_record_id)
                            ->pluck('distribution_code');

                        $maxSeq = 0;
                        foreach ($codes as $code) {
                            if (preg_match('/^DIST-\d{4}-\d{2}-(\d+)$/', (string) $code, $m) === 1) {
                                $maxSeq = max($maxSeq, (int) $m[1]);
                            }
                        }

                        $seq = $maxSeq + 1;
                        do {
                            $candidate = sprintf('%s%04d', $prefix, $seq);
                            $seq++;
                        } while (DistributionRecord::query()
                            ->where('distribution_code', $candidate)
                            ->where('distribution_record_id', '!=', $record->distribution_record_id)
                            ->exists()
                        );

                        $record->distribution_code = $candidate;
                        $record->saveQuietly();
                    }
                });

            // 3. Deliveries: 3-digit sequence (DEL-YYYY-MM-XXX) -> 4-digit sequence (DEL-YYYY-MM-XXXX)
            Delivery::query()
                ->where('delivery_code', 'like', 'DEL-%')
                ->orderBy('delivery_id')
                ->each(function (Delivery $delivery): void {
                    if (preg_match('/^DEL-(\d{4})-(\d{2})-(\d{1,3})$/', (string) $delivery->delivery_code, $matches) === 1) {
                        $normalized = sprintf('DEL-%s-%s-%04d', $matches[1], $matches[2], (int) $matches[3]);
                        if ($normalized !== $delivery->delivery_code) {
                            $delivery->delivery_code = $normalized;
                            $delivery->saveQuietly();
                        }
                    }
                });

            // 4. Deliveries: legacy 5-digit year without month (DEL-YYYY-XXXXX) -> standard (DEL-YYYY-MM-XXXX)
            Delivery::query()
                ->where('delivery_code', 'like', 'DEL-%')
                ->orderBy('delivery_id')
                ->each(function (Delivery $delivery): void {
                    if (preg_match('/^DEL-(\d{4})-(\d{5})$/', (string) $delivery->delivery_code, $matches) === 1) {
                        $date = $delivery->actual_delivery_date
                            ? Carbon::parse($delivery->actual_delivery_date)
                            : ($delivery->created_at ? Carbon::parse($delivery->created_at) : now());

                        $prefix = sprintf('DEL-%s-', $date->format('Y-m'));

                        $codes = Delivery::query()
                            ->where('delivery_code', 'like', $prefix.'%')
                            ->where('delivery_id', '!=', $delivery->delivery_id)
                            ->pluck('delivery_code');

                        $maxSeq = 0;
                        foreach ($codes as $code) {
                            if (preg_match('/^DEL-\d{4}-\d{2}-(\d+)$/', (string) $code, $m) === 1) {
                                $maxSeq = max($maxSeq, (int) $m[1]);
                            }
                        }

                        $seq = $maxSeq + 1;
                        do {
                            $candidate = sprintf('%s%04d', $prefix, $seq);
                            $seq++;
                        } while (Delivery::query()
                            ->where('delivery_code', $candidate)
                            ->where('delivery_id', '!=', $delivery->delivery_id)
                            ->exists()
                        );

                        $delivery->delivery_code = $candidate;
                        $delivery->saveQuietly();
                    }
                });
        });
    }

    public function down(): void
    {
        // Data format normalization, non-reversible.
    }
};
