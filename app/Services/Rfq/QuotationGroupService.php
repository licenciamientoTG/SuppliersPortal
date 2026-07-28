<?php

namespace App\Services\Rfq;

use App\Exceptions\Rfq\GroupDoesNotBelongToRequisitionException;
use App\Exceptions\Rfq\ItemsNotInRequisitionException;
use App\Models\QuotationGroup;
use App\Models\Requisition;

/**
 * Operaciones sobre grupos de cotización, compartidas por el planificador
 * del wizard (QuotationPlannerController) y el tablero.
 */
class QuotationGroupService
{
    public function create(Requisition $requisition, string $name, ?string $notes, array $itemIds, int $userId): QuotationGroup
    {
        $group = QuotationGroup::create([
            'requisition_id' => $requisition->id,
            'name' => $name,
            'notes' => $notes,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        if (! empty($itemIds)) {
            $this->assertItemsBelongToRequisition($requisition, $itemIds);
            $group->items()->attach($itemIds);
        }

        return $group;
    }

    /**
     * @throws GroupDoesNotBelongToRequisitionException
     * @throws ItemsNotInRequisitionException
     */
    public function addItems(Requisition $requisition, QuotationGroup $group, array $itemIds): QuotationGroup
    {
        $this->assertGroupBelongsToRequisition($requisition, $group);
        $this->assertItemsBelongToRequisition($requisition, $itemIds);

        $group->items()->syncWithoutDetaching($itemIds);

        return $group;
    }

    /**
     * @throws GroupDoesNotBelongToRequisitionException
     */
    public function removeItems(Requisition $requisition, QuotationGroup $group, array $itemIds): QuotationGroup
    {
        $this->assertGroupBelongsToRequisition($requisition, $group);

        $group->items()->detach($itemIds);

        return $group;
    }

    /**
     * Los grupos no se borran: se cancelan para conservar el expediente.
     *
     * @throws GroupDoesNotBelongToRequisitionException
     */
    public function cancel(Requisition $requisition, QuotationGroup $group, string $reason, int $userId): void
    {
        $this->assertGroupBelongsToRequisition($requisition, $group);

        $group->cancel($reason, $userId);
    }

    private function assertGroupBelongsToRequisition(Requisition $requisition, QuotationGroup $group): void
    {
        if ((int) $group->requisition_id !== (int) $requisition->id) {
            throw GroupDoesNotBelongToRequisitionException::make();
        }
    }

    private function assertItemsBelongToRequisition(Requisition $requisition, array $itemIds): void
    {
        $validCount = $requisition->items()->whereIn('id', $itemIds)->count();

        if ($validCount !== count($itemIds)) {
            throw ItemsNotInRequisitionException::make();
        }
    }
}
