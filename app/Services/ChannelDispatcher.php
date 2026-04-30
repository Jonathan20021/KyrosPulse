<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\WhatsappChannel;

/**
 * Selecciona el servicio adecuado segun el canal de whatsapp configurado.
 * Permite enviar mensajes sin saber el proveedor real (Wasapi/Cloud/Twilio).
 */
final class ChannelDispatcher
{
    public function __construct(private int $tenantId) {}

    /**
     * Resuelve el canal preferido para una conversacion.
     * Prioridad:
     *   1. conversations.channel_id explicito
     *   2. canal default del tenant
     *   3. fallback a configuracion legacy en tenants.wasapi_*
     */
    public function resolveChannel(?int $channelId = null): ?array
    {
        if ($channelId) {
            $ch = WhatsappChannel::findById($this->tenantId, $channelId);
            if ($ch) return $ch;
        }
        return WhatsappChannel::findDefault($this->tenantId);
    }

    public function sendText(string $phone, string $message, ?int $channelId = null): array
    {
        $channel = $this->resolveChannel($channelId);

        if (!$channel) {
            // Fallback: legacy WasapiService
            return (new WasapiService($this->tenantId))->sendTextMessage($phone, $message);
        }

        return match ($channel['provider']) {
            'cloud'  => (new WhatsappCloudService($this->tenantId, $channel))->sendTextMessage($phone, $message),
            'wasapi' => (new WasapiService($this->tenantId, $channel))->sendTextMessage($phone, $message),
            default  => ['success' => false, 'error' => 'Proveedor "' . $channel['provider'] . '" no soportado para envio.'],
        };
    }

    public function sendMedia(string $phone, string $mediaUrl, string $caption = '', string $type = 'image', ?int $channelId = null): array
    {
        $channel = $this->resolveChannel($channelId);
        if (!$channel) return (new WasapiService($this->tenantId))->sendMediaMessage($phone, $mediaUrl, $caption, $type);

        return match ($channel['provider']) {
            'cloud'  => (new WhatsappCloudService($this->tenantId, $channel))->sendMediaMessage($phone, $mediaUrl, $caption, $type),
            'wasapi' => (new WasapiService($this->tenantId, $channel))->sendMediaMessage($phone, $mediaUrl, $caption, $type),
            default  => ['success' => false, 'error' => 'Proveedor no soportado.'],
        };
    }

    public function sendTemplate(string $phone, string $templateName, array $variables = [], ?int $channelId = null, string $language = 'es'): array
    {
        $channel = $this->resolveChannel($channelId);
        if (!$channel) return (new WasapiService($this->tenantId))->sendTemplate($phone, $templateName, $variables);

        return match ($channel['provider']) {
            'cloud'  => (new WhatsappCloudService($this->tenantId, $channel))->sendTemplate($phone, $templateName, $language),
            'wasapi' => (new WasapiService($this->tenantId, $channel))->sendTemplate($phone, $templateName, $variables),
            default  => ['success' => false, 'error' => 'Proveedor no soportado.'],
        };
    }
}
