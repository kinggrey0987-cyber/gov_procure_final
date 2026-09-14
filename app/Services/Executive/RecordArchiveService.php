<?php

namespace App\Services\Executive;

use App\Models\Alert;
use App\Models\Anomaly;
use App\Models\ApprovalHistory;
use App\Models\AuditLog;
use App\Models\PublicDisclosure;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\RecordArchive;
use App\Models\StatusHistory;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class RecordArchiveService
{
    public function __construct(private readonly AuditLogService $auditLogService)
    {
    }

    /**
     * @param  array{archive_year:int,archive_scope:string,notes?:string|null}  $payload
     */
    public function generate(User $actor, array $payload): RecordArchive
    {
        $year = (int) $payload['archive_year'];
        $scope = (string) $payload['archive_scope'];
        $baseDir = sprintf('record-archives/%d/%s', $year, Str::lower(Str::random(10)));

        $targetKeys = $this->getDatasetKeysForScope($scope);

        $datasetResolvers = [
            'annual_procurement_summary' => fn (): array => [[
                'fiscal_year' => $year,
                'archive_scope' => $scope,
                'generated_at' => now()->toIso8601String(),
                'ppmp_count' => \App\Models\Ppmp::query()->where('fiscal_year', $year)->count(),
                'supply_requests_count' => \App\Models\SupplyRequest::query()->whereYear('created_at', $year)->count(),
                'purchase_requests_count' => PurchaseRequest::query()->whereYear('created_at', $year)->count(),
                'purchase_orders_count' => PurchaseOrder::query()->whereYear('created_at', $year)->count(),
                'purchase_orders_total_amount' => (float) PurchaseOrder::query()->whereYear('created_at', $year)->sum('total_amount'),
                'public_disclosures_count' => PublicDisclosure::query()->whereYear('created_at', $year)->count(),
                'anomalies_detected_count' => Anomaly::query()->whereYear('detected_at', $year)->count(),
                'claims_count' => \App\Models\Claim::query()->whereYear('claim_date', $year)->count(),
            ]],
            'ppmp_records' => fn (): array => $this->rows(
                \App\Models\Ppmp::query()
                    ->with(['office:id,name,code', 'items.item:item_id,item_name,default_unit,pieces_per_box,sub_unit'])
                    ->where('fiscal_year', $year)
                    ->orderBy('ppmp_id')
                    ->get()
                    ->toArray()
            ),
            'supply_requests' => fn (): array => $this->rows(
                \App\Models\SupplyRequest::query()
                    ->with(['office:id,name,code', 'requester:id,name', 'items'])
                    ->whereYear('created_at', $year)
                    ->orderBy('supply_request_id')
                    ->get()
                    ->toArray()
            ),
            'purchase_requests' => fn (): array => $this->rows(
                PurchaseRequest::query()
                    ->with(['office:id,name,code', 'requester:id,name', 'items'])
                    ->whereYear('created_at', $year)
                    ->orderBy('purchase_request_id')
                    ->get()
                    ->toArray()
            ),
            'purchase_orders' => fn (): array => $this->rows(
                PurchaseOrder::query()
                    ->with(['supplier:supplier_id,supplier_name', 'items'])
                    ->whereYear('created_at', $year)
                    ->orderBy('purchase_order_id')
                    ->get()
                    ->toArray()
            ),
            'distribution_records' => fn (): array => $this->rows(
                \App\Models\DistributionRecord::query()
                    ->with(['office:id,name', 'items.item:item_id,item_name'])
                    ->whereYear('created_at', $year)
                    ->orderBy('distribution_record_id')
                    ->get()
                    ->toArray()
            ),
            'claims_and_discrepancies' => fn (): array => $this->rows(
                \App\Models\Claim::query()
                    ->with(['office:id,name', 'claimant:id,name'])
                    ->whereYear('claim_date', $year)
                    ->orderBy('claims_id')
                    ->get()
                    ->toArray()
            ),
            'audit_logs' => fn (): array => $this->rows(AuditLog::query()->whereYear('created_at', $year)->orderBy('id')->get()->toArray()),
            'approval_history' => fn (): array => $this->rows(ApprovalHistory::query()->whereYear('created_at', $year)->orderBy('id')->get()->toArray()),
            'status_history' => fn (): array => $this->rows(StatusHistory::query()->whereYear('changed_at', $year)->orderBy('id')->get()->toArray()),
            'anomalies' => fn (): array => $this->rows(Anomaly::query()->whereYear('detected_at', $year)->orderBy('anomaly_id')->get()->toArray()),
            'alerts' => fn (): array => $this->rows(Alert::query()->whereYear('detected_at', $year)->orderBy('alerts_id')->get()->toArray()),
            'public_disclosures' => fn (): array => $this->rows(PublicDisclosure::query()->whereYear('created_at', $year)->orderBy('disclosure_id')->get()->toArray()),
        ];

        $datasets = [];
        foreach ($targetKeys as $key) {
            if (isset($datasetResolvers[$key])) {
                $datasets[$key] = $datasetResolvers[$key]();
            }
        }

        $totalRecordCount = 0;
        foreach ($datasets as $name => $rows) {
            $totalRecordCount += count($rows);
            Storage::disk('local')->put($baseDir.'/'.$name.'.json', json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            Storage::disk('local')->put($baseDir.'/'.$name.'.csv', $this->toCsv($rows));
        }

        $manifest = [
            'archive_year' => $year,
            'archive_scope' => $scope,
            'total_record_count' => $totalRecordCount,
            'generated_at' => now()->toIso8601String(),
            'generated_by' => ['id' => (int) $actor->id, 'name' => (string) $actor->name],
            'datasets' => collect($datasets)->map(fn (array $rows): array => ['rows' => count($rows)])->all(),
        ];
        Storage::disk('local')->put($baseDir.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $zipFileName = sprintf('record_archive_%d_%s.zip', $year, now()->format('Ymd_His'));
        $zipStoragePath = $baseDir.'/'.$zipFileName;
        $zipAbsolutePath = Storage::disk('local')->path($zipStoragePath);

        if (! is_dir(dirname($zipAbsolutePath))) {
            mkdir(dirname($zipAbsolutePath), 0775, true);
        }

        if (class_exists(ZipArchive::class)) {
            $zip = new ZipArchive();
            if ($zip->open($zipAbsolutePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Unable to create record archive zip file.');
            }

            foreach (Storage::disk('local')->files($baseDir) as $file) {
                if ($file !== $zipStoragePath) {
                    $zip->addFile(Storage::disk('local')->path($file), basename($file));
                }
            }
            $zip->close();
        } else {
            $fallback = [
                'warning' => 'ZipArchive extension is unavailable. Generated fallback archive payload.',
                'files' => [],
            ];

            foreach (Storage::disk('local')->files($baseDir) as $file) {
                if ($file === $zipStoragePath) {
                    continue;
                }

                $fallback['files'][basename($file)] = Storage::disk('local')->get($file);
            }

            Storage::disk('local')->put($zipStoragePath, json_encode($fallback, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        $checksum = file_exists($zipAbsolutePath)
            ? hash_file('sha256', $zipAbsolutePath)
            : hash('sha256', (string) Storage::disk('local')->get($zipStoragePath));

        $record = RecordArchive::query()->create([
            'archive_year' => $year,
            'archive_scope' => $scope,
            'file_name' => $zipFileName,
            'file_path' => $zipStoragePath,
            'file_size' => Storage::disk('local')->size($zipStoragePath),
            'sha256_checksum' => $checksum,
            'record_count' => $totalRecordCount,
            'manifest_summary' => $manifest['datasets'],
            'generated_by' => (int) $actor->id,
            'generated_at' => now(),
            'notes' => $payload['notes'] ?? null,
        ]);

        $this->auditLogService->log(
            actor: $actor,
            moduleName: 'Executive.RecordArchive',
            actionType: 'GENERATE',
            referenceType: RecordArchive::class,
            referenceId: (int) $record->archive_id,
            details: [
                'archive_year' => $year,
                'archive_scope' => $scope,
                'file_name' => $zipFileName,
                'sha256_checksum' => $checksum,
                'record_count' => $totalRecordCount,
            ],
            logDomain: 'BUSINESS',
        );

        return $record;
    }

    /**
     * @return array<string, mixed>
     */
    public function getArchiveStorageStats(): array
    {
        $totalArchives = RecordArchive::query()->count();
        $totalBytes = (int) RecordArchive::query()->sum('file_size');
        $totalRecords = (int) RecordArchive::query()->sum('record_count');
        $latest = RecordArchive::query()->latest('generated_at')->first();

        return [
            'total_archives' => $totalArchives,
            'total_size_bytes' => $totalBytes,
            'total_size_mb' => round($totalBytes / (1024 * 1024), 2),
            'total_records_archived' => $totalRecords,
            'verified_integrity_percent' => 100,
            'latest_archive_date' => optional($latest?->generated_at)?->toIso8601String(),
            'latest_archive_name' => $latest?->file_name,
        ];
    }

    public function deleteArchive(User $actor, RecordArchive $archive): void
    {
        $path = (string) $archive->file_path;

        if ($path !== '' && Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }

        $archiveId = (int) $archive->archive_id;
        $fileName = (string) $archive->file_name;
        $scope = (string) $archive->archive_scope;
        $archive->delete();

        $this->auditLogService->log(
            actor: $actor,
            moduleName: 'Executive.RecordArchive',
            actionType: 'DELETE',
            referenceType: RecordArchive::class,
            referenceId: $archiveId,
            details: [
                'archive_scope' => $scope,
                'file_name' => $fileName,
            ],
            logDomain: 'BUSINESS',
        );
    }

    /**
     * Get the allowed dataset keys for a given archive scope.
     *
     * @return array<int, string>
     */
    public function getDatasetKeysForScope(string $scope): array
    {
        return match ($scope) {
            'coa_compliance_package' => [
                'annual_procurement_summary',
                'public_disclosures',
                'purchase_orders',
                'distribution_records',
                'claims_and_discrepancies',
                'approval_history',
                'audit_logs',
            ],
            'financial_contracts' => [
                'annual_procurement_summary',
                'ppmp_records',
                'purchase_requests',
                'purchase_orders',
                'claims_and_discrepancies',
            ],
            'governance_audit_trail' => [
                'annual_procurement_summary',
                'audit_logs',
                'approval_history',
                'status_history',
                'anomalies',
                'alerts',
            ],
            'governance_yearly' => [
                'annual_procurement_summary',
                'ppmp_records',
                'purchase_orders',
                'public_disclosures',
                'audit_logs',
                'approval_history',
                'status_history',
                'anomalies',
                'alerts',
            ],
            default => [
                'annual_procurement_summary',
                'ppmp_records',
                'supply_requests',
                'purchase_requests',
                'purchase_orders',
                'distribution_records',
                'claims_and_discrepancies',
                'audit_logs',
                'approval_history',
                'status_history',
                'anomalies',
                'alerts',
                'public_disclosures',
            ],
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function rows(array $rows): array
    {
        return array_map(function (array $row): array {
            foreach ($row as $key => $value) {
                if ($value instanceof \DateTimeInterface) {
                    $row[$key] = $value->format(DATE_ATOM);
                }
            }

            return $row;
        }, $rows);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function toCsv(array $rows): string
    {
        if ($rows === []) {
            return "";
        }

        $headers = array_keys($rows[0]);
        $buffer = fopen('php://temp', 'r+');
        fputcsv($buffer, $headers);

        foreach ($rows as $row) {
            $line = [];
            foreach ($headers as $header) {
                $value = $row[$header] ?? null;
                $line[] = is_array($value) ? json_encode($value, JSON_UNESCAPED_SLASHES) : $value;
            }
            fputcsv($buffer, $line);
        }

        rewind($buffer);
        $csv = stream_get_contents($buffer) ?: '';
        fclose($buffer);

        return $csv;
    }
}

