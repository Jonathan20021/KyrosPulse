<?php
/**
 * Detecta conversaciones sin respuesta y dispara el evento "conversation.no_response".
 * Cron: cada 15 minutos. Programar en crontab como: zero-15-30-45 indicadores.
 */
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Core\Events;

// Conversaciones donde el ultimo mensaje es inbound y han pasado mas de 30 min
$rows = Database::fetchAll("
    SELECT c.*, ct.phone, ct.email, ct.first_name, ct.last_name
    FROM conversations c
    INNER JOIN contacts ct ON ct.id = c.contact_id
    WHERE c.status NOT IN ('resolved','closed')
      AND c.last_message_at IS NOT NULL
      AND c.last_message_at <= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
      AND (
        SELECT direction FROM messages
        WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1
      ) = 'inbound'
      AND NOT EXISTS (
        SELECT 1 FROM automation_logs al
        WHERE al.entity_type = 'conversation' AND al.entity_id = c.id
          AND al.created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
      )
");

echo "Conversaciones sin respuesta: " . count($rows) . "\n";

foreach ($rows as $c) {
    Events::dispatch('conversation.no_response', [
        'tenant_id'       => (int) $c['tenant_id'],
        'conversation_id' => (int) $c['id'],
        'contact_id'      => (int) $c['contact_id'],
        'contact_phone'   => (string) ($c['phone'] ?? ''),
        'contact_email'   => (string) ($c['email'] ?? ''),
        'first_name'      => (string) ($c['first_name'] ?? ''),
        'channel'         => (string) $c['channel'],
        'entity_type'     => 'conversation',
        'entity_id'       => (int) $c['id'],
    ]);
}
echo "Done.\n";
