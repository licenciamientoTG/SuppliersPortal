<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            if (! Schema::hasColumn('suppliers', 'first_name')) {
                $table->string('first_name', 100)->nullable();
            }

            if (! Schema::hasColumn('suppliers', 'last_name')) {
                $table->string('last_name', 100)->nullable();
            }

            if (! Schema::hasColumn('suppliers', 'password')) {
                $table->string('password')->nullable();
            }

            if (! Schema::hasColumn('suppliers', 'email_verified_at')) {
                $table->timestamp('email_verified_at')->nullable();
            }

            if (! Schema::hasColumn('suppliers', 'remember_token')) {
                $table->rememberToken();
            }

            if (! Schema::hasColumn('suppliers', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }

            if (! Schema::hasColumn('suppliers', 'last_login')) {
                $table->timestamp('last_login')->nullable();
            }

            if (! Schema::hasColumn('suppliers', 'approval_status')) {
                $table->string('approval_status', 20)->default('pending');
            }

            if (! Schema::hasColumn('suppliers', 'document_status')) {
                $table->string('document_status', 20)->default('pending');
            }

            if (! Schema::hasColumn('suppliers', 'approved_by')) {
                $table->unsignedBigInteger('approved_by')->nullable()->index();
            }

            if (! Schema::hasColumn('suppliers', 'approved_at')) {
                $table->timestamp('approved_at')->nullable();
            }

            if (! Schema::hasColumn('suppliers', 'rejected_by')) {
                $table->unsignedBigInteger('rejected_by')->nullable()->index();
            }

            if (! Schema::hasColumn('suppliers', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable();
            }

            if (! Schema::hasColumn('suppliers', 'approval_notes')) {
                $table->text('approval_notes')->nullable();
            }

            if (! Schema::hasColumn('suppliers', 'person_type')) {
                $table->string('person_type', 20)->nullable();
            }

            if (! Schema::hasColumn('suppliers', 'tax_regimes')) {
                $table->json('tax_regimes')->nullable();
            }
        });

        if (Schema::hasColumn('suppliers', 'economic_activity') && DB::getDriverName() === 'sqlsrv') {
            DB::statement('ALTER TABLE suppliers ALTER COLUMN economic_activity NVARCHAR(MAX) NULL');
        }

        if (Schema::hasColumn('suppliers', 'status')) {
            DB::statement("
                UPDATE suppliers
                SET approval_status = CASE
                    WHEN status = 'approved' THEN 'approved'
                    WHEN status = 'rejected' THEN 'rejected'
                    ELSE 'pending'
                END
                WHERE approval_status IS NULL OR approval_status = 'pending'
            ");

            DB::statement("
                UPDATE suppliers
                SET document_status = CASE
                    WHEN status = 'approved' THEN 'approved'
                    WHEN status = 'rejected' THEN 'rejected'
                    WHEN status = 'pending_docs' THEN 'in_review'
                    ELSE 'pending'
                END
                WHERE document_status IS NULL OR document_status = 'pending'
            ");
        }
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            foreach ([
                'first_name',
                'last_name',
                'password',
                'email_verified_at',
                'remember_token',
                'is_active',
                'last_login',
                'approval_status',
                'document_status',
                'approved_by',
                'approved_at',
                'rejected_by',
                'rejected_at',
                'approval_notes',
                'tax_regimes',
            ] as $column) {
                if (Schema::hasColumn('suppliers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
