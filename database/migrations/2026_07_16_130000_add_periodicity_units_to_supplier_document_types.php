<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_document_types', function (Blueprint $table) {
            $table->unsignedInteger('renewal_interval_value')->nullable()->after('renewal_interval_days');
            $table->string('renewal_interval_unit', 10)->nullable()->after('renewal_interval_value');
        });

        DB::table('supplier_document_types')
            ->whereNotNull('renewal_interval_days')
            ->update([
                'renewal_interval_value' => DB::raw('renewal_interval_days'),
                'renewal_interval_unit' => 'days',
            ]);
    }

    public function down(): void
    {
        Schema::table('supplier_document_types', function (Blueprint $table) {
            $table->dropColumn(['renewal_interval_value', 'renewal_interval_unit']);
        });
    }
};
