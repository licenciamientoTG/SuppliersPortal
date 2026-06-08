<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('suppliers', 'postal_code')) {
            return;
        }

        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('postal_code', 10)->nullable()->after('address');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('suppliers', 'postal_code')) {
            return;
        }

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('postal_code');
        });
    }
};
