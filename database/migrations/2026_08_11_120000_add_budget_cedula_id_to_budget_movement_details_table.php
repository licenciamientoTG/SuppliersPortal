<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_movement_details', function (Blueprint $table) {
            $table->foreignId('budget_cedula_id')
                ->nullable()
                ->after('expense_category_id')
                ->constrained('budget_cedulas')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('budget_movement_details', function (Blueprint $table) {
            $table->dropConstrainedForeignId('budget_cedula_id');
        });
    }
};
