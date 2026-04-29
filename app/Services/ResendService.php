<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;

/**
 * Servicio de envio de correos transaccionales usando Resend (resend.com).
 */
final class ResendService
{
    public function __construct(private ?int $tenantId = null) {}

    private function apiKey(): string
    {
        if ($this->tenantId) {
            $row = Database::fetch("SELECT resend_api_key FROM tenants WHERE id = :id", ['id' => $this->tenantId]);
            if (!empty($row['resend_api_key'])) return (string) $row['resend_api_key'];
        }
        return (string) config('services.resend.api_key', '');
    }

    private function fromEmail(): string
    {
        if ($this->tenantId) {
            $row = Database::fetch("SELECT resend_from_email FROM tenants WHERE id = :id", ['id' => $this->tenantId]);
            if (!empty($row['resend_from_email'])) return (string) $row['resend_from_email'];
        }
        return (string) config('services.resend.from', 'no-reply@kyrosrd.com');
    }

    public function sendEmail(string $to, string $subject, string $html, string $text = '', array $opts = []): array
    {
        $apiKey = $this->apiKey();
        if ($apiKey === '') {
            $this->logEmail($to, $subject, null, 'failed', 'No hay API key Resend configurada.');
            return ['success' => false, 'error' => 'API key Resend no configurada.'];
        }

        $payload = [
            'from'    => $opts['from']    ?? $this->fromEmail(),
            'to'      => is_array($to) ? $to : [$to],
            'subject' => $subject,
            'html'    => $html,
        ];
        if ($text !== '') $payload['text'] = $text;
        if (!empty($opts['reply_to']))    $payload['reply_to']    = $opts['reply_to'];
        if (!empty($opts['cc']))          $payload['cc']          = $opts['cc'];
        if (!empty($opts['bcc']))         $payload['bcc']         = $opts['bcc'];
        if (!empty($opts['attachments'])) $payload['attachments'] = $opts['attachments'];

        $resp = HttpClient::post(
            (string) config('services.resend.api_url'),
            $payload,
            [
                'Authorization' => 'Bearer ' . $apiKey,
                'Accept'        => 'application/json',
            ],
            (int) config('services.resend.timeout', 20)
        );

        $external = $resp['body']['id'] ?? null;
        $status   = $resp['success'] ? 'sent' : 'failed';
        $error    = $resp['success'] ? null : ($resp['body']['message'] ?? $resp['error'] ?? 'Error desconocido');

        $this->logEmail(is_array($to) ? implode(',', $to) : $to, $subject, $external, $status, $error, $opts['template'] ?? null);

        return $resp;
    }

    public function sendVerificationEmail(array $user, string $verifyUrl): array
    {
        $name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        $html = $this->renderTemplate('verify', [
            'name'       => $name ?: 'Usuario',
            'verify_url' => $verifyUrl,
        ]);
        return $this->sendEmail(
            (string) $user['email'],
            'Verifica tu correo - Kyros Pulse',
            $html,
            "Hola $name, verifica tu correo aqui: $verifyUrl",
            ['template' => 'verify']
        );
    }

    public function sendPasswordReset(array $user, string $resetUrl): array
    {
        $name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        $html = $this->renderTemplate('password_reset', [
            'name'      => $name ?: 'Usuario',
            'reset_url' => $resetUrl,
        ]);
        return $this->sendEmail(
            (string) $user['email'],
            'Recupera tu contrasena - Kyros Pulse',
            $html,
            "Restablece tu contrasena: $resetUrl",
            ['template' => 'password_reset']
        );
    }

    public function sendInvitation(array $user, string $inviteUrl, string $companyName): array
    {
        $name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        $html = $this->renderTemplate('invitation', [
            'name'       => $name ?: 'Colaborador',
            'invite_url' => $inviteUrl,
            'company'    => $companyName,
        ]);
        return $this->sendEmail(
            (string) $user['email'],
            "Invitacion a unirte a $companyName en Kyros Pulse",
            $html,
            "Has sido invitado a $companyName: $inviteUrl",
            ['template' => 'invitation']
        );
    }

    private function renderTemplate(string $template, array $data): string
    {
        $brand    = e((string) config('app.name', 'Kyros Pulse'));
        $year     = date('Y');
        $primary  = '#7C3AED';
        $bg       = '#0B1020';

        $title = match ($template) {
            'verify'         => 'Verifica tu correo',
            'password_reset' => 'Restablece tu contrasena',
            'invitation'     => 'Has sido invitado',
            default          => $brand,
        };

        $body = match ($template) {
            'verify' => "
                <p>Hola <strong>" . e($data['name']) . "</strong>,</p>
                <p>Te damos la bienvenida a <strong>$brand</strong>. Para activar tu cuenta y comenzar a usar la plataforma, verifica tu correo haciendo click en el boton:</p>
                <p style='text-align:center;margin:32px 0'>
                    <a href='" . e($data['verify_url']) . "' style='display:inline-block;padding:14px 32px;background:$primary;color:#fff;text-decoration:none;border-radius:10px;font-weight:600'>Verificar correo</a>
                </p>
                <p style='font-size:13px;color:#666'>Si el boton no funciona, copia y pega este enlace en tu navegador:<br><a href='" . e($data['verify_url']) . "'>" . e($data['verify_url']) . "</a></p>
            ",
            'password_reset' => "
                <p>Hola <strong>" . e($data['name']) . "</strong>,</p>
                <p>Recibimos una solicitud para restablecer la contrasena de tu cuenta en <strong>$brand</strong>. Si fuiste tu, haz click en el boton para crear una nueva contrasena:</p>
                <p style='text-align:center;margin:32px 0'>
                    <a href='" . e($data['reset_url']) . "' style='display:inline-block;padding:14px 32px;background:$primary;color:#fff;text-decoration:none;border-radius:10px;font-weight:600'>Restablecer contrasena</a>
                </p>
                <p style='font-size:13px;color:#666'>Este enlace expira en 60 minutos. Si no solicitaste este cambio, ignora este correo.</p>
            ",
            'invitation' => "
                <p>Hola <strong>" . e($data['name']) . "</strong>,</p>
                <p>Has sido invitado a unirte al equipo <strong>" . e($data['company']) . "</strong> en <strong>$brand</strong>.</p>
                <p style='text-align:center;margin:32px 0'>
                    <a href='" . e($data['invite_url']) . "' style='display:inline-block;padding:14px 32px;background:$primary;color:#fff;text-decoration:none;border-radius:10px;font-weight:600'>Aceptar invitacion</a>
                </p>
            ",
            default => '<p>Notificacion de ' . $brand . '</p>',
        };

        return "<!DOCTYPE html>
<html>
<head><meta charset='UTF-8'><title>$title</title></head>
<body style='margin:0;padding:0;background:#F8FAFC;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif'>
  <table role='presentation' width='100%' cellpadding='0' cellspacing='0' style='padding:40px 16px'>
    <tr><td align='center'>
      <table role='presentation' width='600' cellpadding='0' cellspacing='0' style='max-width:600px;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 20px 50px rgba(0,0,0,.08)'>
        <tr><td style='background:linear-gradient(135deg,$bg 0%,$primary 100%);padding:32px 40px;text-align:center'>
          <h1 style='margin:0;color:#fff;font-size:24px;font-weight:700;letter-spacing:-.5px'>$brand</h1>
        </td></tr>
        <tr><td style='padding:40px;color:#1f2937;font-size:15px;line-height:1.6'>
          <h2 style='margin:0 0 16px;font-size:22px;color:#0B1020'>$title</h2>
          $body
        </td></tr>
        <tr><td style='background:#F8FAFC;padding:24px;text-align:center;color:#6b7280;font-size:12px'>
          &copy; $year $brand. Todos los derechos reservados.
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>";
    }

    private function logEmail(string $to, string $subject, ?string $externalId, string $status, ?string $error = null, ?string $template = null): void
    {
        try {
            Database::insert('email_logs', [
                'tenant_id'     => $this->tenantId,
                'to_email'      => $to,
                'from_email'    => $this->fromEmail(),
                'subject'       => $subject,
                'template'      => $template,
                'status'        => $status,
                'external_id'   => $externalId,
                'error_message' => $error,
                'sent_at'       => $status === 'sent' ? date('Y-m-d H:i:s') : null,
            ]);
        } catch (\Throwable $e) {
            Logger::error('No se pudo registrar log de email', ['msg' => $e->getMessage()]);
        }
    }
}
