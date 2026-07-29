<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Notifikasi Email
    |--------------------------------------------------------------------------
    |
    | Dikirim ke admin tiap kali ada booking baru masuk dari form website.
    |
    */
    'admin_email_enabled' => env('ADMIN_EMAIL_NOTIF_ENABLED', true),
    'admin_email' => env('ADMIN_NOTIFICATION_EMAIL'),

    /*
    |--------------------------------------------------------------------------
    | Notifikasi WhatsApp (belum diaktifkan)
    |--------------------------------------------------------------------------
    |
    | Sengaja disiapkan tapi belum dipakai -- nanti kalau mau aktifkan,
    | tinggal tambah kembali WhatsAppChannel + driver gateway (Fonnte/Wablas)
    | dan set admin_whatsapp_enabled = true di .env, tanpa perlu ubah
    | struktur notifikasi email yang sudah ada.
    |
    */
    'admin_whatsapp_enabled' => env('ADMIN_WHATSAPP_NOTIF_ENABLED', false),

];