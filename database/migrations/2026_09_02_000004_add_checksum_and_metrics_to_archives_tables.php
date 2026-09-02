<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('record_archives', function (Blueprint $table) {
            $table->string('sha256_checksum', 64)->nullable()->after('file_size');
            $table->unsignedInteger('record_count')->default(0)->after('sha256_checksum');
            $table->json('manifest_summary')->nullable()->after('record_count');
        });

        Schema::table('system_backups', function (Blueprint $table) {
            $table->string('sha256_checksum', 64)->nullable()->after('file_size');
            $table->json('table_metrics')->nullable()->after('sha256_checksum');
        });
    }

    public function down(): void
    {
        Schema::table('record_archives', function (Blueprint $table) {
            $table->dropColumn(['sha256_checksum', 'record_count', 'manifest_summary']);
        });

        Schema::table('system_backups', function (Blueprint $table) {
            $table->dropColumn(['sha256_checksum', 'table_metrics']);
        });
    }
};
