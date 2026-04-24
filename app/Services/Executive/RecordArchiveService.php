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

        $datasets = [
            'audit_logs' => $this->rows(AuditLog::query()->whereYear('created_at', $year)->orderBy('id')->get()->toArray()),
            'governance_activity_evidence' => $this->rows(
                AuditLog::query()
                    ->business()
                    ->with(['user:id,name', 'office:id,name'])
                    ->whereYear('created_at', $year)
                    ->latest('id')
                    ->get()
                    ->toArray()
            ),
            'approval_history' => $this->rows(ApprovalHistory::query()->whereYear('created_at', $year)->orderBy('id')->get()->toArray()),
            'status_history' => $this->rows(StatusHistory::query()->whereYear('changed_at', $year)->orderBy('id')->get()->toArray()),
            'anomalies' => $this->rows(Anomaly::query()->whereYear('detected_at', $year)->orderBy('anomaly_id')->get()->toArray()),
            'alerts' => $this->rows(Alert::query()->whereYear('detected_at', $year)->orderBy('alerts_id')->get()->toArray()),
            'public_disclosures' => $this->rows(PublicDisclosure::query()->whereYear('created_at', $year)->orderBy('disclosure_id')->get()->toArray()),
            'procurement_insights_latest_records' => $this->rows(
                PurchaseOrder::query()
                    ->with(['purchaseRequest.office:id,name'])
                    ->whereYear('created_at', $year)
                    ->latest('purchase_order_id')
                    ->limit(100)
                    ->get()
                    ->toArray()
            ),
            'procurement_insights_summary' => [[
                'year' => $year,
                'purchase_request_count' => PurchaseRequest::query()->whereYear('created_at', $year)->count(),
                'purchase_order_count' => PurchaseOrder::query()->whereYear('created_at', $year)->count(),
                'purchase_order_total_amount' => (float) PurchaseOrder::query()->whereYear('created_at', $year)->sum('total_amount'),
            ]],
        ];

        foreach ($datasets as $name => $rows) {
            Storage::disk('local')->put($baseDir.'/'.$name.'.json', json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            Storage::disk('local')->put($baseDir.'/'.$name.'.csv', $this->toCsv($rows));
        }

        $manifest = [
            'archive_year' => $year,
            'archive_scope' => $scope,
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

        $record = RecordArchive::query()->create([
            'archive_year' => $year,
            'archive_scope' => $scope,
            'file_name' => $zipFileName,
            'file_path' => $zipStoragePath,
            'file_size' => Storage::disk('local')->size($zipStoragePath),
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
            details: ['archive_year' => $year, 'archive_scope' => $scope, 'file_name' => $zipFileName],
            logDomain: 'BUSINESS',
        );

        return $record;
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

