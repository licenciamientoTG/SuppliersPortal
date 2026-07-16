<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERIODIC_CODES = [
        'constancia_fiscal',
        'opinion_sat',
        'opinion_imss',
        'opinion_infonavit',
    ];

    public function up(): void
    {
        Schema::table('supplier_documents', function (Blueprint $table) {
            if (! Schema::hasColumn('supplier_documents', 'issued_at')) {
                $table->date('issued_at')->nullable()->after('document_expiration_date');
            }
            if (! Schema::hasColumn('supplier_documents', 'issued_at_source')) {
                $table->string('issued_at_source', 20)->nullable()->after('issued_at');
            }
            if (! Schema::hasColumn('supplier_documents', 'issued_at_verified_at')) {
                $table->timestamp('issued_at_verified_at')->nullable()->after('issued_at_source');
            }
            if (! Schema::hasColumn('supplier_documents', 'issued_at_verified_by')) {
                $table->foreignId('issued_at_verified_by')->nullable()->after('issued_at_verified_at')->constrained('users')->noActionOnDelete();
            }
            if (! Schema::hasColumn('supplier_documents', 'issue_date_extraction_data')) {
                $table->text('issue_date_extraction_data')->nullable()->after('issued_at_verified_by');
            }
        });

        $now = now();
        $transitionEndsAt = $now->copy()->addDays(14);
        $types = DB::table('supplier_document_types')->whereIn('code', self::PERIODIC_CODES)->get(['id', 'code']);

        DB::table('supplier_document_types')->whereIn('code', self::PERIODIC_CODES)->update([
            'is_required' => true,
            'renewal_mode' => 'periodic',
            'renewal_interval_days' => null,
            'renewal_interval_value' => 3,
            'renewal_interval_unit' => 'months',
            'validity_source' => 'qr',
            'updated_at' => $now,
        ]);

        $supplierIds = DB::table('suppliers')->pluck('id');
        // SQL Server accepts a maximum of 2,100 bound parameters per statement.
        foreach ($supplierIds->chunk(50) as $supplierChunk) {
            $rows = [];
            foreach ($supplierChunk as $supplierId) {
                foreach ($types as $type) {
                    $rows[] = [
                        'supplier_id' => $supplierId,
                        'supplier_document_type_id' => $type->id,
                        'status' => 'pending',
                        'is_enforced' => true,
                        'due_at' => $transitionEndsAt,
                        'expires_at' => $transitionEndsAt,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
            DB::table('supplier_document_requirements')->upsert(
                $rows,
                ['supplier_id', 'supplier_document_type_id'],
                ['is_enforced', 'updated_at']
            );
        }

        DB::table('supplier_document_requirements')
            ->whereIn('supplier_document_type_id', $types->pluck('id'))
            ->update(['expires_at' => $transitionEndsAt, 'updated_at' => $now]);
    }

    public function down(): void
    {
        Schema::table('supplier_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('issued_at_verified_by');
            $table->dropColumn(['issued_at', 'issued_at_source', 'issued_at_verified_at', 'issue_date_extraction_data']);
        });
    }
};
