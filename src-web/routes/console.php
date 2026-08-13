<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('device:heartbeat-sim
    {--url= : Full heartbeat endpoint URL}
    {--box=BOX-01 : Device box_id}
    {--status=Available : Device status}
    {--door-status=Closed : Door status, Open or Closed}
    {--toggle-door : Alternate door status between Open and Closed on each request}
    {--doorlock=87 : Doorlock battery percent}
    {--device=92 : Device battery percent}
    {--lat=-6.914744 : Starting latitude}
    {--lng=107.609810 : Starting longitude}
    {--lat-step=0.000100 : Latitude delta per request}
    {--lng-step=0.000100 : Longitude delta per request}
    {--interval=5 : Seconds between heartbeats}
    {--count=0 : Total heartbeats, 0 means run forever}
    {--device-key=rahasia : X-Device-Key header}', function () {
    $url = $this->option('url') ?: rtrim(config('app.url') ?: 'http://127.0.0.1:8000', '/') . '/api/device/heartbeat';
    $boxId = (string) $this->option('box');
    $status = (string) $this->option('status');
    $doorStatus = (string) $this->option('door-status');
    $toggleDoor = (bool) $this->option('toggle-door');
    $doorlockBattery = (int) $this->option('doorlock');
    $deviceBattery = (int) $this->option('device');
    $lat = (float) $this->option('lat');
    $lng = (float) $this->option('lng');
    $latStep = (float) $this->option('lat-step');
    $lngStep = (float) $this->option('lng-step');
    $intervalSeconds = max(1, (int) $this->option('interval'));
    $count = max(0, (int) $this->option('count'));
    $deviceKey = (string) $this->option('device-key');

    $this->info('Starting heartbeat simulator');
    $this->line("URL: {$url}");
    $this->line("Box: {$boxId}");
    $this->line("Interval: {$intervalSeconds}s");
    $this->line("Door status: {$doorStatus}" . ($toggleDoor ? ' (toggle enabled)' : ''));
    $this->line($count === 0 ? 'Count: infinite' : "Count: {$count}");

    $iteration = 0;
    while ($count === 0 || $iteration < $count) {
        $payload = [
            'box_id' => $boxId,
            'battery_doorlock' => $doorlockBattery,
            'battery_device' => $deviceBattery,
            'status' => $status,
            'door_status' => $doorStatus,
            'lat' => round($lat, 6),
            'lng' => round($lng, 6),
        ];

        $startedAt = now()->format('Y-m-d H:i:s');

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'X-Device-Key' => $deviceKey,
                ])
                ->post($url, $payload);

            $this->line(sprintf(
                '[%s] #%d code=%d door=%s lat=%0.6f lng=%0.6f response=%s',
                $startedAt,
                $iteration + 1,
                $response->status(),
                $payload['door_status'],
                $payload['lat'],
                $payload['lng'],
                $response->body()
            ));
        } catch (\Throwable $exception) {
            $this->error(sprintf(
                '[%s] #%d door=%s request failed: %s',
                $startedAt,
                $iteration + 1,
                $payload['door_status'],
                $exception->getMessage()
            ));
        }

        $lat += $latStep;
        $lng += $lngStep;
        if ($toggleDoor) {
            $doorStatus = $doorStatus === 'Open' ? 'Closed' : 'Open';
        }
        $iteration++;

        if ($count !== 0 && $iteration >= $count) {
            break;
        }

        sleep($intervalSeconds);
    }

    $this->info('Heartbeat simulator finished');
})->purpose('Simulate automatic device heartbeat requests without ESP32');
