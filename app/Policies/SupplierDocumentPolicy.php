<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\SupplierDocument;
use App\Models\User;
use App\Services\ModuleAccessService;

class SupplierDocumentPolicy
{
    public function view(User|Supplier $actor, SupplierDocument $document): bool
    {
        if ($actor instanceof Supplier) {
            return (int) $actor->id === (int) $document->supplier_id;
        }

        return app(ModuleAccessService::class)->userCanAccessModule($actor, 'document_review');
    }

    public function review(User $user, SupplierDocument $document): bool
    {
        return app(ModuleAccessService::class)->userCanAccessModule($user, 'document_review');
    }
}
