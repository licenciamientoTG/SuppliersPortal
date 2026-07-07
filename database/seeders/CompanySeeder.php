<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        // ✅ Sin deletes ni reseed. Idempotente vía updateOrCreate.
        $rows = [
            [
                'code' => 'DGA',
                'name' => 'Diaz Gas',
                'legal_name' => 'Diaz Gas',
                'rfc' => 'DGA930823KD3',
                'locale' => 'es_MX',
                'timezone' => 'America/Ciudad_Juarez',
                'currency_code' => 'MXN',
                'phone' => '',
                'email' => '',
                'domain' => '',
                'website' => '',
                'logo_path' => '',
                'is_active' => true,
            ],
            [
                'code' => 'DCL',
                'name' => 'Distribuidora Clara',
                'legal_name' => 'Distribuidora Clara',
                'rfc' => 'DCL880518UG2',
                'locale' => 'es_MX',
                'timezone' => 'America/Ciudad_Juarez',
                'currency_code' => 'MXN',
                'phone' => '',
                'email' => '',
                'domain' => '',
                'website' => '',
                'logo_path' => '',
                'is_active' => true,
            ],
            [
                'code' => 'DGM',
                'name' => 'Distribuidora Gasomex',
                'legal_name' => 'Distribuidora Gasomex',
                'rfc' => 'DGM880621FU5',
                'locale' => 'es_MX',
                'timezone' => 'America/Ciudad_Juarez',
                'currency_code' => 'MXN',
                'phone' => '',
                'email' => '',
                'domain' => '',
                'website' => '',
                'logo_path' => '',
                'is_active' => true,
            ],
            [
                'code' => 'ECU',
                'name' => 'Estación Custodia',
                'legal_name' => 'Estación Custodia',
                'rfc' => 'ECU0602287R6',
                'locale' => 'es_MX',
                'timezone' => 'America/Ciudad_Juarez',
                'currency_code' => 'MXN',
                'phone' => '',
                'email' => '',
                'domain' => '',
                'website' => '',
                'logo_path' => '',
                'is_active' => true,
            ],
            [
                'code' => 'FGA',
                'name' => 'FormulaGas',
                'legal_name' => 'FormulaGas',
                'rfc' => 'FGA110722PR7',
                'locale' => 'es_MX',
                'timezone' => 'America/Ciudad_Juarez',
                'currency_code' => 'MXN',
                'phone' => '',
                'email' => '',
                'domain' => '',
                'website' => '',
                'logo_path' => '',
                'is_active' => true,
            ],
            [
                'code' => 'GVA',
                'name' => 'Gasolinera Villa Ahumada',
                'legal_name' => 'Gasolinera Villa Ahumada',
                'rfc' => 'GVA9709154V2',
                'locale' => 'es_MX',
                'timezone' => 'America/Ciudad_Juarez',
                'currency_code' => 'MXN',
                'phone' => '',
                'email' => '',
                'domain' => '',
                'website' => '',
                'logo_path' => '',
                'is_active' => true,
            ],
            [
                'code' => 'GOG',
                'name' => 'Grupo Operador Gasolinero TSA del Centro',
                'legal_name' => 'Grupo Operador Gasolinero TSA del Centro',
                'rfc' => 'GOG181220973',
                'locale' => 'es_MX',
                'timezone' => 'America/Ciudad_Juarez',
                'currency_code' => 'MXN',
                'phone' => '',
                'email' => '',
                'domain' => '',
                'website' => '',
                'logo_path' => '',
                'is_active' => true,
            ],
            [
                'code' => 'FIHH',
                'name' => 'Héctor Armandino Fierro Holguín',
                'legal_name' => 'Héctor Armandino Fierro Holguín',
                'rfc' => 'FIHH7303026K7',
                'locale' => 'es_MX',
                'timezone' => 'America/Ciudad_Juarez',
                'currency_code' => 'MXN',
                'phone' => '',
                'email' => '',
                'domain' => '',
                'website' => '',
                'logo_path' => '',
                'is_active' => true,
            ],
            [
                'code' => 'SJA',
                'name' => 'Servicio El Jarudo',
                'legal_name' => 'Servicio El Jarudo',
                'rfc' => 'SJA880518PRA',
                'locale' => 'es_MX',
                'timezone' => 'America/Ciudad_Juarez',
                'currency_code' => 'MXN',
                'phone' => '',
                'email' => '',
                'domain' => '',
                'website' => '',
                'logo_path' => '',
                'is_active' => true,
            ],
            [
                'code' => 'SSY',
                'name' => 'Servicio SYC',
                'legal_name' => 'Servicio SYC',
                'rfc' => 'SSY940520271',
                'locale' => 'es_MX',
                'timezone' => 'America/Ciudad_Juarez',
                'currency_code' => 'MXN',
                'phone' => '',
                'email' => '',
                'domain' => '',
                'website' => '',
                'logo_path' => '',
                'is_active' => true,
            ],
            [
                'code' => 'SGC',
                'name' => 'Servicios Gasolineros El Castaño',
                'legal_name' => 'Servicios Gasolineros El Castaño',
                'rfc' => 'SGC1304129H9',
                'locale' => 'es_MX',
                'timezone' => 'America/Ciudad_Juarez',
                'currency_code' => 'MXN',
                'phone' => '',
                'email' => '',
                'domain' => '',
                'website' => '',
                'logo_path' => '',
                'is_active' => true,
            ],
            [
                'code' => 'SPI',
                'name' => 'SMA Picachos',
                'legal_name' => 'SMA Picachos',
                'rfc' => 'SPI200529SC7',
                'locale' => 'es_MX',
                'timezone' => 'America/Ciudad_Juarez',
                'currency_code' => 'MXN',
                'phone' => '',
                'email' => '',
                'domain' => '',
                'website' => '',
                'logo_path' => '',
                'is_active' => true,
            ],
            [
                'code' => 'SVE',
                'name' => 'SMA Ventanas',
                'legal_name' => 'SMA Ventanas',
                'rfc' => 'SVE200529DB9',
                'locale' => 'es_MX',
                'timezone' => 'America/Ciudad_Juarez',
                'currency_code' => 'MXN',
                'phone' => '',
                'email' => '',
                'domain' => '',
                'website' => '',
                'logo_path' => '',
                'is_active' => true,
            ],
            [
                'code' => 'CDV',
                'name' => 'Controladora de Vales',
                'legal_name' => 'Controladora de Vales',
                'rfc' => 'CVA1302069W4',
                'locale' => 'es_MX',
                'timezone' => 'America/Ciudad_Juarez',
                'currency_code' => 'MXN',
                'phone' => '',
                'email' => '',
                'domain' => '',
                'website' => '',
                'logo_path' => '',
                'is_active' => true,
            ],
            [
                'code' => 'IMD',
                'name' => 'Inmo Diaz',
                'legal_name' => 'Inmo Diaz',
                'rfc' => 'IDI180213L38',
                'locale' => 'es_MX',
                'timezone' => 'America/Ciudad_Juarez',
                'currency_code' => 'MXN',
                'phone' => '',
                'email' => '',
                'domain' => '',
                'website' => '',
                'logo_path' => '',
                'is_active' => true,
            ],
            [
                'code' => 'PET',
                'name' => 'Petrotal',
                'legal_name' => 'Petrotal',
                'rfc' => 'PET180213L66',
                'locale' => 'es_MX',
                'timezone' => 'America/Ciudad_Juarez',
                'currency_code' => 'MXN',
                'phone' => '',
                'email' => '',
                'domain' => '',
                'website' => '',
                'logo_path' => '',
                'is_active' => true,
            ],
            [
                'code' => 'ZAI',
                'name' => 'Zaidenergy',
                'legal_name' => 'Zaidenergy',
                'rfc' => 'ZAI151013BL2',
                'locale' => 'es_MX',
                'timezone' => 'America/Ciudad_Juarez',
                'currency_code' => 'MXN',
                'phone' => '',
                'email' => '',
                'domain' => '',
                'website' => '',
                'logo_path' => '',
                'is_active' => true,
            ],
        ];

        DB::transaction(function () use ($rows) {
            $this->deleteFakeCompaniesAndLocations();

            foreach ($rows as $data) {
                Company::updateOrCreate(
                    ['code' => $data['code']], // clave natural única
                    $data
                );
            }
        });
    }

    private function deleteFakeCompaniesAndLocations(): void
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
}
