<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GeminiConciergeService;
use Illuminate\Http\Request;

class GeminiConciergeController extends Controller
{
    public function chat(Request $request, GeminiConciergeService $service)
    {
        $request->validate([
            'message' => 'required|string|max:1000|min:2',
            'history' => 'nullable|array|max:40',
        ]);

        try {
            $result = $service->chat($request->input('history', []), $request->input('message'));
        } catch (\RuntimeException $e) {
            return response()->json([
                'reply' => 'Mohon Maaf, layanan AI sedang sibuk. Silakan coba lagi sebentar lagi atau hubungi kami lewat WhatsApp.',
                'history' => $request->input('history', []),
            ]);
        }

        return response()->json($result);
}
}