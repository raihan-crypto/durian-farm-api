<?php

namespace App\Services;

use App\Models\Garden;
use App\Models\MonitoringArea;
use App\Models\WeatherRecord;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WeatherService
{
    /**
     * WMO Weather interpretation code labels in Indonesian (Open-Meteo standard).
     *
     * @var array<int, string>
     */
    private const WMO_CONDITIONS = [
        0 => 'Cerah',
        1 => 'Cerah Berawan',
        2 => 'Berawan Sebagian',
        3 => 'Mendung',
        45 => 'Berkabut',
        48 => 'Kabut Tebal',
        51 => 'Gerimis Ringan',
        53 => 'Gerimis Sedang',
        55 => 'Gerimis Lebat',
        61 => 'Hujan Ringan',
        63 => 'Hujan Sedang',
        65 => 'Hujan Lebat',
        80 => 'Hujan Lokal Ringan',
        81 => 'Hujan Lokal Sedang',
        82 => 'Hujan Lokal Lebat',
        95 => 'Badai Petir',
        96 => 'Badai Petir & Hujan Es',
        99 => 'Badai Petir Berat',
    ];

    /**
     * Fetch current weather from external provider and store it in weather_records (SRS 13.3).
     */
    public function fetchAndStoreForGarden(Garden $garden, ?MonitoringArea $area = null): WeatherRecord
    {
        $latitude = $area?->latitude ?? $garden->latitude;
        $longitude = $area?->longitude ?? $garden->longitude;

        if ($latitude === null || $longitude === null) {
            throw new RuntimeException('Koordinat latitude dan longitude belum diatur untuk lokasi ini');
        }

        $baseUrl = (string) config('durian.weather.base_url', 'https://api.open-meteo.com/v1/forecast');
        $timeout = (int) config('durian.weather.timeout_seconds', 10);

        try {
            $response = Http::timeout($timeout)
                ->retry(2, 200)
                ->get($baseUrl, [
                    'latitude' => (float) $latitude,
                    'longitude' => (float) $longitude,
                    'current' => 'temperature_2m,relative_humidity_2m,rain,wind_speed_10m,weather_code',
                    'timezone' => 'auto',
                ]);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Gagal menghubungi layanan cuaca eksternal: '.$e->getMessage(), 0, $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException('Layanan cuaca eksternal mengembalikan status '.$response->status());
        }

        $payload = $response->json();
        $current = is_array($payload['current'] ?? null) ? $payload['current'] : [];
        $weatherCode = isset($current['weather_code']) ? (int) $current['weather_code'] : null;

        $record = WeatherRecord::create([
            'monitoring_area_id' => $area?->id,
            'garden_id' => $garden->id,
            'recorded_at' => $current['time'] ?? now(),
            'temperature_c' => isset($current['temperature_2m']) ? (float) $current['temperature_2m'] : null,
            'humidity_percent' => isset($current['relative_humidity_2m']) ? (float) $current['relative_humidity_2m'] : null,
            'rainfall_mm' => isset($current['rain']) ? (float) $current['rain'] : 0.0,
            'wind_speed' => isset($current['wind_speed_10m']) ? (float) $current['wind_speed_10m'] : null,
            'weather_condition' => $weatherCode !== null
                ? (self::WMO_CONDITIONS[$weatherCode] ?? "Kode Cuaca {$weatherCode}")
                : 'Tidak Diketahui',
            'source' => (string) config('durian.weather.provider', 'open-meteo'),
            'raw_data' => $payload,
        ]);

        $cacheKey = $this->latestCacheKey($garden->id, $area?->id);
        Cache::put($cacheKey, $record->id, now()->addMinutes((int) config('durian.weather.cache_ttl_minutes', 30)));

        return $record;
    }

    /**
     * Get cached daily weather forecast for coordinates (SRS 13.4).
     *
     * @return array<string, mixed>
     */
    public function getForecast(float $latitude, float $longitude, int $days = 5): array
    {
        $days = min(max($days, 1), 14);
        $ttl = max((int) config('durian.weather.cache_ttl_minutes', 30), 1);
        $cacheKey = sprintf('weather:forecast:%.4f:%.4f:%d', $latitude, $longitude, $days);

        return Cache::remember($cacheKey, now()->addMinutes($ttl), function () use ($latitude, $longitude, $days) {
            $baseUrl = (string) config('durian.weather.base_url', 'https://api.open-meteo.com/v1/forecast');
            $timeout = (int) config('durian.weather.timeout_seconds', 10);

            try {
                $response = Http::timeout($timeout)
                    ->retry(2, 200)
                    ->get($baseUrl, [
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'daily' => 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_sum,wind_speed_10m_max',
                        'forecast_days' => $days,
                        'timezone' => 'auto',
                    ]);
            } catch (ConnectionException $e) {
                throw new RuntimeException('Gagal menghubungi layanan prakiraan cuaca: '.$e->getMessage(), 0, $e);
            }

            if (! $response->successful()) {
                throw new RuntimeException('Layanan prakiraan cuaca mengembalikan status '.$response->status());
            }

            $payload = $response->json();
            $daily = is_array($payload['daily'] ?? null) ? $payload['daily'] : [];
            $dates = $daily['time'] ?? [];

            $forecastDays = [];
            foreach ($dates as $index => $date) {
                $code = isset($daily['weather_code'][$index]) ? (int) $daily['weather_code'][$index] : null;
                $forecastDays[] = [
                    'date' => $date,
                    'weather_condition' => $code !== null ? (self::WMO_CONDITIONS[$code] ?? "Kode Cuaca {$code}") : 'Tidak Diketahui',
                    'temperature_max_c' => isset($daily['temperature_2m_max'][$index]) ? (float) $daily['temperature_2m_max'][$index] : null,
                    'temperature_min_c' => isset($daily['temperature_2m_min'][$index]) ? (float) $daily['temperature_2m_min'][$index] : null,
                    'precipitation_sum_mm' => isset($daily['precipitation_sum'][$index]) ? (float) $daily['precipitation_sum'][$index] : null,
                    'wind_speed_max' => isset($daily['wind_speed_10m_max'][$index]) ? (float) $daily['wind_speed_10m_max'][$index] : null,
                ];
            }

            return [
                'provider' => (string) config('durian.weather.provider', 'open-meteo'),
                'latitude' => $latitude,
                'longitude' => $longitude,
                'timezone' => $payload['timezone'] ?? 'UTC',
                'days' => $forecastDays,
            ];
        });
    }

    public function latestCacheKey(string $gardenId, ?string $monitoringAreaId = null): string
    {
        return "weather:latest:{$gardenId}:".($monitoringAreaId ?? 'garden');
    }
}
