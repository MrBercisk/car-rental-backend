<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        // eager load
        $query = Product::query()
            ->with(['category', 'packages.package'])
            ->where('is_available', true);

        if ($request->filled('category')) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $request->category));
        }

        if ($request->filled('transmission')) {
            $query->where('transmission', $request->transmission);
        }

        if ($request->filled('featured')) {
            $query->where('is_featured', true);
        }
        
        if ($request->filled('search')) {
            $search = str_replace(['%', '_'], ['\%', '\_'], $request->search);
            $query->whereRaw('name LIKE ? ESCAPE ?', ['%' . $search . '%', '\\']);
        }

        // Cegah query berat batas per_page maksimal 50, minimal 1.
        $perPage = (int) $request->get('per_page', 12);
        $perPage = max(1, min($perPage, 50));

        $products = $query->orderBy('sort_order')->paginate($perPage);

        return ProductResource::collection($products);
    }

    public function show(string $slug)
    {
        // eager load
        $product = Product::with(['category', 'packages.package'])
            ->where('slug', $slug)
            ->where('is_available', true)
            ->firstOrFail();

        return new ProductResource($product);
    }
}