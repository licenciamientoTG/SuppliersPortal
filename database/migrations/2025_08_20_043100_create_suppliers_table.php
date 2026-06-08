<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 150)->unique();
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login')->nullable();

            $table->string('company_name', 150);
            $table->string('rfc', 13);
            $table->text('address');
            $table->string('phone_number', 15);
            $table->string('contact_person', 100);
            $table->string('contact_phone', 10)->nullable();
            $table->string('supplier_type', 20);
            $table->string('tax_regime', 20);
            $table->string('bank_name', 100)->nullable();
            $table->string('account_number', 20)->nullable();
            $table->string('clabe', 18)->nullable();
            $table->string('currency', 3)->default('MXN')->nullable();
            $table->string('default_payment_terms', 30)->default('CASH');
            $table->string('swift_bic', 11)->nullable();
            $table->string('iban', 34)->nullable();
            $table->string('bank_address', 255)->nullable();
            $table->string('aba_routing', 9)->nullable();
            $table->string('us_bank_name', 100)->nullable();

            $table->string('approval_status', 20)->default('pending');
            $table->string('document_status', 20)->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('approval_notes')->nullable();

            $table->boolean('provides_specialized_services')->default(false);
            $table->string('repse_registration_number')->nullable();
            $table->date('repse_expiry_date')->nullable();
            $table->json('specialized_services_types')->nullable();
            $table->string('economic_activity', 150)->nullable();
            $table->timestamps();

            $table->unique('rfc');
            $table->index(['company_name']);
        });

        DB::statement("
            CREATE UNIQUE INDEX suppliers_account_number_unique
            ON suppliers(account_number)
            WHERE account_number IS NOT NULL
        ");
    }

    public function down(): void
    {
        try {
            DB::statement("DROP INDEX suppliers_account_number_unique ON suppliers");
        } catch (\Throwable $e) {
        }

        Schema::dropIfExists('suppliers');
    }
};
