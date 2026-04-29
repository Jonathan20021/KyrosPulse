<?php
/**
 * Limpieza diaria: rate limits expirados, password resets antiguos, sesiones, etc.
 * Cron: diario 3am => 0 3 * * *
 */
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

use App\Core\Database;

$deletedRL = Database::run("DELETE FROM rate_limits WHERE expires_at < NOW()")->rowCount();
$deletedPR = Database::run("DELETE FROM password_resets WHERE expires_at < DATE_SUB(NOW(), INTERVAL 7 DAY)")->rowCount();
$deletedEV = Database::run("DELETE FROM email_verifications WHERE expires_at < DATE_SUB(NOW(), INTERVAL 30 DAY) AND verified_at IS NULL")->rowCount();
$deletedNT = Database::run("DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY) AND read_at IS NOT NULL")->rowCount();
$deletedAL = Database::run("DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 365 DAY)")->rowCount();

echo "Cleanup:\n";
echo "  rate_limits expirados:        $deletedRL\n";
echo "  password_resets antiguos:     $deletedPR\n";
echo "  email_verifications viejas:   $deletedEV\n";
echo "  notifications leidas (>90d):  $deletedNT\n";
echo "  audit_logs (>1 ano):          $deletedAL\n";

// Auto-expirar tenants con trial vencido
$expired = Database::run(
    "UPDATE tenants SET status = 'expired'
     WHERE status = 'trial' AND trial_ends_at IS NOT NULL AND trial_ends_at < NOW()"
)->rowCount();
echo "  trials expirados:             $expired\n";
echo "Done.\n";
