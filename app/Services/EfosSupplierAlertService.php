<?php

namespace App\Services;

use App\Models\Supplier;
use App\Models\User;
use App\Notifications\SupplierListedInEfosNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class EfosSupplierAlertService
{
    public function notifyListedActiveSuppliers(): int
    {
        $deactivatedSuppliers = DB::transaction(
            fn (): Collection => $this->deactivateListedActiveSuppliers()
        );

        if ($deactivatedSuppliers->isEmpty()) {
            return 0;
        }

        $roles = Role::query()
            ->whereIn('name', ['buyer', 'superadmin', 'admin'])
            ->get();
        $recipients = $roles->isEmpty() ? collect() : User::role($roles)->get();

        $supplierData = $deactivatedSuppliers
            ->map(fn (Supplier $supplier) => [
                'id' => $supplier->id,
                'name' => $supplier->company_name,
                'rfc' => $supplier->rfc,
                'situation' => $supplier->efos_situation,
            ])
            ->values()
            ->all();

        $recipients->each->notify(new SupplierListedInEfosNotification($supplierData));

        return $deactivatedSuppliers->count();
    }

    /** @return Collection<int, Supplier> */
    private function deactivateListedActiveSuppliers(): Collection
    {
        $suppliers = Supplier::query()
            ->select('suppliers.*', 'efos.situation as efos_situation')
            ->join('sat_efos_69b as efos', 'efos.rfc', '=', 'suppliers.rfc')
            ->active()
            ->whereIn('efos.situation', ['Definitivo', 'Presunto'])
            ->lockForUpdate()
            ->get();

        $suppliers->each->update(['is_active' => false]);

        return $suppliers;
    }
}
