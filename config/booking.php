<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mode Booking
    |--------------------------------------------------------------------------
    |
    | Menentukan alur booking yang aktif di frontend publik:
    |
    | 'whatsapp_only'   -> Tombol WA langsung, tanpa form, tanpa simpan ke DB.
    |                      (paket Basic & Plus)
    | 'whatsapp_form'   -> Form isi tanggal/nama dulu, submit -> generate pesan
    |                      WA otomatis. Boleh disimpan ke bookings (status
    |                      pending) atau tidak, tergantung SAVE_ON_FORM_SUBMIT.
    |                      (paket Standard)
    | 'calendar_booking'-> Kalender ketersediaan aktif, form booking penuh,
    |                      status pending/dp/lunas, opsional payment gateway.
    |                      (paket Business)
    |
    */
    'mode' => env('BOOKING_MODE', 'whatsapp_only'),

    /*
    |--------------------------------------------------------------------------
    | Kalender Ketersediaan
    |--------------------------------------------------------------------------
    |
    | Kalau true, endpoint /products/{slug}/availability aktif dan frontend
    | menampilkan kalender dengan tanggal yang sudah ter-booking di-disable.
    |
    */
    'calendar_enabled' => env('BOOKING_CALENDAR', false),

    /*
    |--------------------------------------------------------------------------
    | Simpan ke Database saat Mode whatsapp_form
    |--------------------------------------------------------------------------
    |
    | Hanya relevan kalau mode = 'whatsapp_form'. Kalau true, data form ikut
    | disimpan ke tabel bookings (status pending, source form) sebelum
    | redirect ke WA -- berguna kalau mau tetap punya rekap walau belum
    | pakai kalender penuh. Kalau false, data cuma dipakai buat generate
    | teks WA, tidak disimpan sama sekali.
    |
    */
    'save_on_form_submit' => env('BOOKING_SAVE_ON_FORM_SUBMIT', false),

    /*
    |--------------------------------------------------------------------------
    | Status Pembayaran (DP / Lunas)
    |--------------------------------------------------------------------------
    |
    | Kalau true, admin panel & frontend menampilkan tracking status
    | pembayaran (pending/dp/lunas), bukan cuma pending/confirmed.
    |
    */
    'payment_status_enabled' => env('BOOKING_PAYMENT_STATUS', false),

    /*
    |--------------------------------------------------------------------------
    | Upload Bukti Bayar Manual
    |--------------------------------------------------------------------------
    |
    | Kalau true, customer/admin bisa upload bukti transfer manual
    | (payment_proof_path). Independen dari payment gateway.
    |
    */
    'payment_proof_enabled' => env('BOOKING_PAYMENT_PROOF', false),

    /*
    |--------------------------------------------------------------------------
    | Payment Gateway
    |--------------------------------------------------------------------------
    |
    | Aktifkan integrasi gateway otomatis (Midtrans/Doku/Xendit dst).
    | 'driver' menentukan class handler mana yang dipakai -- akan dibuat
    | di tahap integrasi gateway nanti.
    |
    */
    'gateway_enabled' => env('BOOKING_GATEWAY_ENABLED', false),
    'gateway_driver' => env('BOOKING_GATEWAY_DRIVER', null), // 'midtrans' | 'doku' | 'xendit'

    /*
    |--------------------------------------------------------------------------
    | Nomor WhatsApp Tujuan
    |--------------------------------------------------------------------------
    |
    | Dipakai untuk generate link wa.me di semua mode. Format: 62xxx
    | tanpa tanda + atau spasi.
    |
    */
    'whatsapp_number' => env('BOOKING_WHATSAPP_NUMBER', ''),

    /*
    |--------------------------------------------------------------------------
    | Auto-lock Booking Lama
    |--------------------------------------------------------------------------
    |
    | Jumlah hari setelah end_date sebelum booking otomatis di-lock
    | (locked_at terisi) oleh scheduled command, supaya data closing
    | tidak bisa diedit lagi lewat form admin. Null = tidak auto-lock.
    |
    */
    'auto_lock_after_days' => env('BOOKING_AUTO_LOCK_DAYS', null),

];