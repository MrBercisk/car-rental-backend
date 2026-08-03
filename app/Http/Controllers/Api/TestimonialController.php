<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TestimonialResource;
use App\Models\Testimonial;
use Illuminate\Http\Request;

class TestimonialController extends Controller
{
    public function index()
    {
        $testimonials = Testimonial::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return TestimonialResource::collection($testimonials);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'role' => ['nullable', 'string', 'max:100'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'message' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $testimonial = Testimonial::create([
            'name' => trim($data['name']),
            'role' => $data['role'] ?? 'Pelanggan',
            'rating' => $data['rating'],
            'message' => trim($data['message']),
            'is_active' => true,
            'sort_order' => (Testimonial::max('sort_order') ?? 0) + 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Testimoni berhasil dikirim.',
            'data' => new TestimonialResource($testimonial),
        ], 201);
    }
}