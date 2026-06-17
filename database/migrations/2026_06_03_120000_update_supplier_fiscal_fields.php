<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('suppliers', 'person_type')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->string('person_type', 20)->nullable()->after('supplier_type');
            });
        }

        if (! Schema::hasColumn('suppliers', 'tax_regimes')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->json('tax_regimes')->nullable()->after('person_type');
            });
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlsrv') {
            if (Schema::hasColumn('suppliers', 'tax_regime')) {
                DB::statement('ALTER TABLE suppliers DROP COLUMN tax_regime');
            }

            if (Schema::hasColumn('suppliers', 'economic_activity')) {
                DB::statement('ALTER TABLE suppliers DROP COLUMN economic_activity');
            }

            if (! Schema::hasColumn('suppliers', 'economic_activity')) {
                Schema::table('suppliers', function (Blueprint $table) {
                    $table->json('economic_activity')->nullable()->after('specialized_services_types');
                });
            }
        } else {
            $columnsToDrop = [];

            if (Schema::hasColumn('suppliers', 'tax_regime')) {
                $columnsToDrop[] = 'tax_regime';
            }

            if (Schema::hasColumn('suppliers', 'economic_activity')) {
                $columnsToDrop[] = 'economic_activity';
            }

            if ($columnsToDrop !== []) {
                Schema::table('suppliers', function (Blueprint $table) use ($columnsToDrop) {
                    $table->dropColumn($columnsToDrop);
                });
            }

            if (! Schema::hasColumn('suppliers', 'economic_activity')) {
                Schema::table('suppliers', function (Blueprint $table) {
                    $table->json('economic_activity')->nullable()->after('specialized_services_types');
                });
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('suppliers', 'tax_regime')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->string('tax_regime', 20)->nullable()->after('supplier_type');
            });
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlsrv') {
            if (Schema::hasColumn('suppliers', 'economic_activity')) {
                DB::statement('ALTER TABLE suppliers DROP COLUMN economic_activity');
            }

            Schema::table('suppliers', function (Blueprint $table) {
                if (! Schema::hasColumn('suppliers', 'economic_activity')) {
                    $table->string('economic_activity', 150)->nullable()->after('specialized_services_types');
                }
            });

            $columnsToDrop = [];

            if (Schema::hasColumn('suppliers', 'person_type')) {
                $columnsToDrop[] = 'person_type';
            }

            if (Schema::hasColumn('suppliers', 'tax_regimes')) {
                $columnsToDrop[] = 'tax_regimes';
            }

            if ($columnsToDrop !== []) {
                Schema::table('suppliers', function (Blueprint $table) use ($columnsToDrop) {
                    $table->dropColumn($columnsToDrop);
                });
            }
        } else {
            if (Schema::hasColumn('suppliers', 'economic_activity')) {
                Schema::table('suppliers', function (Blueprint $table) {
                    $table->dropColumn('economic_activity');
                });
            }

            Schema::table('suppliers', function (Blueprint $table) {
                if (! Schema::hasColumn('suppliers', 'economic_activity')) {
                    $table->string('economic_activity', 150)->nullable()->after('specialized_services_types');
                }
            });

            $columnsToDrop = [];

            if (Schema::hasColumn('suppliers', 'person_type')) {
                $columnsToDrop[] = 'person_type';
            }

            if (Schema::hasColumn('suppliers', 'tax_regimes')) {
                $columnsToDrop[] = 'tax_regimes';
            }

            if ($columnsToDrop !== []) {
                Schema::table('suppliers', function (Blueprint $table) use ($columnsToDrop) {
                    $table->dropColumn($columnsToDrop);
                });
            }
        }
    }
};
