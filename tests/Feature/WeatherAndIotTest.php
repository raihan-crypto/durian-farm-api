<?php

namespace Tests\Feature;

use App\Jobs\FetchGardenWeatherJob;
use App\Models\Block;
use App\Models\Garden;
use App\Models\IotDevice;
use App\Models\Role;
use App\Models\User;
use App\Models\Village;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WeatherAndIotTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected string $adminToken;

    protected Garden $garden;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);

        $superAdminRole = Role::where('name', 'super_admin')->firstOrFail();
        $this->adminUser = User::create([
            'name' => 'Admin Cuaca IoT',
            'email' => 'admin.weather@durianfarm.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $this->adminUser->roles()->attach($superAdminRole->id);
        $this->adminToken = $this->adminUser->createToken('admin-token')->plainTextToken;

        $village = Village::create(['name' => 'Desa Cuaca']);
        $block = Block::create(['village_id' => $village->id, 'name' => 'Blok Cuaca']);
        $this->garden = Garden::create([
            'block_id' => $block->id,
            'name' => 'Kebun Berkoordinat',
            'latitude' => -6.9175,
            'longitude' => 107.6191,
        ]);
    }

    public function test_can_fetch_store_list_and_forecast_weather_with_caching(): void
    {
        Http::fake([
            'api.open-meteo.com/*' => Http::sequence()
                ->push([
                    'timezone' => 'Asia/Jakarta',
                    'current' => [
                        'time' => '2026-11-18T12:00',
                        'temperature_2m' => 29.4,
                        'relative_humidity_2m' => 76,
                        'rain' => 1.2,
                        'wind_speed_10m' => 8.5,
                        'weather_code' => 61,
                    ],
                ], 200)
                ->push([
                    'timezone' => 'Asia/Jakarta',
                    'daily' => [
                        'time' => ['2026-11-18', '2026-11-19'],
                        'weather_code' => [0, 61],
                        'temperature_2m_max' => [31.5, 29.8],
                        'temperature_2m_min' => [22.1, 21.8],
                        'precipitation_sum' => [0.0, 5.4],
                        'wind_speed_10m_max' => [10.2, 12.1],
                    ],
                ], 200),
        ]);

        // Fetch and store current weather
        $fetchResp = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/v1/weather/fetch', [
                'garden_id' => $this->garden->id,
            ]);

        $fetchResp->assertStatus(201)
            ->assertJsonPath('data.temperature_c', 29.4)
            ->assertJsonPath('data.humidity_percent', 76)
            ->assertJsonPath('data.weather_condition', 'Hujan Ringan')
            ->assertJsonPath('data.source', 'open-meteo');

        $this->assertDatabaseHas('weather_records', [
            'garden_id' => $this->garden->id,
            'weather_condition' => 'Hujan Ringan',
        ]);

        // Latest weather reads from cache / DB without hitting external API again
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson("/api/v1/weather/latest?garden_id={$this->garden->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.temperature_c', 29.4);

        // Forecast endpoint calls external API once and serves second call from cache
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson("/api/v1/weather/forecast?garden_id={$this->garden->id}&days=2")
            ->assertStatus(200)
            ->assertJsonCount(2, 'data.days')
            ->assertJsonPath('data.days.0.weather_condition', 'Cerah');

        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson("/api/v1/weather/forecast?garden_id={$this->garden->id}&days=2")
            ->assertStatus(200)
            ->assertJsonCount(2, 'data.days');

        Http::assertSentCount(2);

        // Queued fetch dispatches background job
        Queue::fake();
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/v1/weather/fetch', ['queue' => true])
            ->assertStatus(202);
        Queue::assertPushed(FetchGardenWeatherJob::class);
    }

    public function test_can_manage_monitoring_areas_iot_devices_and_ingest_authenticated_telemetry(): void
    {
        config(['durian.iot.ingest_key' => 'secret-iot-key-2026']);

        $areaResp = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/v1/monitoring-areas', [
                'garden_id' => $this->garden->id,
                'name' => 'Zona Sensor Utara',
                'latitude' => -6.917,
                'longitude' => 107.619,
            ]);
        $areaResp->assertStatus(201);
        $areaId = $areaResp->json('data.id');

        $deviceResp = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/v1/iot-devices', [
                'monitoring_area_id' => $areaId,
                'device_code' => 'SENS-DURIAN-01',
                'device_name' => 'Sensor Tanah & Iklim #1',
                'device_type' => 'soil_weather_combo',
                'manufacturer' => 'AgriSense',
            ]);
        $deviceResp->assertStatus(201)->assertJsonPath('data.status', 'active');
        $deviceId = $deviceResp->json('data.id');

        // Reject telemetry with wrong key
        $this->withHeader('X-IoT-Ingest-Key', 'wrong-key')
            ->postJson('/api/v1/iot/telemetry', [
                'device_code' => 'SENS-DURIAN-01',
                'temperature_c' => 27.5,
            ])
            ->assertStatus(401);

        // Accept valid telemetry and update device last_seen_at
        $this->withHeader('X-IoT-Ingest-Key', 'secret-iot-key-2026')
            ->postJson('/api/v1/iot/telemetry', [
                'device_code' => 'SENS-DURIAN-01',
                'temperature_c' => 27.5,
                'humidity_percent' => 81.2,
                'soil_moisture_percent' => 48.5,
                'battery_percent' => 94.0,
                'additional_data' => ['rssi' => -65],
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.device_id', $deviceId);

        $this->assertNotNull(IotDevice::findOrFail($deviceId)->last_seen_at);

        // Fetch telemetry history via admin API
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson("/api/v1/iot-devices/{$deviceId}/telemetry")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.items');

        // Cannot delete monitoring area that still has IoT devices
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->deleteJson("/api/v1/monitoring-areas/{$areaId}")
            ->assertStatus(422);
    }
}
