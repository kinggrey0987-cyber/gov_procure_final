<?php

namespace App\Services\Documents;

use App\Models\Document;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DocumentService
{
    private function publicDisk(): string
    {
        return (string) config('filesystems.public_disk', 'public');
    }

    public function __construct(
        private readonly DocumentAccessService $accessService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    private function isImage(UploadedFile $file): bool
    {
        $mime = strtolower((string) ($file->getMimeType() ?: ''));
        $ext = strtolower($file->getClientOriginalExtension());
        $imageMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/bmp'];
        $imageExts = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp'];

        return in_array($mime, $imageMimes, true) || in_array($ext, $imageExts, true);
    }

    /**
     * @return array{file_path: string, file_name: string, converted: bool}
     */
    private function convertImageToPdf(
        UploadedFile $file,
        string $title,
        ?string $documentKind,
        string $module,
        int $relatedReferenceId,
        ?string $referenceLabel = null
    ): array {
        $imagePath = $file->getRealPath();
        $imageInfo = @getimagesize($imagePath);
        $width = $imageInfo ? $imageInfo[0] : 800;
        $height = $imageInfo ? $imageInfo[1] : 1100;
        $orientation = $width > $height ? 'landscape' : 'portrait';
        $maxImgHeight = $width > $height ? '480px' : '680px';
        $imageMime = $imageInfo['mime'] ?? $file->getMimeType() ?? 'image/jpeg';
        $base64Image = 'data:'.$imageMime.';base64,'.base64_encode(file_get_contents($imagePath));

        $originalName = $file->getClientOriginalName();
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        $pdfFileName = $baseName.'.pdf';
        $dateStr = now()->format('F d, Y h:i A');

        $kindLabel = match (strtolower((string) $documentKind)) {
            'noa' => 'Notice of Award (NOA)',
            'canvass_sheet' => 'Abstract of Canvass / Canvass Sheet',
            'supplier_quotation' => 'Winning / Bidder Quotation',
            'signed_po' => 'Signed PO with Conforme',
            'procurement_record' => 'BAC Resolution / Procurement Record',
            'delivery_receipt' => 'Official Delivery Receipt (DR)',
            'sales_invoice' => 'Supplier Sales Invoice',
            'verification_evidence' => 'Inspection & Verification Evidence',
            default => $documentKind ?: 'Supporting Attachment',
        };

        $refText = $referenceLabel ?: (strtoupper(str_replace('_', ' ', $module)).' #'.$relatedReferenceId);
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $safeOriginalName = htmlspecialchars($originalName, ENT_QUOTES, 'UTF-8');
        $safeRefText = htmlspecialchars($refText, ENT_QUOTES, 'UTF-8');
        $safeKindLabel = htmlspecialchars($kindLabel, ENT_QUOTES, 'UTF-8');

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        @page { margin: 20px 25px; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; color: #1e293b; margin: 0; padding: 0; }
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; border-bottom: 2px solid #b91c1c; padding-bottom: 8px; }
        .republic { font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: #64748b; }
        .title { font-size: 15px; font-weight: bold; color: #991b1b; margin-top: 2px; }
        .subtitle { font-size: 9px; color: #475569; margin-top: 2px; }
        .badge { background-color: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; padding: 4px 8px; font-size: 9px; font-weight: bold; border-radius: 4px; text-align: center; }
        .meta-box { width: 100%; border-collapse: collapse; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; margin-bottom: 12px; font-size: 10px; }
        .meta-box td { padding: 4px 8px; border: 1px solid #e2e8f0; }
        .meta-label { font-weight: bold; color: #475569; width: 22%; background-color: #f1f5f9; }
        .meta-val { color: #0f172a; }
        .image-container { text-align: center; margin-top: 8px; }
        .image-container img { max-width: 95%; max-height: {$maxImgHeight}; border: 1px solid #cbd5e1; border-radius: 4px; }
        .footer-note { font-size: 8px; color: #94a3b8; text-align: center; margin-top: 12px; border-top: 1px solid #e2e8f0; padding-top: 6px; }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td>
                <div class="republic">Republic of the Philippines &bull; Municipal Procurement System</div>
                <div class="title">Official Document Registry Archive</div>
                <div class="subtitle">Certified Procurement Attachment Pursuant to RA 9184 & COA Auditing Archival Guidelines</div>
            </td>
            <td style="text-align: right; vertical-align: middle; width: 150px;">
                <div class="badge">&check; CONVERTED TO PDF</div>
            </td>
        </tr>
    </table>

    <table class="meta-box">
        <tr>
            <td class="meta-label">Document Title</td>
            <td class="meta-val">{$safeTitle}</td>
            <td class="meta-label">Document Type</td>
            <td class="meta-val">{$safeKindLabel}</td>
        </tr>
        <tr>
            <td class="meta-label">Procurement Reference</td>
            <td class="meta-val">{$safeRefText}</td>
            <td class="meta-label">Date Archived</td>
            <td class="meta-val">{$dateStr}</td>
        </tr>
        <tr>
            <td class="meta-label">Original Scanned File</td>
            <td class="meta-val" colspan="3">{$safeOriginalName} (Converted from submitted image scan into archival PDF)</td>
        </tr>
    </table>

    <div class="image-container">
        <img src="{$base64Image}" />
    </div>

    <div class="footer-note">
        GovSupply Connect Transparency System &bull; Legal Archival Document &bull; This electronic record has been rendered from the submitted scan for archival durability and compliance.
    </div>
</body>
</html>
HTML;

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('a4', $orientation);
        $pdfContent = $pdf->output();

        $storedFileName = Str::uuid()->toString().'_'.$pdfFileName;
        $directory = 'documents/'.$module.'/'.$relatedReferenceId;
        $filePath = $directory.'/'.$storedFileName;
        $this->storeContentSafely($filePath, $pdfContent);

        return [
            'file_path' => $filePath,
            'file_name' => $pdfFileName,
            'converted' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function upload(User $actor, string $relatedModule, int $relatedReferenceId, array $payload): Document
    {
        $docs = $this->uploadMultiple($actor, $relatedModule, $relatedReferenceId, $payload);

        return $docs->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return Collection<int, Document>
     */
    public function uploadMultiple(User $actor, string $relatedModule, int $relatedReferenceId, array $payload): Collection
    {
        $module = $this->accessService->normalizeModule($relatedModule);
        $parent = $this->accessService->resolveParentOrFail($module, $relatedReferenceId);

        if (! $this->accessService->canManage($actor, $module, $parent)) {
            throw ValidationException::withMessages([
                'authorization' => 'You are not authorized to upload attachments for this record.',
            ]);
        }

        // Resolve files from payload: either 'files' array or single 'file'
        $rawFiles = $payload['files'] ?? [];
        if (! is_array($rawFiles) || empty($rawFiles)) {
            if (isset($payload['file']) && $payload['file'] instanceof UploadedFile) {
                $rawFiles = [$payload['file']];
            }
        }

        if (empty($rawFiles)) {
            throw ValidationException::withMessages([
                'file' => 'Please select at least one file to upload.',
            ]);
        }

        $titles = $payload['titles'] ?? [];
        $kinds = $payload['document_kinds'] ?? [];
        $defaultTitle = trim((string) ($payload['title'] ?? ''));
        $defaultKind = isset($payload['document_kind']) ? (string) $payload['document_kind'] : null;

        $referenceLabel = null;
        if ($parent instanceof \App\Models\PurchaseOrder) {
            $referenceLabel = ($parent->po_number ?: ('PO-'.str_pad((string) $parent->purchase_order_id, 4, '0', STR_PAD_LEFT)));
        }

        $createdDocuments = new Collection();

        foreach (array_values($rawFiles) as $index => $file) {
            if (! ($file instanceof UploadedFile)) {
                continue;
            }

            $fileTitle = ! empty($titles[$index]) ? trim((string) $titles[$index]) : $defaultTitle;
            if ($fileTitle === '') {
                $fileTitle = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            }
            $fileKind = ! empty($kinds[$index]) ? (string) $kinds[$index] : $defaultKind;

            if ($this->isImage($file)) {
                $processed = $this->convertImageToPdf(
                    $file,
                    $fileTitle,
                    $fileKind,
                    $module,
                    $relatedReferenceId,
                    $referenceLabel
                );
            } else {
                $storedFileName = Str::uuid()->toString().'_'.$file->getClientOriginalName();
                $directory = 'documents/'.$module.'/'.$relatedReferenceId;
                $filePath = $this->storeUploadedFileSafely($file, $directory, $storedFileName);
                $processed = [
                    'file_path' => $filePath,
                    'file_name' => $file->getClientOriginalName(),
                    'converted' => false,
                ];
            }

            $document = DB::transaction(function () use ($actor, $module, $relatedReferenceId, $fileTitle, $fileKind, $processed): Document {
                $document = Document::query()->create([
                    'uploaded_by' => (int) $actor->id,
                    'title' => $fileTitle,
                    'file_name' => $processed['file_name'],
                    'document_kind' => $fileKind,
                    'file_path' => $processed['file_path'],
                    'related_module' => $module,
                    'related_reference_id' => $relatedReferenceId,
                    'uploaded_at' => now(),
                ]);

                $this->auditLogService->log(
                    actor: $actor,
                    moduleName: 'Documents.Attachment',
                    actionType: 'UPLOAD',
                    referenceType: Document::class,
                    referenceId: (int) $document->document_id,
                    details: [
                        'related_module' => $module,
                        'related_reference_id' => $relatedReferenceId,
                        'file_name' => $document->file_name,
                        'converted_to_pdf' => $processed['converted'],
                    ],
                    logDomain: 'BUSINESS',
                    officeId: $actor->office_id,
                );

                return $document;
            });

            $createdDocuments->push($document->fresh(['uploader']) ?? $document);
        }

        return $createdDocuments;
    }

    public function createGeneratedAttachment(
        User $actor,
        string $relatedModule,
        int $relatedReferenceId,
        string $title,
        string $fileName,
        string $content,
        string $mimeExtension = 'txt',
    ): Document {
        $module = $this->accessService->normalizeModule($relatedModule);
        $parent = $this->accessService->resolveParentOrFail($module, $relatedReferenceId);

        if (! $this->accessService->canManage($actor, $module, $parent)) {
            throw ValidationException::withMessages([
                'authorization' => 'You are not authorized to create attachments for this record.',
            ]);
        }

        $safeName = trim($fileName) !== '' ? $fileName : 'generated-attachment';
        $storedFileName = Str::uuid()->toString().'_'.$safeName.'.'.$mimeExtension;
        $filePath = 'documents/'.$module.'/'.$relatedReferenceId.'/'.$storedFileName;
        $this->storeContentSafely($filePath, $content);

        $document = DB::transaction(function () use ($actor, $module, $relatedReferenceId, $title, $safeName, $mimeExtension, $filePath): Document {
            $document = Document::query()->create([
                'uploaded_by' => (int) $actor->id,
                'title' => $title,
                'file_name' => $safeName.'.'.$mimeExtension,
                'document_kind' => null,
                'file_path' => $filePath,
                'related_module' => $module,
                'related_reference_id' => $relatedReferenceId,
                'uploaded_at' => now(),
            ]);

            $this->auditLogService->log(
                actor: $actor,
                moduleName: 'Documents.Attachment',
                actionType: 'GENERATE',
                referenceType: Document::class,
                referenceId: (int) $document->document_id,
                details: [
                    'related_module' => $module,
                    'related_reference_id' => $relatedReferenceId,
                    'file_name' => $document->file_name,
                    'generated' => true,
                ],
                logDomain: 'BUSINESS',
                officeId: $actor->office_id,
            );

            return $document;
        });

        return $document->fresh(['uploader']) ?? $document;
    }

    /**
     * @return Collection<int, Document>
     */
    public function listForRelated(?User $actor, string $relatedModule, int $relatedReferenceId): Collection
    {
        $module = $this->accessService->normalizeModule($relatedModule);
        $parent = $this->accessService->resolveParentOrFail($module, $relatedReferenceId);

        if (! $this->accessService->canView($actor, $module, $parent)) {
            throw ValidationException::withMessages([
                'authorization' => 'You are not authorized to view attachments for this record.',
            ]);
        }

        return Document::query()
            ->with('uploader')
            ->forReference($module, $relatedReferenceId)
            ->orderByDesc('uploaded_at')
            ->orderByDesc('document_id')
            ->get();
    }

    public function authorizeViewDocument(Document $document, ?User $actor): void
    {
        $parent = $this->accessService->resolveParentForDocument($document);

        if (! $this->accessService->canView($actor, (string) $document->related_module, $parent)) {
            throw ValidationException::withMessages([
                'authorization' => 'You are not authorized to access this document.',
            ]);
        }
    }

    public function delete(User $actor, Document $document): void
    {
        $parent = $this->accessService->resolveParentForDocument($document);

        if (! $this->accessService->canManage($actor, (string) $document->related_module, $parent)) {
            throw ValidationException::withMessages([
                'authorization' => 'You are not authorized to delete this document.',
            ]);
        }

        $path = (string) $document->file_path;
        foreach (array_unique([$this->publicDisk(), 'public', 'local']) as $diskName) {
            try {
                if (Storage::disk($diskName)->exists($path)) {
                    Storage::disk($diskName)->delete($path);
                }
            } catch (\Throwable) {
                // Ignore cloud disk failure on deletion
            }
        }

        DB::transaction(function () use ($actor, $document): void {
            $this->auditLogService->log(
                actor: $actor,
                moduleName: 'Documents.Attachment',
                actionType: 'DELETE',
                referenceType: Document::class,
                referenceId: (int) $document->document_id,
                details: [
                    'related_module' => $document->related_module,
                    'related_reference_id' => $document->related_reference_id,
                    'file_name' => $document->file_name,
                ],
                logDomain: 'BUSINESS',
                officeId: $actor->office_id,
            );

            DB::delete(
                'delete from documents where document_id = ?',
                [(int) $document->getKey()],
            );
        });
    }

    private function storeUploadedFileSafely(UploadedFile $file, string $directory, string $fileName): string
    {
        $primaryDisk = $this->publicDisk();
        try {
            $path = $file->storeAs($directory, $fileName, $primaryDisk);
            if (is_string($path) && trim($path) !== '') {
                return $path;
            }
        } catch (\Throwable) {
            // Fallback when cloud disk fails or lacks credentials
        }

        if ($primaryDisk !== 'public') {
            try {
                $path = $file->storeAs($directory, $fileName, 'public');
                if (is_string($path) && trim($path) !== '') {
                    return $path;
                }
            } catch (\Throwable) {
                // Fallback to local
            }
        }

        $path = $file->storeAs($directory, $fileName, 'local');
        if (is_string($path) && trim($path) !== '') {
            return $path;
        }

        throw ValidationException::withMessages([
            'file' => 'The attachment could not be saved. Check storage disk write permissions.',
        ]);
    }

    private function storeContentSafely(string $filePath, string $content): void
    {
        $primaryDisk = $this->publicDisk();
        try {
            Storage::disk($primaryDisk)->put($filePath, $content);
            return;
        } catch (\Throwable) {
            // Ignore cloud disk failure
        }

        if ($primaryDisk !== 'public') {
            try {
                Storage::disk('public')->put($filePath, $content);
                return;
            } catch (\Throwable) {
                // Ignore public disk failure
            }
        }

        Storage::disk('local')->put($filePath, $content);
    }
}
