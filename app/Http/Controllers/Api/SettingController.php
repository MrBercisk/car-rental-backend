<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;

class SettingController extends Controller
{
    public function index()
    {
        $settings = Setting::allAsArray();

        foreach (['site_logo', 'site_favicon'] as $fileKey) {
            if (! empty($settings[$fileKey])) {
                $settings[$fileKey] = asset('storage/' . $settings[$fileKey]);
            }
        }

        return response()->json(['data' => $settings]);
    }
}
