<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La marca REPSE pasa de derivarse al vuelo (partidas de categoría SER) a
 * persistirse en la requisición, para que RC-01 y "Trazabilidad por
 * requisitor" usen el mismo dato ya calculado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requisitions', function (Blueprint $table) {
            $table->boolean('is_repse')->default(false)->after('status');
        });

        DB::table('requisitions')
            ->whereIn('id', fn ($query) => $query->select('requisition_items.requisition_id')
                ->from('requisition_items')
                ->join('expense_categories', 'expense_categories.id', '=', 'requisition_items.expense_category_id')
                ->where('expense_categories.code', 'SER'))
            ->update(['is_repse' => true]);
    }

    public function down(): void
    {
        Schema::table('requisitions', function (Blueprint $table) {
            $table->dropColumn('is_repse');
        });
    }
};
