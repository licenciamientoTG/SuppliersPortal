<?php

namespace App\Services;

use App\Models\Supplier;
use App\Models\User;
use App\Notifications\SupplierListedInEfosNotification;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

class EfosSupplierAlertService
{
    public function notifyListedActiveSuppliers(): int
    {
        $listedSuppliers = $this->listedActiveSuppliers();

        if ($listedSuppliers->isEmpty()) {
            return 0;
        }

        $roles = Role::query()
            ->whereIn('name', ['buyer', 'superadmin', 'admin'])
            ->get();
        $recipients = $roles->isEmpty() ? collect() : User::role($roles)->get();

        foreach ($listedSuppliers as $supplier) {
            $recipients->each->notify(new SupplierListedInEfosNotification($supplier));
        }

        return $listedSuppliers->count();
    }

    /** @return Collection<int, Supplier> */
    private function listedActiveSuppliers(): Collection
    {
        return Supplier::query()
            ->active()
            ->whereIn('rfc', function ($query) {
                $query->select('rfc')
                    ->from('sat_efos_69b')
                    ->whereIn('situation', ['Definitivo', 'Presunto']);
            })
            ->get();
    }
}
