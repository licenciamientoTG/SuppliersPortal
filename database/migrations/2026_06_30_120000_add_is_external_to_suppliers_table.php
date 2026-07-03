<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('suppliers', 'is_external')) {
            return;
        }

        Schema::table('suppliers', function (Blueprint $table) {
            $table->boolean('is_external')->default(false)->after('approval_status');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('suppliers', 'is_external')) {
            return;
        }

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('is_external');
        });
    }
};
