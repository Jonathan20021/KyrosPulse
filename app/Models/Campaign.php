<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Campaign
{
    public static function create(array $data): int
    {
        return Database::insert('campaigns', $data);
    }

    public static function findById(int $tenantId, int $id): ?array
    {
        return Database::fetch(
            "SELECT * FROM campaigns WHERE id = :id AND tenant_id = :t",
            ['id' => $id, 't' => $tenantId]
        );
    }

    public static function listForTenant(int $tenantId, int $limit = 50): array
    {
        return Database::fetchAll(
            "SELECT * FROM campaigns WHERE tenant_id = :t ORDER BY created_at DESC LIMIT $limit",
            ['t' => $tenantId]
        );
    }

    public static function update(int $tenantId, int $id, array $data): int
    {
        return Database::update('campaigns', $data, ['id' => $id, 'tenant_id' => $tenantId]);
    }

    public static function delete(int $tenantId, int $id): int
    {
        Database::delete('campaign_recipients', ['campaign_id' => $id]);
        return Database::delete('campaigns', ['id' => $id, 'tenant_id' => $tenantId]);
    }

    public static function buildAudience(int $tenantId, array $filters): array
    {
        $where  = ['c.tenant_id = :t', 'c.deleted_at IS NULL'];
        $params = ['t' => $tenantId];

        if (!empty($filters['status']))   { $where[] = 'c.status = :s';   $params['s']  = $filters['status']; }
        if (!empty($filters['source']))   { $where[] = 'c.source = :sr';  $params['sr'] = $filters['source']; }
        if (!empty($filters['country']))  { $where[] = 'c.country = :co'; $params['co'] = $filters['country']; }
        if (!empty($filters['has_whatsapp'])) { $where[] = 'c.whatsapp IS NOT NULL AND c.whatsapp != \'\''; }
        if (!empty($filters['tag'])) {
            $where[] = 'EXISTS (SELECT 1 FROM contact_tags ct INNER JOIN tags tg ON tg.id = ct.tag_id WHERE ct.contact_id = c.id AND tg.name = :tg)';
            $params['tg'] = $filters['tag'];
        }

        return Database::fetchAll(
            "SELECT DISTINCT c.id, c.first_name, c.last_name, c.email, c.phone, c.whatsapp
             FROM contacts c WHERE " . implode(' AND ', $where) . "
             LIMIT 5000",
            $params
        );
    }

    public static function addRecipients(int $tenantId, int $campaignId, array $contacts): int
    {
        $count = 0;
        foreach ($contacts as $c) {
            try {
                Database::insert('campaign_recipients', [
                    'tenant_id'   => $tenantId,
                    'campaign_id' => $campaignId,
                    'contact_id'  => (int) $c['id'],
                    'phone'       => $c['whatsapp'] ?: ($c['phone'] ?? null),
                    'email'       => $c['email'] ?? null,
                    'status'      => 'pending',
                ]);
                $count++;
            } catch (\Throwable) {
                // Skip duplicados o errores
            }
        }
        Database::run("UPDATE campaigns SET total_recipients = :n WHERE id = :id", ['n' => $count, 'id' => $campaignId]);
        return $count;
    }

    public static function pendingRecipients(int $campaignId, int $limit = 100): array
    {
        return Database::fetchAll(
            "SELECT cr.*, c.first_name, c.last_name, c.company
             FROM campaign_recipients cr
             INNER JOIN contacts c ON c.id = cr.contact_id
             WHERE cr.campaign_id = :c AND cr.status = 'pending'
             LIMIT $limit",
            ['c' => $campaignId]
        );
    }

    public static function recordSend(int $recipientId, bool $success, ?string $externalId = null, ?string $error = null): void
    {
        $status = $success ? 'sent' : 'failed';
        Database::update('campaign_recipients', [
            'status'        => $status,
            'external_id'   => $externalId,
            'error_message' => $error,
            'sent_at'       => $success ? date('Y-m-d H:i:s') : null,
        ], ['id' => $recipientId]);
    }

    public static function refreshMetrics(int $campaignId): void
    {
        $row = Database::fetch(
            "SELECT
                SUM(status='sent')        AS sent,
                SUM(status='delivered')   AS delivered,
                SUM(status='read')        AS readd,
                SUM(status='replied')     AS replied,
                SUM(status='failed')      AS failed
             FROM campaign_recipients WHERE campaign_id = :c",
            ['c' => $campaignId]
        );
        Database::update('campaigns', [
            'total_sent'      => (int) ($row['sent']      ?? 0),
            'total_delivered' => (int) ($row['delivered'] ?? 0),
            'total_read'      => (int) ($row['readd']     ?? 0),
            'total_replied'   => (int) ($row['replied']   ?? 0),
            'total_failed'    => (int) ($row['failed']    ?? 0),
        ], ['id' => $campaignId]);
    }
}
