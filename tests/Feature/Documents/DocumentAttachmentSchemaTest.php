<?php

namespace Tests\Feature\Documents;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DocumentAttachmentSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_documents_table_contains_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('documents'));

        $this->assertTrue(Schema::hasColumns('documents', [
            'document_id',
            'uploaded_by',
            'title',
            'file_name',
            'file_path',
            'related_module',
            'related_reference_id',
            'uploaded_at',
            'created_at',
            'updated_at',
        ]));
    }
}
