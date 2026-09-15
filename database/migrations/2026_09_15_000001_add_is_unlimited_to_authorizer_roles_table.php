<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('authorizer_roles', 'is_unlimited')) {
            Schema::table('authorizer_roles', function (Blueprint $table) {
                $table->boolean('is_unlimited')->default(false)->after('approval_limit');
            });
        }

        // Conserva el comportamiento previo: el rol sin limite se identificaba por nombre.
        DB::table('authorizer_roles')
            ->where('name', 'Dirección General')
            ->whereNull('approval_limit')
            ->update(['is_unlimited' => true]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('authorizer_roles', 'is_unlimited')) {
            Schema::table('authorizer_roles', function (Blueprint $table) {
                $table->dropColumn('is_unlimited');
            });
        }
    }
};
