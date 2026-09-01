<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ProductController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Product::class);
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
        ]);
        $search = trim((string) ($validated['search'] ?? ''));

        $products = Product::query()
            ->with('taxRate')
            ->where('active', true)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('article_number', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%");
                });
            })
            ->orderBy('title')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        return ProductResource::collection($products);
    }

    public function show(Product $product): ProductResource
    {
        abort_unless($product->active, Response::HTTP_NOT_FOUND);
        Gate::authorize('view', $product);

        return new ProductResource($product->load('taxRate'));
    }

    public function byBarcode(string $barcode): ProductResource
    {
        Gate::authorize('viewAny', Product::class);

        $product = Product::query()
            ->with('taxRate')
            ->where('active', true)
            ->where('barcode', $barcode)
            ->firstOrFail();

        return new ProductResource($product);
    }
}
