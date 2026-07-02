<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rfq_responses', function (Blueprint $table) {
            $table->enum('entry_source', ['supplier_portal', 'buyer_manual'])
                ->default('supplier_portal')
                ->after('status');

            $table->foreignId('entered_by')
                ->nullable()
                ->after('entry_source')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rfq_responses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('entered_by');
            $table->dropColumn('entry_source');
        });
    }
};
