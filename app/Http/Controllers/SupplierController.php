<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SupplierController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));
        $page = max((int) $request->query('page', 1), 1);
        $perPage = 20;

        $paginator = Supplier::query()
            ->approved()
            ->notEfos69b()
            ->search($term)
            ->orderBy('company_name')
            ->simplePaginate($perPage, ['id', 'company_name', 'rfc'], 'page', $page);

        $results = collect($paginator->items())->map(function (Supplier $supplier) {
            $rfc = $supplier->rfc ? " ({$supplier->rfc})" : '';

            return [
                'id' => $supplier->id,
                'text' => Str::limit($supplier->company_name, 80) . $rfc,
            ];
        });

        return response()->json([
            'results' => $results,
            'pagination' => ['more' => $paginator->hasMorePages()],
        ]);
    }
}
