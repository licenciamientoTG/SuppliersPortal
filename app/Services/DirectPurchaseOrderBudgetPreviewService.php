<?php

namespace App\Services;

use App\Models\BudgetCommitment;
use App\Models\CostCenter;
use App\Models\DirectPurchaseOrder;
use App\Models\ExchangeRate;
use App\Models\ProductService;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Vista previa (solo lectura) del presupuesto disponible para las partidas de una OCD
 * mientras se capturan. Replica la regla con la que se aparta el presupuesto:
 * subtotal sin IVA convertido a MXN, repartido por centros distribuidores y
 * acumulado por centro de costo + cuenta + cédula en el mes de aplicación.
 */
class DirectPurchaseOrderBudgetPreviewService
{
    private const EPSILON = 0.01;

    /** @var array<int, string> */
    private array $costCenterLabels = [];

    public function __construct(
        private readonly BudgetAllocationService $allocations,
        private readonly CostCenterDistributionService $distributions,
        private readonly ProductBudgetClassificationService $classifier,
    ) {}

    /**
     * Mes de aplicación igual al que asigna el controlador al guardar.
     */
    public function applicationMonth(?int $estimatedDeliveryDays): string
    {
        return now()->addDays($estimatedDeliveryDays ?: 30)->format('Y-m');
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $items  partidas indexadas como en el formulario
     * @param  Collection<int, int>  $allowedCostCenterIds  centros de costo activos del usuario
     */
    public function preview(
        User $user,
        array $items,
        string $currency,
        string $applicationMonth,
        Collection $allowedCostCenterIds,
        ?DirectPurchaseOrder $ocd = null,
    ): array {
        $month = Carbon::createFromFormat('Y-m-d', $applicationMonth.'-01');
        $result = [
            'month' => $applicationMonth,
            'month_label' => $month->locale('es')->translatedFormat('F Y'),
            'rows' => [],
        ];

        try {
            $rate = ExchangeRate::rateToMxn($currency);
        } catch (RuntimeException $exception) {
            $result['error'] = $exception->getMessage();

            return $result;
        }

        $ownCommitted = $this->ownCommittedAmounts($ocd, $applicationMonth);
        $availableByKey = [];
        $usedByKey = [];

        foreach ($items as $key => $item) {
            $result['rows'][$key] = $this->previewRow(
                $user, $item, $rate, $month, $allowedCostCenterIds, $ownCommitted, $availableByKey, $usedByKey
            );
        }

        return $result;
    }

    private function previewRow(
        User $user,
        array $item,
        float $rate,
        Carbon $month,
        Collection $allowedCostCenterIds,
        array $ownCommitted,
        array &$availableByKey,
        array &$usedByKey,
    ): array {
        $costCenterId = (int) ($item['cost_center_id'] ?? 0);
        $productId = (int) ($item['product_service_id'] ?? 0);

        if (! $costCenterId || ! $productId) {
            return ['status' => 'incomplete'];
        }

        if (! $allowedCostCenterIds->contains($costCenterId)) {
            return ['status' => 'error', 'message' => 'No tienes acceso a este centro de costo.'];
        }

        $product = ProductService::query()->find($productId);
        if (! $product) {
            return ['status' => 'error', 'message' => 'El producto ya no existe.'];
        }

        try {
            $classification = $this->classifier->resolveForProduct($product, $user->department_id);
            $amountMxn = round((float) ($item['quantity'] ?? 0) * (float) ($item['unit_price'] ?? 0) * $rate, 2);
            $allocations = $this->distributions->expand($costCenterId, max(0, $amountMxn));
        } catch (RuntimeException|ValidationException $exception) {
            return ['status' => 'error', 'message' => $this->messageFrom($exception)];
        }

        $categoryId = (int) $classification['expense_category_id'];
        $cedulaId = $classification['budget_cedula_id'] ? (int) $classification['budget_cedula_id'] : null;
        $rows = [];

        foreach ($allocations as $allocation) {
            $targetId = (int) $allocation['cost_center_id'];
            $amount = (float) $allocation['amount'];
            $key = implode('|', [$targetId, $categoryId, $cedulaId ?? 'null']);

            if (! array_key_exists($key, $availableByKey)) {
                $availableByKey[$key] = $this->availableFor($targetId, $month, $categoryId, $cedulaId, $ownCommitted[$key] ?? 0.0);
            }

            $available = $availableByKey[$key];
            $label = $this->costCenterLabel($targetId);

            if (is_string($available)) {
                $rows[] = ['cost_center' => $label, 'status' => 'error', 'message' => $available];

                continue;
            }

            if ($available === null) {
                $rows[] = ['cost_center' => $label, 'status' => 'free'];

                continue;
            }

            // Partidas anteriores con la misma cédula ya consumieron parte del saldo.
            $remainingBefore = $available - ($usedByKey[$key] ?? 0.0);
            $usedByKey[$key] = ($usedByKey[$key] ?? 0.0) + $amount;

            $rows[] = [
                'cost_center' => $label,
                'status' => $remainingBefore + self::EPSILON >= $amount ? 'ok' : 'insufficient',
                'available' => round(max(0, $remainingBefore), 2),
                'required' => round($amount, 2),
            ];
        }

        return [
            'status' => $this->rowStatus($rows),
            'required_mxn' => $amountMxn,
            'allocations' => $rows,
        ];
    }

    /**
     * Saldo disponible en MXN; null si el centro es de consumo libre; string con el
     * motivo cuando no hay presupuesto aprobado o distribución para el mes.
     */
    private function availableFor(int $costCenterId, Carbon $month, int $categoryId, ?int $cedulaId, float $ownCommitted): float|string|null
    {
        try {
            $check = $this->allocations->checkAvailability(
                $costCenterId, (int) $month->year, (int) $month->month, $categoryId, 0.0, $cedulaId
            );
        } catch (RuntimeException $exception) {
            return $exception->getMessage();
        }

        if (! array_key_exists('available_amount', $check)) {
            return ($check['available'] ?? false) ? null : (string) ($check['message'] ?? 'Sin presupuesto.');
        }

        return round((float) $check['available_amount'] + $ownCommitted, 2);
    }

    /**
     * Al editar una OCD que aún tiene presupuesto apartado, ese monto se libera
     * antes de volver a validar, así que cuenta como disponible para ella misma.
     *
     * @return array<string, float>
     */
    private function ownCommittedAmounts(?DirectPurchaseOrder $ocd, string $applicationMonth): array
    {
        if (! $ocd) {
            return [];
        }

        return BudgetCommitment::query()
            ->where('direct_purchase_order_id', $ocd->id)
            ->where('status', 'COMMITTED')
            ->where('application_month', $applicationMonth)
            ->get()
            ->groupBy(fn (BudgetCommitment $c) => implode('|', [$c->cost_center_id, $c->expense_category_id, $c->budget_cedula_id ?? 'null']))
            ->map(fn (Collection $group) => (float) $group->sum('committed_amount'))
            ->all();
    }

    private function rowStatus(array $rows): string
    {
        $statuses = collect($rows)->pluck('status');

        return match (true) {
            $statuses->contains('error') => 'error',
            $statuses->contains('insufficient') => 'insufficient',
            $statuses->isNotEmpty() && $statuses->every(fn ($s) => $s === 'free') => 'free',
            default => 'ok',
        };
    }

    private function costCenterLabel(int $costCenterId): string
    {
        if (! isset($this->costCenterLabels[$costCenterId])) {
            $center = CostCenter::query()->find($costCenterId, ['id', 'code', 'name']);
            $this->costCenterLabels[$costCenterId] = $center
                ? trim(($center->code ? "[{$center->code}] " : '').$center->name)
                : "#{$costCenterId}";
        }

        return $this->costCenterLabels[$costCenterId];
    }

    private function messageFrom(RuntimeException|ValidationException $exception): string
    {
        return $exception instanceof ValidationException
            ? collect($exception->errors())->flatten()->first() ?? 'Configuración de distribución inválida.'
            : $exception->getMessage();
    }
}
