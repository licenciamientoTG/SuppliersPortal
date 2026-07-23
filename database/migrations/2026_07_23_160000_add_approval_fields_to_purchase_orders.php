<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // El enum original no admite PENDING_APPROVAL/REJECTED; se amplía a string.
        if (DB::getDriverName() === 'sqlsrv') {
            DB::statement("
                DECLARE @c NVARCHAR(200);
                SELECT @c = name FROM sys.check_constraints
                WHERE parent_object_id = OBJECT_ID('purchase_orders') AND definition LIKE '%status%';
                IF @c IS NOT NULL EXEC('ALTER TABLE purchase_orders DROP CONSTRAINT ' + @c);
            ");
            DB::statement('ALTER TABLE purchase_orders ALTER COLUMN status NVARCHAR(40) NOT NULL');
        } else {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->string('status', 40)->default('OPEN')->change();
            });
        }

        Schema::table('purchase_orders', function (Blueprint $table) {
            // Control de autorización (espejo de odc_direct_purchase_orders)
            $table->foreignId('assigned_approver_id')->nullable()->constrained('users')->noActionOnDelete();
            $table->foreignId('authorizer_role_id')->nullable()->constrained('authorizer_roles')->noActionOnDelete();
            $table->decimal('effective_authorization_limit', 14, 2)->nullable();
            $table->longText('approval_chain_snapshot')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->noActionOnDelete();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->noActionOnDelete();
            $table->timestamp('rejected_at')->nullable();

            $table->index('assigned_approver_id');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropIndex(['assigned_approver_id']);
            $table->dropConstrainedForeignId('assigned_approver_id');
            $table->dropConstrainedForeignId('authorizer_role_id');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropColumn([
                'effective_authorization_limit', 'approval_chain_snapshot',
                'resolution_notes', 'rejected_at',
            ]);
        });
    }
};
