<?php
/**
 * Procesa campanas programadas que ya alcanzaron su scheduled_at.
 * Ejecutar cada minuto:
 *   * * * * * php /ruta/cron/process_campaigns.php
 */
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Core\Tenant;
use App\Models\Campaign;
use App\Services\WasapiService;

$campaigns = Database::fetchAll(
    "SELECT * FROM campaigns
     WHERE status = 'scheduled' AND scheduled_at IS NOT NULL AND scheduled_at <= NOW()
     LIMIT 5"
);

echo "Campanas a procesar: " . count($campaigns) . "\n";

foreach ($campaigns as $c) {
    $tenantId = (int) $c['tenant_id'];
    Tenant::setCurrent(\App\Models\Tenant::findById($tenantId));

    Database::update('campaigns', [
        'status'     => 'sending',
        'started_at' => date('Y-m-d H:i:s'),
    ], ['id' => $c['id']]);

    $svc = new WasapiService($tenantId);
    $sent = 0;

    while (true) {
        $batch = Campaign::pendingRecipients((int) $c['id'], 50);
        if (empty($batch)) break;
        foreach ($batch as $r) {
            $phone = (string) ($r['phone'] ?? '');
            if ($phone === '') {
                Campaign::recordSend((int) $r['id'], false, null, 'Sin telefono');
                continue;
            }
            $resp = $svc->sendTextMessage($phone, (string) $c['message']);
            Campaign::recordSend(
                (int) $r['id'],
                !empty($resp['success']),
                $resp['body']['id'] ?? null,
                !empty($resp['success']) ? null : ($resp['error'] ?? null)
            );
            $sent++;
            usleep(80000);
        }
    }

    Database::update('campaigns', [
        'status'      => 'completed',
        'finished_at' => date('Y-m-d H:i:s'),
    ], ['id' => $c['id']]);
    Campaign::refreshMetrics((int) $c['id']);
    echo "  Campana {$c['id']} ({$c['name']}): $sent mensajes enviados\n";
}
echo "Done.\n";
