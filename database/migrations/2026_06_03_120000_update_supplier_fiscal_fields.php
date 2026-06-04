<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('person_type', 20)->nullable()->after('supplier_type');
            $table->json('tax_regimes')->nullable()->after('person_type');
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlsrv') {
            DB::statement('ALTER TABLE suppliers DROP COLUMN tax_regime');
            DB::statement('ALTER TABLE suppliers DROP COLUMN economic_activity');

            Schema::table('suppliers', function (Blueprint $table) {
                $table->json('economic_activity')->nullable()->after('specialized_services_types');
            });
        } else {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->dropColumn(['tax_regime', 'economic_activity']);
            });

            Schema::table('suppliers', function (Blueprint $table) {
                $table->json('economic_activity')->nullable()->after('specialized_services_types');
            });
        }
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('tax_regime', 20)->nullable()->after('supplier_type');
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlsrv') {
            DB::statement('ALTER TABLE suppliers DROP COLUMN economic_activity');

            Schema::table('suppliers', function (Blueprint $table) {
                $table->string('economic_activity', 150)->nullable()->after('specialized_services_types');
                $table->dropColumn(['person_type', 'tax_regimes']);
            });
        } else {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->dropColumn('economic_activity');
            });

            Schema::table('suppliers', function (Blueprint $table) {
                $table->string('economic_activity', 150)->nullable()->after('specialized_services_types');
                $table->dropColumn(['person_type', 'tax_regimes']);
            });
        }
    }
};
