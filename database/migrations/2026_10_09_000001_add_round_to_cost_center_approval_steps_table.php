<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cada reinicio de la autorización abre una ronda nueva; las rondas anteriores
 * se conservan con estatus SUPERSEDED en lugar de borrarse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cost_center_approval_steps', function (Blueprint $table) {
            $table->unsignedInteger('round')->default(1)->after('step_order');
        });

        Schema::table('cost_center_approval_steps', function (Blueprint $table) {
            $table->dropUnique('cc_approval_step_order');
            $table->unique(['approvable_type', 'approvable_id', 'round', 'step_order'], 'cc_approval_step_round_order');
        });
    }

    /**
     * Falla a propósito si ya hay rondas anteriores guardadas: restaurar el índice
     * viejo obligaría a borrar historial.
     */
    public function down(): void
    {
        Schema::table('cost_center_approval_steps', function (Blueprint $table) {
            $table->dropUnique('cc_approval_step_round_order');
            $table->unique(['approvable_type', 'approvable_id', 'step_order'], 'cc_approval_step_order');
        });

        Schema::table('cost_center_approval_steps', function (Blueprint $table) {
            $table->dropColumn('round');
        });
    }
};
