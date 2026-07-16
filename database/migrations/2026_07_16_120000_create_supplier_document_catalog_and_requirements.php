<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_document_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_required')->default(true);
            $table->boolean('applies_to_physical')->default(true);
            $table->boolean('applies_to_legal')->default(true);
            $table->boolean('requires_repse')->default(false);
            $table->string('renewal_mode', 20)->default('once');
            $table->unsignedInteger('renewal_interval_days')->nullable();
            $table->string('validity_source', 20)->default('manual');
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('supplier_document_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->noActionOnDelete();
            $table->foreignId('supplier_document_type_id')->constrained()->noActionOnDelete();
            $table->foreignId('current_document_id')->nullable()->constrained('supplier_documents')->noActionOnDelete();
            $table->string('status', 20)->default('pending');
            $table->boolean('is_enforced')->default(true);
            $table->timestamp('due_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamps();

            $table->unique(['supplier_id', 'supplier_document_type_id'], 'supplier_document_requirement_unique');
            $table->index(['is_enforced', 'due_at']);
            $table->index(['is_enforced', 'expires_at']);
        });

        Schema::create('supplier_document_requirement_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_document_requirement_id')->constrained()->cascadeOnDelete();
            $table->integer('milestone_days');
            $table->timestamp('sent_at')->useCurrent();
            $table->timestamps();

            $table->unique(['supplier_document_requirement_id', 'milestone_days'], 'supplier_document_requirement_notice_unique');
        });

        Schema::table('supplier_documents', function (Blueprint $table) {
            $table->foreignId('supplier_document_type_id')->nullable()->after('supplier_id')->constrained()->noActionOnDelete();
            $table->foreignId('supplier_document_requirement_id')->nullable()->after('supplier_document_type_id')->constrained()->noActionOnDelete();
            $table->date('document_expiration_date')->nullable()->after('reviewed_at');
            $table->timestamp('expiration_verified_at')->nullable()->after('document_expiration_date');
            $table->foreignId('expiration_verified_by')->nullable()->after('expiration_verified_at')->constrained('users')->noActionOnDelete();
        });

        $now = now();
        $types = [
            ['constancia_fiscal', 'Constancia de situacion fiscal', true, true, false, true],
            ['comprobante_domicilio', 'Comprobante de domicilio', true, true, false, true],
            ['caratula_bancaria', 'Caratula bancaria', true, true, false, true],
            ['opinion_sat', 'Opinion de cumplimiento SAT', true, true, false, true],
            ['acta_constitutiva', 'Acta constitutiva', false, true, false, true],
            ['poder_legal', 'Poder legal', false, true, false, true],
            ['identificacion_oficial', 'Identificacion oficial', true, true, false, true],
            ['opinion_imss', 'Opinion de cumplimiento IMSS', true, true, false, false],
            ['opinion_infonavit', 'Opinion de cumplimiento INFONAVIT', true, true, false, false],
            ['solicitud_alta_proveedor', 'Solicitud de alta de proveedor', true, true, false, true],
            ['repse', 'Registro REPSE', true, true, true, true],
            ['acta_confidencialidad', 'Acta de confidencialidad', true, true, false, true],
            ['curso_induccion', 'Curso de induccion', true, true, false, true],
        ];

        foreach ($types as [$code, $name, $physical, $legal, $repseOnly, $required]) {
            DB::table('supplier_document_types')->insert([
                'code' => $code,
                'name' => $name,
                'is_active' => true,
                'is_required' => $required,
                'applies_to_physical' => $physical,
                'applies_to_legal' => $legal,
                'requires_repse' => $repseOnly,
                'renewal_mode' => 'once',
                'validity_source' => 'manual',
                'activated_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $typeIds = DB::table('supplier_document_types')->pluck('id', 'code');
        DB::table('supplier_documents')->orderBy('id')->each(function (object $document) use ($typeIds): void {
            $typeId = $typeIds[$document->doc_type] ?? null;
            if ($typeId) {
                DB::table('supplier_documents')->where('id', $document->id)->update(['supplier_document_type_id' => $typeId]);
            }
        });

        DB::table('suppliers')->orderBy('id')->each(function (object $supplier) use ($now): void {
            foreach (DB::table('supplier_document_types')->where('is_required', true)->get() as $type) {
                $matchesPerson = ($supplier->person_type === 'fisica' && $type->applies_to_physical)
                    || ($supplier->person_type === 'moral' && $type->applies_to_legal);
                if (! $matchesPerson || ($type->requires_repse && ! $supplier->provides_specialized_services)) {
                    continue;
                }

                $latest = DB::table('supplier_documents')
                    ->where('supplier_id', $supplier->id)
                    ->where('supplier_document_type_id', $type->id)
                    ->orderByDesc('uploaded_at')
                    ->orderByDesc('id')
                    ->first();

                DB::table('supplier_document_requirements')->insert([
                    'supplier_id' => $supplier->id,
                    'supplier_document_type_id' => $type->id,
                    'current_document_id' => $latest?->status === 'accepted' ? $latest->id : null,
                    'status' => $latest?->status === 'accepted' ? 'compliant' : ($latest?->status === 'rejected' ? 'rejected' : ($latest ? 'submitted' : 'pending')),
                    'is_enforced' => true,
                    'fulfilled_at' => $latest?->status === 'accepted' ? ($latest->reviewed_at ?? $latest->uploaded_at) : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });

        DB::table('supplier_documents')->whereNotNull('supplier_document_type_id')->orderBy('id')->each(function (object $document): void {
            $requirementId = DB::table('supplier_document_requirements')
                ->where('supplier_id', $document->supplier_id)
                ->where('supplier_document_type_id', $document->supplier_document_type_id)
                ->value('id');
            if ($requirementId) {
                DB::table('supplier_documents')->where('id', $document->id)->update(['supplier_document_requirement_id' => $requirementId]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('supplier_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('expiration_verified_by');
            $table->dropColumn(['document_expiration_date', 'expiration_verified_at']);
            $table->dropConstrainedForeignId('supplier_document_requirement_id');
            $table->dropConstrainedForeignId('supplier_document_type_id');
        });
        Schema::dropIfExists('supplier_document_requirement_notifications');
        Schema::dropIfExists('supplier_document_requirements');
        Schema::dropIfExists('supplier_document_types');
    }
};
