<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * GeminiConciergeService
 *
 * Versi Gemini (Google AI Studio, free tier) 
 * semua data tetap dari query ke Product & Booking
 * Setup:
 *   1. Ambil API key gratis di https://aistudio.google.com/apikey 
 *   2. .env: GEMINI_API_KEY=xxxx
 *   3. config/services.php: 'gemini' => ['api_key' => env('GEMINI_API_KEY')]
 */
class GeminiConciergeService
{
    private string $apiKey;
    private string $model = 'gemini-3.5-flash-lite'; 

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
    }

    /**
     * @param array  $history     Array of ['role' => 'user'|'model', 'parts' => [...]]
     *                            Kosongkan [] untuk percakapan baru.
     * @param string $userMessage Pesan baru dari user.
     */
    private const DAILY_SOFT_LIMIT = 450; // limit asli 500
    private const DAILY_CACHE_KEY = 'gemini_rpd_usage';

    public function chat(array $history, string $userMessage): array
    {
        if ($this->isDailyLimitReached()) {
            return [
                'reply' => 'Mohon Maaf, asisten AI kami sedang ramai digunakan hari ini. Silakan hubungi tim kami langsung lewat WhatsApp ya, kami bantu secepatnya terimakasih',
                'history' => $history,
                'limit_reached' => true,
            ];
        }
        $history[] = [
            'role' => 'user',
            'parts' => [['text' => $userMessage]],
        ];

        for ($i = 0; $i < 3; $i++) {
            $response = $this->callGemini($history);
            $this->incrementDailyUsage();

            if ($this->isDailyLimitReached()) {
                // klo limit stop
                break;
            }

            $candidate = $response['candidates'][0] ?? null;
            if (!$candidate) {
                Log::error('Gemini API: no candidate', $response);
                return ['reply' => 'Maaf, ada gangguan di sisi AI assistant.', 'history' => $history];
            }

            $parts = $candidate['content']['parts'] ?? [];
            $functionCalls = array_values(array_filter($parts, fn ($p) => isset($p['functionCall'])));

            // Simpan balasan model (termasuk functionCall parts) ke history
            $history[] = ['role' => 'model', 'parts' => $parts];

            if (empty($functionCalls)) {
                $text = implode("\n", array_map(fn ($p) => $p['text'] ?? '', $parts));
                return ['reply' => trim($text), 'history' => $history];
            }

            // Jalankan setiap function call, kumpulkan hasilnya jadi 1 giliran "user"
            $responseParts = [];
            foreach ($functionCalls as $part) {
                $call = $part['functionCall'];
                $output = $this->runTool($call['name'], $call['args'] ?? []);

                $functionResponse = [
                    'name' => $call['name'],
                    // response wajib object dengan gemini
                    'response' => ['result' => $output],
                ];

                // 'id' cuma disertakan kalau Gemini memang mengirimkannya di functionCall.
                // Beberapa versi API menolak field null eksplisit di sini.
                if (!empty($call['id'])) {
                    $functionResponse['id'] = $call['id'];
                }

                $responseParts[] = ['functionResponse' => $functionResponse];
            }

            $history[] = ['role' => 'user', 'parts' => $responseParts];
        }

        return [
            'reply' => 'Maaf, sepertinya butuh info lebih spesifik. Bisa dijelaskan ulang kebutuhannya?',
            'history' => $history,
        ];
    }
    private function isDailyLimitReached(): bool
    {
        return (int) Cache::get(self::DAILY_CACHE_KEY, 0) >= self::DAILY_SOFT_LIMIT;
    }

    private function incrementDailyUsage(): void
    {
        $key = self::DAILY_CACHE_KEY;
        $secondsUntilMidnightPT = now('America/Los_Angeles')->endOfDay()->diffInSeconds(now());

        if (!Cache::has($key)) {
            Cache::put($key, 1, $secondsUntilMidnightPT);
        } else {
            Cache::increment($key);
        }
    }

    /**
     *paksa semua array kosong jadi stdClass sebelum di-encode.
     */
    private function emptyArraysToObjects(mixed $data): mixed
    {
        if (is_array($data)) {
            if (empty($data)) {
                return new \stdClass();
            }
            foreach ($data as $key => $value) {
                $data[$key] = $this->emptyArraysToObjects($value);
            }
        }
        return $data;
    }

    private function callGemini(array $contents): array
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent";

        $response = Http::withHeaders([
            'x-goog-api-key' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])
        ->timeout(90)
        ->connectTimeout(10)
        ->post($url, $this->emptyArraysToObjects([
            'system_instruction' => [
                'parts' => [['text' => $this->systemPrompt()]],
            ],
            'contents' => $contents,
            'tools' => [
                ['functionDeclarations' => $this->toolDefinitions()],
            ],
        ]));

        if ($response->status() === 429) {
            Log::warning('Gemini API: rate limit tercapai', ['body' => $response->body()]);
            // kenai limit sebelum kena limit real
            Cache::put(
                self::DAILY_CACHE_KEY,
                self::DAILY_SOFT_LIMIT,
                now('America/Los_Angeles')->endOfDay()->diffInSeconds(now())
            );
            throw new \RuntimeException('RATE_LIMIT_EXCEEDED');
        }

        if ($response->failed()) {
            Log::error('Gemini API error', ['body' => $response->body()]);
            throw new \RuntimeException('Gagal menghubungi AI assistant (Gemini).');
        }

        return $response->json();
    }

    private function systemPrompt(): string
    {
        return <<<TXT
            Kamu adalah customer service rental mobil. Jawab singkat, ramah, dan
            dalam Bahasa Indonesia. Kalau butuh data harga, ketersediaan, atau daftar
            mobil, SELALU pakai function yang tersedia jangan pernah menebak angka
            harga atau status ketersediaan sendiri.Kalau user belum kasih tanggal sewa saat nanya ketersediaan/harga total ,tanyakan dulu tanggalnya. 
            Kalau user belum jelas mau sewa dengan supir atau lepas kunci, tanyakan itu juga sebelum menghitung harga final, karena ada biaya supir tambahan.
        TXT;
    }

    private function toolDefinitions(): array
    {
        return [
            [
                'name' => 'search_products',
                'description' => 'Cari mobil berdasarkan kategori, transmisi, bahan bakar, atau kapasitas kursi.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'category' => ['type' => 'STRING', 'description' => 'slug kategori, opsional'],
                        'transmission' => ['type' => 'STRING', 'enum' => ['manual', 'automatic']],
                        'fuel_type' => ['type' => 'STRING', 'enum' => ['bensin', 'diesel', 'listrik', 'hybrid']],
                        'seat_capacity' => ['type' => 'INTEGER'],
                    ],
                ],
            ],
            [
                'name' => 'check_availability',
                'description' => 'Cek apakah sebuah mobil (by slug) tersedia untuk rentang tanggal tertentu.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'slug' => ['type' => 'STRING'],
                        'start_date' => ['type' => 'STRING', 'description' => 'format YYYY-MM-DD'],
                        'end_date' => ['type' => 'STRING', 'description' => 'format YYYY-MM-DD'],
                    ],
                    'required' => ['slug', 'start_date', 'end_date'],
                ],
            ],
            [
                'name' => 'calculate_price',
                'description' => 'Hitung estimasi total harga sewa untuk sebuah mobil pada rentang tanggal tertentu, termasuk cek ketersediaan.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'slug' => ['type' => 'STRING'],
                        'start_date' => ['type' => 'STRING'],
                        'end_date' => ['type' => 'STRING'],
                        'with_driver' => ['type' => 'BOOLEAN', 'description' => 'true kalau user minta sewa dengan supir'],
                    ],
                    'required' => ['slug', 'start_date', 'end_date'],
                ],
            ],
        ];
    }

    //Tool implementations

    private function runTool(string $name, array $input): array
    {
        return match ($name) {
            'search_products' => $this->toolSearchProducts($input),
            'check_availability' => $this->toolCheckAvailability($input),
            'calculate_price' => $this->toolCalculatePrice($input),
            default => ['error' => "Tool '{$name}' tidak dikenal."],
        };
    }

    private function toolSearchProducts(array $input): array
    {
        $query = Product::query()->with(['category', 'packages.package'])->where('is_available', true);

        if (!empty($input['category'])) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $input['category']));
        }
        if (!empty($input['transmission'])) {
            $query->where('transmission', $input['transmission']);
        }
        if (!empty($input['fuel_type'])) {
            $query->where('fuel_type', $input['fuel_type']);
        }
        if (!empty($input['seat_capacity'])) {
            $query->where('seat_capacity', '>=', (int) $input['seat_capacity']);
        }

        return $query->limit(10)->get()->map(fn ($p) => [
            'slug' => $p->slug,
            'name' => $p->name,
            'transmission' => $p->transmission,
            'fuel_type' => $p->fuel_type,
            'seat_capacity' => $p->seat_capacity,
            'starting_price' => optional($p->packages->first())->price ?? null,
        ])->toArray();
    }

    private function toolCheckAvailability(array $input): array
    {
        $product = Product::where('slug', $input['slug'])->where('is_available', true)->first();
        if (!$product) {
            return ['error' => 'Mobil tidak ditemukan.'];
        }

        $from = Carbon::parse($input['start_date'])->startOfDay();
        $to = Carbon::parse($input['end_date'])->endOfDay();

        $unitIds = $product->units()->active()->pluck('id');
        if ($unitIds->isEmpty()) {
            return ['available' => false, 'reason' => 'Tidak ada unit aktif.'];
        }

        $bookedUnitCount = Booking::whereIn('product_unit_id', $unitIds)
            ->whereIn('status', ['pending', 'dp', 'lunas', 'confirmed'])
            ->where('start_date', '<=', $to)
            ->where('end_date', '>=', $from)
            ->distinct('product_unit_id')
            ->count('product_unit_id');

        return [
            'available' => $bookedUnitCount < $unitIds->count(),
            'total_units' => $unitIds->count(),
            'booked_units' => $bookedUnitCount,
        ];
    }

    private function toolCalculatePrice(array $input): array
    {
        $availability = $this->toolCheckAvailability($input);
        if (!empty($availability['error']) || empty($availability['available'])) {
            return $availability;
        }

        $withDriver = (bool) ($input['with_driver'] ?? false);

        $product = Product::with('packages.package')
            ->where('slug', $input['slug'])
            ->first();

        $days = Carbon::parse($input['start_date'])->diffInDays(Carbon::parse($input['end_date'])) + 1;

        // Pilih paket dengan durasi terpanjang yang masih <= total hari sewa.
        // Contoh: sewa 7 hari -> pilih "Mingguan" (7 hari), bukan "12 Jam".
        $matchedCarPackage = $product->packages
            ->filter(fn ($cp) => $cp->package !== null)
            ->sortByDesc(fn ($cp) => $this->packageDurationInDays($cp->package))
            ->first(fn ($cp) => $this->packageDurationInDays($cp->package) <= $days);

        if (!$matchedCarPackage) {
            return ['error' => 'Tidak ada paket harga yang cocok untuk durasi sewa ini.'];
        }

        $packageDays = $this->packageDurationInDays($matchedCarPackage->package);
        $basePrice = $matchedCarPackage->price;

        if ((float) $days === $packageDays) {
            // Durasi sewa persis sama dengan paket -> pakai harga paket apa adanya.
            $subtotal = $basePrice;
        } else {
            // Durasi lebih panjang dari paket yang ada (mis. 10 hari tapi paket
            // terpanjang cuma Mingguan/7 hari): kelipatan penuh pakai harga paket,
            // sisa hari dihitung proporsional dari harga paket itu.
            $multiplier = intdiv($days, (int) $packageDays);
            $remainderDays = $days % (int) $packageDays;
            $dailyRate = $basePrice / $packageDays;
            $subtotal = ($basePrice * $multiplier) + ($dailyRate * $remainderDays);
        }

        $driverFee = $withDriver ? ($matchedCarPackage->package->effective_driver_fee ?? 0) : 0;

        return [
            'available' => true,
            'package_name' => $matchedCarPackage->package->name,
            'days' => $days,
            'subtotal' => (int) round($subtotal),
            'with_driver' => $withDriver,
            'driver_fee' => $driverFee,
            'total_price' => (int) round($subtotal) + $driverFee,
        ];
    }

    private function packageDurationInDays($package): float
    {
        return match ($package->duration_unit) {
            'hour' => $package->duration_value / 24,
            'day' => $package->duration_value,
            'month' => $package->duration_value * 30,
            default => $package->duration_value,
        };
    }
}