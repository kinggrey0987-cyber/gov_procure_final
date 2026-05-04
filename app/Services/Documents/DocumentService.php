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

    /**
     * @param  array<string, mixed>  $payload
     */
    public function upload(User $actor, string $relatedModule, int $relatedReferenceId, array $payload): Document
    {
        $module = $this->accessService->normalizeModule($relatedModule);
        $parent = $this->accessService->resolveParentOrFail($module, $relatedReferenceId);

        if (! $this->accessService->canManage($actor, $module, $parent)) {
            throw ValidationException::withMessages([
                'authorization' => 'You are not authorized to upload attachments for this record.',
            ]);
        }

        /** @var UploadedFile $file */
        $file = $payload['file'];
        $storedFileName = Str::uuid()->toString().'_'.$file->getClientOriginalName();
        $filePath = $file->storeAs('documents/'.$module.'/'.$relatedReferenceId, $storedFileName, $this->publicDisk());

        $document = DB::transaction(function () use ($actor, $module, $relatedReferenceId, $payload, $file, $filePath): Document {
            $document = Document::query()->create([
                'uploaded_by' => (int) $actor->id,
                'title' => (string) $payload['title'],
                'file_name' => $file->getClientOriginalName(),
                'document_kind' => isset($payload['document_kind']) ? (string) $payload['document_kind'] : null,
                'file_path' => $filePath,
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
                ],
                logDomain: 'BUSINESS',
                officeId: $actor->office_id,
            );

            return $document;
        });

        return $document->fresh(['uploader']) ?? $document;
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
        Storage::disk($this->publicDisk())->put($filePath, $content);

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

        DB::transaction(function () use ($actor, $document): void {
            Storage::disk($this->publicDisk())->delete((string) $document->file_path);

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

            Document::query()->whereKey((int) $document->document_id)->delete();
        });
    }
}
