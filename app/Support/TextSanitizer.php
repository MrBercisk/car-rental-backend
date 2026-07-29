<?php

namespace App\Support;

class TextSanitizer
{
    /**
     * Pastikan string valid sebagai UTF-8, apa pun kondisi byte aslinya.
     *
     * Alurnya:
     * 1. Kalau sudah valid UTF-8, cuma buang karakter kontrol non-printable.
     * 2. Kalau bukan, coba deteksi encoding asli (Windows-1252/ISO-8859-1/ASCII)
     *    lalu convert paksa ke UTF-8.
     * 3. Kalau deteksi gagal atau hasil convert masih invalid, buang paksa
     *    semua byte yang tidak valid (lebih baik kehilangan sedikit karakter
     *    aneh daripada export gagal total).
     */
    public static function clean(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (! mb_check_encoding($value, 'UTF-8')) {
            $detected = mb_detect_encoding($value, ['Windows-1252', 'ISO-8859-1', 'ASCII'], true);

            $value = $detected
                ? mb_convert_encoding($value, 'UTF-8', $detected)
                : (@iconv('UTF-8', 'UTF-8//IGNORE', $value) ?: '');

            if (! mb_check_encoding($value, 'UTF-8')) {
                $value = @iconv('UTF-8', 'UTF-8//IGNORE', $value) ?: preg_replace('/[\x80-\xFF]/', '', $value);
            }
        }

        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value) ?? '';
    }
}