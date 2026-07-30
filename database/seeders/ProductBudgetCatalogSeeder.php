<?php

namespace Database\Seeders;

use App\Enum\ProductServiceStatus;
use App\Models\ProductService;
use App\Models\Subaccount;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ProductBudgetCatalogSeeder extends Seeder
{
    private const CATALOG_PATH = 'database/seeders/data/product_budget_catalog.csv';

    private const PENDING_INVENTORIABLE_PATH = 'database/seeders/data/pending_inventoriable_catalog.csv';

    /** @var array<string, string> */
    private const SUBCATEGORY_ALIASES = [
        'mantenimiento en general' => 'Mantenimiento General',
    ];

    public function run(): void
    {
        $user = User::query()->where('name', 'Super Administrador')->first()
            ?? User::query()->orderBy('id')->first();

        if (! $user) {
            throw new \RuntimeException('No hay usuarios disponibles para registrar la auditoría del catálogo.');
        }

        $catalog = $this->readCatalog(base_path(self::CATALOG_PATH));
        $pendingInventoriables = $this->readPendingInventoriables(base_path(self::PENDING_INVENTORIABLE_PATH));
        $subaccounts = $this->subaccountsFor($catalog);

        $nextCode = $this->nextProductCodeNumber();

        DB::transaction(function () use ($catalog, $pendingInventoriables, $subaccounts, $user, &$nextCode): void {
            $productsByName = ProductService::withTrashed()->get()
                ->keyBy(fn (ProductService $product) => $this->normalize($product->short_name ?: $product->technical_description));

            foreach ($catalog as $entry) {
                $name = $this->normalize($entry['description']);
                $product = $productsByName->get($name);

                if (! $product) {
                    $product = new ProductService(['code' => sprintf('PROD-%06d', $nextCode++)]);
                } elseif ($product->trashed()) {
                    $product->restore();
                }

                $product->fill([
                    'technical_description' => null,
                    'short_name' => $entry['description'],
                    'product_type' => $entry['product_type'],
                    'unit_of_measure' => $entry['product_type'] === 'SERVICIO' ? 'SERVICIO' : 'PIEZA',
                    'estimated_price' => 0,
                    'currency_code' => 'MXN',
                    'is_inventoriable' => false,
                    'status' => ProductServiceStatus::ACTIVE->value,
                    'is_active' => true,
                    'created_by' => $product->exists ? $product->created_by : $user->id,
                    'updated_by' => $product->exists ? $user->id : null,
                    'deleted_by' => null,
                ]);
                $product->save();

                // La clasificación presupuestal del producto se guarda únicamente por subcuenta.
                $subaccount = $subaccounts->get($this->normalize($entry['budget_subcategory']));
                $product->subaccounts()->sync([$subaccount->id]);

                // Mantiene la relación legacy con la misma subcategoría, sin enlazar categorías.
                $product->budgetCedulas()->sync([$subaccount->legacy_budget_cedula_id]);
            }

            $this->purgeOutOfScopeProducts($catalog, $pendingInventoriables);
        });
    }

    /** @return array<int, array{description: string, expense_category: string, budget_subcategory: string, product_type: string}> */
    private function readCatalog(string $path): array
    {
        $rows = $this->readCsv($path, ['description', 'expense_category', 'budget_subcategory', 'product_type']);
        $seen = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            foreach (['description', 'budget_subcategory', 'product_type'] as $field) {
                if (blank($row[$field] ?? null)) {
                    throw new \RuntimeException("Fila {$line}: {$field} es obligatorio.");
                }
            }

            if (! in_array($row['product_type'], ['PRODUCTO', 'SERVICIO'], true)) {
                throw new \RuntimeException("Fila {$line}: tipo de producto inválido.");
            }

            $key = $this->normalize($row['description']);
            if (isset($seen[$key])) {
                throw new \RuntimeException("Fila {$line}: descripción duplicada en el catálogo.");
            }
            $seen[$key] = true;
        }

        if (count($rows) !== 331) {
            throw new \RuntimeException('El catálogo presupuestal debe contener exactamente 331 productos clasificados.');
        }

        return $rows;
    }

    /** @return array<string, true> */
    private function readPendingInventoriables(string $path): array
    {
        $rows = $this->readCsv($path, ['description']);
        $names = [];

        foreach ($rows as $row) {
            $names[$this->normalize($row['description'])] = true;
        }

        if (count($names) !== 154) {
            throw new \RuntimeException('El catálogo pendiente de inventariables debe contener exactamente 154 productos.');
        }

        return $names;
    }

    /** @param array<int, array{budget_subcategory: string}> $catalog */
    private function subaccountsFor(array $catalog): Collection
    {
        $requested = collect($catalog)
            ->pluck('budget_subcategory')
            ->map(fn (string $name) => self::SUBCATEGORY_ALIASES[$this->normalize($name)] ?? $name)
            ->unique();

        $subaccounts = Subaccount::query()
            ->whereNotNull('legacy_budget_cedula_id')
            ->get()
            ->keyBy(fn (Subaccount $subaccount) => $this->normalize($subaccount->name));

        $missing = $requested->filter(fn (string $name) => ! $subaccounts->has($this->normalize($name)));
        if ($missing->isNotEmpty()) {
            throw new \RuntimeException('No existen las subcategorías presupuestales: '.$missing->implode(', ').'.');
        }

        return collect($catalog)->mapWithKeys(function (array $entry) use ($subaccounts): array {
            $name = self::SUBCATEGORY_ALIASES[$this->normalize($entry['budget_subcategory'])] ?? $entry['budget_subcategory'];

            return [$this->normalize($entry['budget_subcategory']) => $subaccounts->get($this->normalize($name))];
        });
    }

    /** @param array<int, array{description: string}> $catalog @param array<string, true> $pendingInventoriables */
    private function purgeOutOfScopeProducts(array $catalog, array $pendingInventoriables): void
    {
        $catalogNames = collect($catalog)
            ->mapWithKeys(fn (array $entry) => [$this->normalize($entry['description']) => true])
            ->all();

        $candidates = ProductService::withTrashed()
            ->where(function ($query): void {
                $query->whereNull('is_inventoriable')->orWhere('is_inventoriable', false);
            })
            ->get()
            ->filter(function (ProductService $product) use ($catalogNames, $pendingInventoriables): bool {
                $name = $this->normalize($product->short_name ?: $product->technical_description);

                return ! isset($catalogNames[$name]) && ! isset($pendingInventoriables[$name]);
            });

        $references = $this->referencesFor($candidates->pluck('id')->all());
        if ($references !== []) {
            throw new \RuntimeException('No se puede limpiar el catálogo; hay productos con referencias operativas en: '.implode(', ', $references).'.');
        }

        $candidates->each->forceDelete();
    }

    /** @param array<int, int> $productIds @return array<int, string> */
    private function referencesFor(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        return collect(['requisition_items', 'contract_products', 'odc_direct_purchase_order_items'])
            ->filter(fn (string $table) => Schema::hasTable($table))
            ->filter(fn (string $table) => DB::table($table)->whereIn('product_service_id', $productIds)->exists())
            ->values()
            ->all();
    }

    /** @return array<int, array<string, string>> */
    private function readCsv(string $path, array $headers): array
    {
        if (! is_file($path)) {
            throw new \RuntimeException("No se encontró el archivo {$path}.");
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException("No se pudo abrir {$path}.");
        }

        try {
            $actualHeaders = fgetcsv($handle);
            if ($actualHeaders !== $headers) {
                throw new \RuntimeException("Encabezados inválidos en {$path}.");
            }

            $rows = [];
            while (($row = fgetcsv($handle)) !== false) {
                if ($row === [null] || $row === []) {
                    continue;
                }
                if (count($row) !== count($headers)) {
                    throw new \RuntimeException("Fila inválida en {$path}.");
                }
                $rows[] = array_combine($headers, array_map(fn ($value) => trim((string) $value), $row));
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    private function normalize(?string $value): string
    {
        return (string) Str::of($value ?? '')
            ->ascii()
            ->lower()
            ->squish();
    }

    private function nextProductCodeNumber(): int
    {
        $highest = ProductService::withTrashed()
            ->pluck('code')
            ->map(function (?string $code): int {
                return preg_match('/^PROD-(\d+)$/', (string) $code, $matches) ? (int) $matches[1] : 0;
            })
            ->max();

        return ((int) $highest) + 1;
    }
}
