<?php

declare(strict_types=1);

use App\Shared\Infrastructure\Database\DatabaseSchema;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DatabaseSchema::ensure(DatabaseSchema::CORE_FILES);

        Schema::create(DatabaseTable::FILE_OBJECTS, function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('disk');
            $table->string('path');
            $table->foreignId('canonical_file_object_id')->nullable();
            $table->foreignId('retention_source_file_object_id')->nullable();
            $table->boolean('physical_owner')->default(true);
            $table->string('original_name');
            $table->string('extension', 32);
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum_sha256', 64);
            $table->string('scan_state', 32)->default('pending');
            $table->timestampTz('scan_state_changed_at')->nullable();
            $table->unsignedInteger('scan_attempts')->default(0);
            $table->timestampTz('last_scan_queued_at')->nullable();
            $table->timestampTz('available_at')->nullable();
            $table->timestampTz('quarantined_at');
            $table->timestampTz('anonymized_at')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->string('retention_purpose')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->foreign('canonical_file_object_id')->references('id')->on(DatabaseTable::FILE_OBJECTS)->restrictOnDelete();
            $table->foreign('retention_source_file_object_id')->references('id')->on(DatabaseTable::FILE_OBJECTS)->restrictOnDelete();
            $table->index(['disk', 'path']);
            $table->index(['scan_state', 'created_at']);
            $table->index(['checksum_sha256', 'scan_state']);
            $table->index(['canonical_file_object_id', 'created_at']);
            $table->index(['retention_source_file_object_id', 'created_at']);
            $table->index(['deleted_at', 'created_at']);
        });

        Schema::create(DatabaseTable::FILE_SCAN_EVIDENCE, function (Blueprint $table): void {
            $table->id();
            $table->foreignId('file_object_id')->constrained(DatabaseTable::FILE_OBJECTS)->restrictOnDelete();
            $table->string('provider');
            $table->string('engine_version')->nullable();
            $table->string('signature_version')->nullable();
            $table->timestampTz('scanned_at');
            $table->string('result', 32);
            $table->string('threat_name')->nullable();
            $table->string('checksum_sha256', 64);
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['file_object_id', 'scanned_at']);
            $table->index(['result', 'scanned_at']);
            $table->index(['checksum_sha256', 'scanned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(DatabaseTable::FILE_SCAN_EVIDENCE);
        Schema::dropIfExists(DatabaseTable::FILE_OBJECTS);
    }
};
