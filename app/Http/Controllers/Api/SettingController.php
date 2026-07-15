<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;

class SettingController extends Controller
{
    // field yang terekspos public
    private const PUBLIC_KEYS = [
        'site_name',
        'site_tagline',
        'site_logo',
        'site_favicon',
        'site_description',
        'contact_phone',
        'contact_email',
        'contact_address',
        'contact_maps_url',
        'contact_business_hours',
        'social_instagram',
        'social_facebook',
        'social_tiktok',
        'social_youtube',
        'seo_meta_title',
        'seo_meta_description',
        'seo_og_image',
        'seo_google_verification',
        'seo_facebook_app_id',
        'driver_surcharge',
    ];

    public function index()
    {
        $allSettings = Setting::allAsArray();

        // Whitelist cuma ambil key yang eksplisit diizinkan, sisanya dibuang.
        $settings = array_intersect_key($allSettings, array_flip(self::PUBLIC_KEYS));

        foreach (['site_logo', 'site_favicon'] as $fileKey) {
            if (! empty($settings[$fileKey])) {
                $settings[$fileKey] = asset('storage/' . $settings[$fileKey]);
            }
        }

        return response()->json(['data' => $settings]);
    }
}