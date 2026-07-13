<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;

class SettingController extends Controller
{
    /**
     * Mengembalikan seluruh pengaturan website dalam satu payload,
     * siap dipakai untuk header/footer/meta di frontend React.
     */
    public function index()
    {
        $settings = Setting::allAsArray();

        // Ubah key file (logo, favicon) menjadi full URL
        foreach (['site_logo', 'site_favicon'] as $fileKey) {
            if (! empty($settings[$fileKey])) {
                $settings[$fileKey] = asset('storage/' . $settings[$fileKey]);
            }
        }

        return response()->json(['data' => $settings]);
    }
}
