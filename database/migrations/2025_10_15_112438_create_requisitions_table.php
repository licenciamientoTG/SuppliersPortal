<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('requisitions', function (Blueprint $table) {
            $table->bigIncrements('id');

            // ======= FKs "fuertes" =======
            $table->foreignId('company_id')
                ->constrained('companies')
                ->onUpdate('NO ACTION')
                ->onDelete('NO ACTION');

            $table->foreignId('receiving_location_id')
                ->constrained('receiving_locations')
                ->onUpdate('NO ACTION')
                ->onDelete('NO ACTION');

            $table->foreignId('department_id')
                ->nullable()
                ->constrained('departments')
                ->onUpdate('NO ACTION')
                ->onDelete('NO ACTION');

            // ======= Datos generales =======
            $table->string('folio', 30)->unique();

            $table->unsignedBigInteger('requested_by')->nullable()->index();
            $table->date('required_date')->nullable();
            $table->text('description')->nullable();

            // ======= Estado =======
            $table->string('status', 40)->default('draft')
                ->comment('Estado de la requisición (sin aprobación interna)');

            // ======= Estado Pausada =======
            $table->text('pause_reason')
                ->nullable()
                ->comment('Motivo de la pausa (ej: esperando aprobación de producto en catálogo)');

            $table->unsignedBigInteger('paused_by')->nullable()->index();
            $table->timestamp('paused_at')->nullable();

            $table->unsignedBigInteger('reactivated_by')->nullable()->index();
            $table->timestamp('reactivated_at')->nullable();

            // ======= Cancelación =======
            $table->text('cancellation_reason')->nullable()
                ->comment('Motivo de cancelación por Compras o requisitor');
            $table->unsignedBigInteger('cancelled_by')->nullable()->index();
            $table->dateTime('cancelled_at')->nullable();

            // ======= Rechazo (Compras) =======
            $table->text('rejection_reason')->nullable()
                ->comment('Motivo de rechazo por el departamento de Compras');
            $table->unsignedBigInteger('rejected_by')->nullable()->index();
            $table->dateTime('rejected_at')->nullable();

            // ======= NUEVO: Validaciones del Departamento de Compras =======
            $table->boolean('validation_specs_clear')->default(false)
                ->comment('Validación: Claridad de especificaciones técnicas');

            $table->boolean('validation_time_feasible')->default(false)
                ->comment('Validación: Factibilidad de tiempos de entrega');

            $table->boolean('validation_alternatives_evaluated')->default(false)
                ->comment('Validación: Evaluación de alternativas de catálogo');

            $table->timestamp('validated_at')->nullable()
                ->comment('Fecha y hora de validación por Compras');

            $table->unsignedBigInteger('validated_by')->nullable()->index()
                ->comment('Usuario de Compras que validó la requisición');

            // ======= Notas de Validación (Compras) =======
            $table->text('purchasing_validation_notes')->nullable()
                ->comment('Notas de validación del departamento de Compras');

            // ======= Auditoría =======
            $table->unsignedBigInteger('created_by')->nullable()->index()
                ->comment('Usuario que creó la requisición (requisitor)');
            $table->unsignedBigInteger('updated_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            // Índices útiles
            $table->index('status');
            $table->index('paused_at');
            $table->index('required_date');
        });

        // SQL Server-specific statements (skipped on SQLite / testing environments)
        if (DB::getDriverName() === 'sqlsrv') {
            // Comentario para SQL Server
            DB::statement("EXEC sp_addextendedproperty
                @name = N'MS_Description',
                @value = 'Requisiciones - Pasan directamente a Compras sin aprobación interna. La aprobación se realiza sobre la cotización.',
                @level0type = N'SCHEMA', @level0name = 'dbo',
                @level1type = N'TABLE',  @level1name = 'requisitions'");

            // Constraints manuales
            DB::statement("
                ALTER TABLE requisitions
                ADD CONSTRAINT fk_requisitions_requested_by
                FOREIGN KEY (requested_by) REFERENCES users(id)
                ON DELETE NO ACTION ON UPDATE NO ACTION
            ");

            DB::statement("
                ALTER TABLE requisitions
                ADD CONSTRAINT fk_requisitions_paused_by
                FOREIGN KEY (paused_by) REFERENCES users(id)
                ON DELETE NO ACTION ON UPDATE NO ACTION
            ");

            DB::statement("
                ALTER TABLE requisitions
                ADD CONSTRAINT fk_requisitions_reactivated_by
                FOREIGN KEY (reactivated_by) REFERENCES users(id)
                ON DELETE NO ACTION ON UPDATE NO ACTION
            ");

            DB::statement("
                ALTER TABLE requisitions
                ADD CONSTRAINT fk_requisitions_cancelled_by
                FOREIGN KEY (cancelled_by) REFERENCES users(id)
                ON DELETE NO ACTION ON UPDATE NO ACTION
            ");

            DB::statement("
                ALTER TABLE requisitions
                ADD CONSTRAINT fk_requisitions_rejected_by
                FOREIGN KEY (rejected_by) REFERENCES users(id)
                ON DELETE NO ACTION ON UPDATE NO ACTION
            ");

            DB::statement("
                ALTER TABLE requisitions
                ADD CONSTRAINT fk_requisitions_created_by
                FOREIGN KEY (created_by) REFERENCES users(id)
                ON DELETE NO ACTION ON UPDATE NO ACTION
            ");

            DB::statement("
                ALTER TABLE requisitions
                ADD CONSTRAINT fk_requisitions_updated_by
                FOREIGN KEY (updated_by) REFERENCES users(id)
                ON DELETE NO ACTION ON UPDATE NO ACTION
            ");

            // ======= NUEVO: Constraint para validated_by =======
            DB::statement("
                ALTER TABLE requisitions
                ADD CONSTRAINT fk_requisitions_validated_by
                FOREIGN KEY (validated_by) REFERENCES users(id)
                ON DELETE NO ACTION ON UPDATE NO ACTION
            ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlsrv') {
            DB::statement("ALTER TABLE requisitions DROP CONSTRAINT IF EXISTS fk_requisitions_requested_by");
            DB::statement("ALTER TABLE requisitions DROP CONSTRAINT IF EXISTS fk_requisitions_paused_by");
            DB::statement("ALTER TABLE requisitions DROP CONSTRAINT IF EXISTS fk_requisitions_reactivated_by");
            DB::statement("ALTER TABLE requisitions DROP CONSTRAINT IF EXISTS fk_requisitions_cancelled_by");
            DB::statement("ALTER TABLE requisitions DROP CONSTRAINT IF EXISTS fk_requisitions_rejected_by");
            DB::statement("ALTER TABLE requisitions DROP CONSTRAINT IF EXISTS fk_requisitions_created_by");
            DB::statement("ALTER TABLE requisitions DROP CONSTRAINT IF EXISTS fk_requisitions_updated_by");
            DB::statement("ALTER TABLE requisitions DROP CONSTRAINT IF EXISTS fk_requisitions_validated_by");
        }

        Schema::dropIfExists('requisitions');
    }
};
