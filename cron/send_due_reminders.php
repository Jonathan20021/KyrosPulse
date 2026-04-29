<?php
/**
 * Envia recordatorios de tareas que vencen en los proximos 60 minutos.
 * Cron: minutos 0,30  =>  0,30 * * * * php cron/send_due_reminders.php
 */
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Models\Task;
use App\Services\ResendService;
use App\Services\WasapiService;

$tasks = Task::dueReminders(60);
echo "Tareas con recordatorio: " . count($tasks) . "\n";

foreach ($tasks as $t) {
    $tenantId = (int) $t['tenant_id'];

    if (!empty($t['remind_email']) && !empty($t['user_email'])) {
        $msg = "Recordatorio: tu tarea \"" . $t['title'] . "\" vence " . date('d/m H:i', strtotime((string) $t['due_at']));
        (new ResendService($tenantId))->sendEmail(
            (string) $t['user_email'],
            'Recordatorio de tarea - Kyros Pulse',
            "<p>Hola " . htmlspecialchars((string) $t['user_first']) . ",</p><p>$msg</p>"
        );
    }

    if (!empty($t['remind_whatsapp']) && !empty($t['contact_phone'])) {
        (new WasapiService($tenantId))->sendTextMessage(
            (string) $t['contact_phone'],
            "Recordatorio: " . $t['title']
        );
    }

    Database::update('tasks', ['remind_email' => 0, 'remind_whatsapp' => 0], ['id' => $t['id']]);
    echo "  Tarea {$t['id']} notificada\n";
}
echo "Done.\n";
