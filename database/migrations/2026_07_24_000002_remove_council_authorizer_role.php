<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $councilId = DB::table('authorizer_roles')
            ->where('name', 'Consejo de Administración')
            ->value('id');

        if (! $councilId) {
            return;
        }

        $dependencies = [
            'asignaciones de usuario' => Schema::hasTable('user_authorizer_roles')
                ? DB::table('user_authorizer_roles')->where('authorizer_role_id', $councilId)->count()
                : 0,
            'cotizaciones pendientes o históricas' => Schema::hasTable('quotation_summaries')
                ? DB::table('quotation_summaries')->where('authorizer_role_id', $councilId)->count()
                : 0,
            'órdenes directas' => Schema::hasTable('odc_direct_purchase_orders')
                ? DB::table('odc_direct_purchase_orders')->where('authorizer_role_id', $councilId)->count()
                : 0,
            'órdenes regulares' => Schema::hasTable('purchase_orders')
                && Schema::hasColumn('purchase_orders', 'authorizer_role_id')
                ? DB::table('purchase_orders')->where('authorizer_role_id', $councilId)->count()
                : 0,
        ];

        $found = collect($dependencies)->filter(fn (int $count) => $count > 0);

        if ($found->isNotEmpty()) {
            throw new \RuntimeException(
                'No se puede eliminar Consejo de Administración: se encontraron dependencias inesperadas ('
                .$found->map(fn ($count, $label) => "{$label}: {$count}")->implode(', ')
                .'). Corrige los datos antes de continuar.'
            );
        }

        DB::table('authorizer_roles')->where('id', $councilId)->delete();
    }

    public function down(): void
    {
        // Retirado por regla de negocio; no debe recrearse durante rollback.
    }
};
