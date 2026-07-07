<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LOCATION_COMPANY = [
        'CORP-001' => 'DGA',
        'E01149' => 'DGA',
        'E01163' => 'DGA',
        'E02172' => 'DGA',
        'E02526' => 'DGA',
        'E04179' => 'DGA',
        'E04188' => 'DGA',
        'E05317' => 'DGA',
        'E05465' => 'DGA',
        'E06410' => 'DGA',
        'E06947' => 'DGA',
        'E07167' => 'DGA',
        'E08244' => 'DGA',
        'E09191' => 'DGA',
        'E09235' => 'DGA',
        'E09885' => 'DGA',
        'E09893' => 'DGA',
        'E23214' => 'DGA',
        'P10702' => 'DGA',
        'P24938' => 'DGA',
        'E01242' => 'GVA',
        'E01376' => 'SSY',
        'E05170' => 'ECU',
        'E11007' => 'ECU',
        'P12840' => 'DGM',
        'P12841' => 'DGM',
        'P12842' => 'DGM',
        'P12843' => 'DGM',
        'P13074' => 'DGM',
        'P13620' => 'DCL',
        'P13624' => 'SJA',
        'E15091' => 'GOG',
        'P14946' => 'GOG',
        'P15071' => 'GOG',
        'P03340' => 'FGA',
        'P19190' => 'SGC',
        'P24499' => 'SPI',
        'P24500' => 'SVE',
    ];

    public function up(): void
    {
        if (! Schema::hasColumn('receiving_locations', 'company_id')) {
            Schema::table('receiving_locations', function (Blueprint $table) {
                $table->foreignId('company_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('companies')
                    ->noActionOnDelete();
            });
        }

        $this->deleteFakeData();
        $this->backfillCompanyIds();

        if (DB::getDriverName() === 'sqlsrv') {
            DB::statement('ALTER TABLE receiving_locations ALTER COLUMN company_id BIGINT NOT NULL');
        }

        if (! $this->indexExists('receiving_locations', 'idx_receiving_locations_company_active')) {
            Schema::table('receiving_locations', function (Blueprint $table) {
                $table->index(['company_id', 'is_active'], 'idx_receiving_locations_company_active');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('receiving_locations', 'company_id')) {
            return;
        }

        Schema::table('receiving_locations', function (Blueprint $table) {
            $table->dropIndex('idx_receiving_locations_company_active');
            $table->dropConstrainedForeignId('company_id');
        });
    }

    private function deleteFakeData(): void
    {
        $fakeCompanyIds = DB::table('companies')
            ->whereIn('code', ['MNJ', 'USB'])
            ->pluck('id');

        $fakeLocationIds = DB::table('receiving_locations')
            ->whereIn('code', ['LOCPRF', 'LOCQKA'])
            ->pluck('id');

        if ($fakeCompanyIds->isEmpty() && $fakeLocationIds->isEmpty()) {
            return;
        }

        $fakeRequisitionIds = DB::table('requisitions')
            ->where('status', 'draft')
            ->where(function ($query) use ($fakeCompanyIds, $fakeLocationIds) {
                if ($fakeCompanyIds->isNotEmpty()) {
                    $query->whereIn('company_id', $fakeCompanyIds);
                }

                if ($fakeLocationIds->isNotEmpty()) {
                    $query->orWhereIn('receiving_location_id', $fakeLocationIds);
                }
            })
            ->pluck('id');

        if ($fakeRequisitionIds->isNotEmpty()) {
            $this->deleteFakeRequisitionDependencies($fakeRequisitionIds);

            DB::table('requisitions')->whereIn('id', $fakeRequisitionIds)->delete();
        }

        if ($fakeLocationIds->isNotEmpty()) {
            DB::table('receiving_locations')->whereIn('id', $fakeLocationIds)->delete();
        }

        if ($fakeCompanyIds->isNotEmpty()) {
            DB::table('company_user')->whereIn('company_id', $fakeCompanyIds)->delete();
            DB::table('companies')->whereIn('id', $fakeCompanyIds)->delete();
        }
    }

    private function backfillCompanyIds(): void
    {
        $companyIds = DB::table('companies')
            ->whereIn('code', array_values(array_unique(self::LOCATION_COMPANY)))
            ->pluck('id', 'code');

        foreach (self::LOCATION_COMPANY as $locationCode => $companyCode) {
            $companyId = $companyIds[$companyCode] ?? null;

            if (! $companyId) {
                continue;
            }

            DB::table('receiving_locations')
                ->where('code', $locationCode)
                ->update(['company_id' => $companyId]);
        }
    }

    private function deleteFakeRequisitionDependencies($fakeRequisitionIds): void
    {
        $quotationGroupIds = Schema::hasTable('quotation_groups')
            ? DB::table('quotation_groups')->whereIn('requisition_id', $fakeRequisitionIds)->pluck('id')
            : collect();

        $rfqIds = Schema::hasTable('rfqs')
            ? DB::table('rfqs')
                ->whereIn('requisition_id', $fakeRequisitionIds)
                ->when($quotationGroupIds->isNotEmpty(), fn ($query) => $query->orWhereIn('quotation_group_id', $quotationGroupIds))
                ->pluck('id')
            : collect();

        $quotationSummaryIds = Schema::hasTable('quotation_summaries')
            ? DB::table('quotation_summaries')
                ->whereIn('requisition_id', $fakeRequisitionIds)
                ->when($rfqIds->isNotEmpty(), fn ($query) => $query->orWhereIn('rfq_id', $rfqIds))
                ->pluck('id')
            : collect();

        if ($quotationSummaryIds->isNotEmpty()) {
            foreach (['quotation_summary_items', 'budget_commitments'] as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->whereIn('quotation_summary_id', $quotationSummaryIds)->delete();
                }
            }

            if (Schema::hasTable('purchase_orders')) {
                $purchaseOrderIds = DB::table('purchase_orders')
                    ->whereIn('requisition_id', $fakeRequisitionIds)
                    ->orWhereIn('quotation_summary_id', $quotationSummaryIds)
                    ->pluck('id');

                if ($purchaseOrderIds->isNotEmpty()) {
                    if (Schema::hasTable('purchase_order_items')) {
                        DB::table('purchase_order_items')->whereIn('purchase_order_id', $purchaseOrderIds)->delete();
                    }

                    DB::table('purchase_orders')->whereIn('id', $purchaseOrderIds)->delete();
                }
            }

            DB::table('quotation_summaries')->whereIn('id', $quotationSummaryIds)->delete();
        }

        if ($rfqIds->isNotEmpty()) {
            foreach (['rfq_responses', 'rfq_suppliers'] as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->whereIn('rfq_id', $rfqIds)->delete();
                }
            }

            DB::table('rfqs')->whereIn('supersedes_rfq_id', $rfqIds)->update(['supersedes_rfq_id' => null]);
            DB::table('rfqs')->whereIn('id', $rfqIds)->delete();
        }

        if ($quotationGroupIds->isNotEmpty()) {
            if (Schema::hasTable('quotation_group_items')) {
                DB::table('quotation_group_items')->whereIn('quotation_group_id', $quotationGroupIds)->delete();
            }

            DB::table('quotation_groups')->whereIn('id', $quotationGroupIds)->delete();
        }

        foreach (['requisition_items', 'requisition_feedback'] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->whereIn('requisition_id', $fakeRequisitionIds)->delete();
            }
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        if (DB::getDriverName() !== 'sqlsrv') {
            return false;
        }

        return DB::table('sys.indexes')
            ->where('name', $index)
            ->whereRaw('object_id = OBJECT_ID(?)', [$table])
            ->exists();
    }
};
