<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requisition_items', function (Blueprint $table) {
            $table->unsignedBigInteger('contract_id')->nullable()->after('notes');
            $table->unsignedBigInteger('contract_product_id')->nullable()->after('contract_id');
            $table->decimal('unit_price', 14, 4)->nullable()->after('contract_product_id');
            $table->char('currency_code', 3)->nullable()->after('unit_price');
        });

        if (DB::getDriverName() === 'sqlsrv') {
            DB::statement("
                ALTER TABLE requisition_items
                ADD CONSTRAINT fk_req_items_contract_id
                FOREIGN KEY (contract_id) REFERENCES contracts(id)
                ON DELETE NO ACTION ON UPDATE NO ACTION
            ");

            DB::statement("
                ALTER TABLE requisition_items
                ADD CONSTRAINT fk_req_items_contract_product_id
                FOREIGN KEY (contract_product_id) REFERENCES contract_products(id)
                ON DELETE NO ACTION ON UPDATE NO ACTION
            ");
        } else {
            Schema::table('requisition_items', function (Blueprint $table) {
                $table->foreign('contract_id')
                    ->references('id')
                    ->on('contracts')
                    ->noActionOnDelete();

                $table->foreign('contract_product_id')
                    ->references('id')
                    ->on('contract_products')
                    ->noActionOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlsrv') {
            DB::statement('ALTER TABLE requisition_items DROP CONSTRAINT IF EXISTS fk_req_items_contract_id');
            DB::statement('ALTER TABLE requisition_items DROP CONSTRAINT IF EXISTS fk_req_items_contract_product_id');
        } else {
            Schema::table('requisition_items', function (Blueprint $table) {
                $table->dropForeign(['contract_id']);
                $table->dropForeign(['contract_product_id']);
            });
        }

        Schema::table('requisition_items', function (Blueprint $table) {
            $table->dropColumn(['contract_id', 'contract_product_id', 'unit_price', 'currency_code']);
        });
    }
};
