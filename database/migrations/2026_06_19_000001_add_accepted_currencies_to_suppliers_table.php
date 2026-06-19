<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            // Moneda(s) en las que el proveedor cotiza/opera: ["MXN"], ["USD"] o ["MXN","USD"].
            // Es independiente de la columna `currency`, que corresponde a la cuenta bancaria.
            $table->json('accepted_currencies')->nullable()->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('accepted_currencies');
        });
    }
};
