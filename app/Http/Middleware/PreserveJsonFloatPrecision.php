<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreserveJsonFloatPrecision
{
    /**
     * Supaya float bulat (mis. 25.0) tidak dibulatkan jadi integer (25)
     * saat di-encode ke JSON response -- default PHP json_encode()
     * membuang ".0" dari float yang nilainya bulat.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response instanceof JsonResponse) {
            $response->setEncodingOptions(
                $response->getEncodingOptions() | JSON_PRESERVE_ZERO_FRACTION
            );
        }

        return $response;
    }
}