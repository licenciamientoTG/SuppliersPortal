<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reception_attachments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('reception_id')->constrained('receptions')->cascadeOnDelete();

            // REMISSION (por evento) o PHOTO (por partida, solo cuando reception_item_id no es null)
            $table->string('attachable_type', 20);
            $table->foreignId('reception_item_id')->nullable()
                ->constrained('reception_items')->cascadeOnDelete();

            $table->string('disk', 40)->default('public');
            $table->string('path', 500);
            $table->string('original_name')->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size')->nullable();

            // SHA-256 del contenido del archivo
            $table->char('sha256', 64)->nullable();

            // EN_CARGA = calculado al subir; BACKFILL = calculado despues, sobre un archivo ya existente en disco
            $table->string('hash_origin', 20);

            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('uploaded_at')->nullable();

            $table->timestamps();

            $table->index('reception_id');
            $table->index('reception_item_id');
            $table->index('attachable_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reception_attachments');
    }
};
