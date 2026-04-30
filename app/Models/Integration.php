<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

final class Integration extends Model
{
    protected static string $table = 'integrations';

    /**
     * Catalogo maestro. La fuente de verdad de "que integraciones existen".
     * Cada tenant tiene su fila con estado/credenciales en la tabla `integrations`.
     *
     * Estructura:
     *  slug, name, category, description, icon, is_premium, min_plan, fields[]
     */
    public static function catalog(): array
    {
        return [
            // ---------- MENSAJERIA ----------
            [
                'slug' => 'whatsapp_cloud', 'name' => 'WhatsApp Cloud API', 'category' => 'messaging',
                'description' => 'Conexion oficial con Meta Business para WhatsApp Business API.',
                'icon' => 'whatsapp', 'is_premium' => 0, 'min_plan' => 'professional',
                'fields' => [
                    ['key' => 'phone_number_id',     'label' => 'Phone Number ID',      'type' => 'text', 'required' => true],
                    ['key' => 'business_account_id', 'label' => 'WABA ID',              'type' => 'text', 'required' => true],
                    ['key' => 'access_token',        'label' => 'System User Token',    'type' => 'password', 'required' => true],
                    ['key' => 'webhook_verify',      'label' => 'Webhook Verify Token', 'type' => 'text', 'required' => false],
                ],
                'docs' => 'https://developers.facebook.com/docs/whatsapp/cloud-api',
            ],
            [
                'slug' => 'wasapi', 'name' => 'Wasapi', 'category' => 'messaging',
                'description' => 'Plataforma latam con multi-numero, plantillas y chatbot.',
                'icon' => 'wasapi', 'is_premium' => 0, 'min_plan' => null,
                'fields' => [
                    ['key' => 'api_key', 'label' => 'API Key',              'type' => 'password', 'required' => true],
                    ['key' => 'phone',   'label' => 'Numero principal',     'type' => 'text', 'required' => false],
                ],
                'docs' => 'https://docs.wasapi.io',
            ],
            [
                'slug' => 'twilio_whatsapp', 'name' => 'Twilio WhatsApp', 'category' => 'messaging',
                'description' => 'Twilio para WhatsApp Business y SMS internacional.',
                'icon' => 'twilio', 'is_premium' => 0, 'min_plan' => 'business',
                'fields' => [
                    ['key' => 'account_sid', 'label' => 'Account SID',  'type' => 'text', 'required' => true],
                    ['key' => 'auth_token',  'label' => 'Auth Token',   'type' => 'password', 'required' => true],
                    ['key' => 'from_phone',  'label' => 'From (E.164)', 'type' => 'text', 'required' => true],
                ],
                'docs' => 'https://www.twilio.com/docs/whatsapp',
            ],
            [
                'slug' => 'telegram', 'name' => 'Telegram Bot', 'category' => 'messaging',
                'description' => 'Atiende a tus clientes desde Telegram con la misma bandeja.',
                'icon' => 'telegram', 'is_premium' => 0, 'min_plan' => 'professional',
                'fields' => [
                    ['key' => 'bot_token',    'label' => 'Bot Token',     'type' => 'password', 'required' => true],
                    ['key' => 'bot_username', 'label' => 'Bot username',  'type' => 'text', 'required' => false],
                ],
                'docs' => 'https://core.telegram.org/bots/api',
            ],
            [
                'slug' => 'messenger', 'name' => 'Facebook Messenger', 'category' => 'messaging',
                'description' => 'Recibe y responde mensajes de Facebook Pages.',
                'icon' => 'messenger', 'is_premium' => 0, 'min_plan' => 'business',
                'fields' => [
                    ['key' => 'page_id',     'label' => 'Page ID',          'type' => 'text', 'required' => true],
                    ['key' => 'page_token',  'label' => 'Page Access Token','type' => 'password', 'required' => true],
                    ['key' => 'app_secret',  'label' => 'App secret',       'type' => 'password', 'required' => false],
                ],
                'docs' => 'https://developers.facebook.com/docs/messenger-platform',
            ],
            [
                'slug' => 'instagram_dm', 'name' => 'Instagram DM', 'category' => 'messaging',
                'description' => 'DMs de Instagram Business unificados en tu inbox.',
                'icon' => 'instagram', 'is_premium' => 0, 'min_plan' => 'business',
                'fields' => [
                    ['key' => 'ig_business_id', 'label' => 'IG Business ID',   'type' => 'text', 'required' => true],
                    ['key' => 'access_token',   'label' => 'Access Token',     'type' => 'password', 'required' => true],
                ],
                'docs' => 'https://developers.facebook.com/docs/instagram-api',
            ],
            [
                'slug' => 'webchat', 'name' => 'Webchat embebido', 'category' => 'messaging',
                'description' => 'Widget de chat para tu sitio web. Convierte visitantes en leads.',
                'icon' => 'webchat', 'is_premium' => 0, 'min_plan' => null,
                'fields' => [
                    ['key' => 'allowed_domains', 'label' => 'Dominios permitidos (coma)', 'type' => 'text', 'required' => false],
                    ['key' => 'theme_color',     'label' => 'Color del widget',           'type' => 'color', 'required' => false],
                ],
                'docs' => null,
            ],
            [
                'slug' => 'sms_twilio', 'name' => 'SMS via Twilio', 'category' => 'messaging',
                'description' => 'Envia SMS transaccionales y campanas.',
                'icon' => 'sms', 'is_premium' => 0, 'min_plan' => 'business',
                'fields' => [
                    ['key' => 'account_sid',  'label' => 'Account SID', 'type' => 'text', 'required' => true],
                    ['key' => 'auth_token',   'label' => 'Auth Token',  'type' => 'password', 'required' => true],
                    ['key' => 'from_phone',   'label' => 'From',        'type' => 'text', 'required' => true],
                ],
                'docs' => 'https://www.twilio.com/docs/sms',
            ],

            // ---------- EMAIL ----------
            [
                'slug' => 'resend', 'name' => 'Resend', 'category' => 'email',
                'description' => 'Email transaccional moderno con APIs simples.',
                'icon' => 'resend', 'is_premium' => 0, 'min_plan' => null,
                'fields' => [
                    ['key' => 'api_key',    'label' => 'API Key',     'type' => 'password', 'required' => true],
                    ['key' => 'from_email', 'label' => 'From email',  'type' => 'text', 'required' => true],
                ],
                'docs' => 'https://resend.com/docs',
            ],
            [
                'slug' => 'sendgrid', 'name' => 'SendGrid', 'category' => 'email',
                'description' => 'Email a escala con marketing y transaccional.',
                'icon' => 'sendgrid', 'is_premium' => 0, 'min_plan' => 'business',
                'fields' => [
                    ['key' => 'api_key',    'label' => 'API Key',    'type' => 'password', 'required' => true],
                    ['key' => 'from_email', 'label' => 'From email', 'type' => 'text', 'required' => true],
                ],
                'docs' => 'https://docs.sendgrid.com',
            ],
            [
                'slug' => 'mailgun', 'name' => 'Mailgun', 'category' => 'email',
                'description' => 'Servicio email para developers, alta entregabilidad.',
                'icon' => 'mailgun', 'is_premium' => 0, 'min_plan' => 'business',
                'fields' => [
                    ['key' => 'api_key', 'label' => 'API Key', 'type' => 'password', 'required' => true],
                    ['key' => 'domain',  'label' => 'Domain',  'type' => 'text', 'required' => true],
                ],
                'docs' => 'https://documentation.mailgun.com',
            ],

            // ---------- CRM ----------
            [
                'slug' => 'hubspot', 'name' => 'HubSpot CRM', 'category' => 'crm',
                'description' => 'Sincroniza contactos, deals y actividades con HubSpot.',
                'icon' => 'hubspot', 'is_premium' => 1, 'min_plan' => 'business',
                'fields' => [
                    ['key' => 'access_token', 'label' => 'Private App Token', 'type' => 'password', 'required' => true],
                    ['key' => 'portal_id',    'label' => 'Portal ID',         'type' => 'text', 'required' => false],
                ],
                'docs' => 'https://developers.hubspot.com',
            ],
            [
                'slug' => 'salesforce', 'name' => 'Salesforce', 'category' => 'crm',
                'description' => 'Sincronizacion bidireccional con Salesforce Sales Cloud.',
                'icon' => 'salesforce', 'is_premium' => 1, 'min_plan' => 'enterprise',
                'fields' => [
                    ['key' => 'instance_url',  'label' => 'Instance URL', 'type' => 'text', 'required' => true],
                    ['key' => 'client_id',     'label' => 'Client ID',    'type' => 'text', 'required' => true],
                    ['key' => 'client_secret', 'label' => 'Client Secret','type' => 'password', 'required' => true],
                    ['key' => 'refresh_token', 'label' => 'Refresh Token','type' => 'password', 'required' => true],
                ],
                'docs' => 'https://developer.salesforce.com',
            ],
            [
                'slug' => 'pipedrive', 'name' => 'Pipedrive', 'category' => 'crm',
                'description' => 'Sincroniza pipeline de ventas con Pipedrive.',
                'icon' => 'pipedrive', 'is_premium' => 1, 'min_plan' => 'business',
                'fields' => [
                    ['key' => 'api_token', 'label' => 'API Token',   'type' => 'password', 'required' => true],
                    ['key' => 'company',   'label' => 'Subdominio',  'type' => 'text', 'required' => false],
                ],
                'docs' => 'https://developers.pipedrive.com',
            ],
            [
                'slug' => 'zoho_crm', 'name' => 'Zoho CRM', 'category' => 'crm',
                'description' => 'Conexion con Zoho CRM (contactos, leads, deals).',
                'icon' => 'zoho', 'is_premium' => 1, 'min_plan' => 'business',
                'fields' => [
                    ['key' => 'client_id',     'label' => 'Client ID',     'type' => 'text', 'required' => true],
                    ['key' => 'client_secret', 'label' => 'Client Secret', 'type' => 'password', 'required' => true],
                    ['key' => 'refresh_token', 'label' => 'Refresh Token', 'type' => 'password', 'required' => true],
                    ['key' => 'region',        'label' => 'Region (us, eu, in)', 'type' => 'text', 'required' => false],
                ],
                'docs' => 'https://www.zoho.com/crm/developer',
            ],

            // ---------- ECOMMERCE ----------
            [
                'slug' => 'shopify', 'name' => 'Shopify', 'category' => 'ecommerce',
                'description' => 'Sincroniza productos, ordenes y clientes con Shopify.',
                'icon' => 'shopify', 'is_premium' => 1, 'min_plan' => 'business',
                'fields' => [
                    ['key' => 'shop_domain', 'label' => 'Shop domain (.myshopify.com)', 'type' => 'text', 'required' => true],
                    ['key' => 'access_token','label' => 'Admin API Access Token', 'type' => 'password', 'required' => true],
                ],
                'docs' => 'https://shopify.dev/api',
            ],
            [
                'slug' => 'woocommerce', 'name' => 'WooCommerce', 'category' => 'ecommerce',
                'description' => 'Sincroniza tu tienda WordPress con KyrosPulse.',
                'icon' => 'woocommerce', 'is_premium' => 0, 'min_plan' => 'business',
                'fields' => [
                    ['key' => 'site_url',         'label' => 'URL del sitio',     'type' => 'text', 'required' => true],
                    ['key' => 'consumer_key',     'label' => 'Consumer Key',      'type' => 'password', 'required' => true],
                    ['key' => 'consumer_secret',  'label' => 'Consumer Secret',   'type' => 'password', 'required' => true],
                ],
                'docs' => 'https://woocommerce.github.io/woocommerce-rest-api-docs',
            ],

            // ---------- PAGOS ----------
            [
                'slug' => 'stripe', 'name' => 'Stripe', 'category' => 'payments',
                'description' => 'Genera links de pago y registra transacciones automaticamente.',
                'icon' => 'stripe', 'is_premium' => 0, 'min_plan' => 'professional',
                'fields' => [
                    ['key' => 'public_key',     'label' => 'Public Key',     'type' => 'text', 'required' => true],
                    ['key' => 'secret_key',     'label' => 'Secret Key',     'type' => 'password', 'required' => true],
                    ['key' => 'webhook_secret', 'label' => 'Webhook Secret', 'type' => 'password', 'required' => false],
                ],
                'docs' => 'https://stripe.com/docs/api',
            ],
            [
                'slug' => 'mercadopago', 'name' => 'MercadoPago', 'category' => 'payments',
                'description' => 'Pagos en LATAM y Brasil con MercadoPago.',
                'icon' => 'mercadopago', 'is_premium' => 0, 'min_plan' => 'professional',
                'fields' => [
                    ['key' => 'access_token', 'label' => 'Access Token', 'type' => 'password', 'required' => true],
                    ['key' => 'public_key',   'label' => 'Public Key',   'type' => 'text', 'required' => false],
                ],
                'docs' => 'https://www.mercadopago.com/developers',
            ],
            [
                'slug' => 'paypal', 'name' => 'PayPal', 'category' => 'payments',
                'description' => 'Acepta pagos via PayPal con generacion de links.',
                'icon' => 'paypal', 'is_premium' => 0, 'min_plan' => 'business',
                'fields' => [
                    ['key' => 'client_id',     'label' => 'Client ID',     'type' => 'text', 'required' => true],
                    ['key' => 'client_secret', 'label' => 'Client Secret', 'type' => 'password', 'required' => true],
                    ['key' => 'mode',          'label' => 'Mode (live/sandbox)', 'type' => 'text', 'required' => false],
                ],
                'docs' => 'https://developer.paypal.com',
            ],

            // ---------- PRODUCTIVIDAD ----------
            [
                'slug' => 'slack', 'name' => 'Slack', 'category' => 'productivity',
                'description' => 'Notifica leads, conversaciones criticas y alertas en Slack.',
                'icon' => 'slack', 'is_premium' => 0, 'min_plan' => 'professional',
                'fields' => [
                    ['key' => 'webhook_url',   'label' => 'Incoming Webhook URL', 'type' => 'text', 'required' => true],
                    ['key' => 'channel',       'label' => 'Canal por defecto',    'type' => 'text', 'required' => false],
                ],
                'docs' => 'https://api.slack.com/messaging/webhooks',
            ],
            [
                'slug' => 'msteams', 'name' => 'Microsoft Teams', 'category' => 'productivity',
                'description' => 'Notificaciones de KyrosPulse en canales de Teams.',
                'icon' => 'teams', 'is_premium' => 0, 'min_plan' => 'business',
                'fields' => [
                    ['key' => 'webhook_url', 'label' => 'Incoming Webhook URL', 'type' => 'text', 'required' => true],
                ],
                'docs' => 'https://learn.microsoft.com/microsoftteams/platform/webhooks-and-connectors/how-to/add-incoming-webhook',
            ],
            [
                'slug' => 'discord', 'name' => 'Discord', 'category' => 'productivity',
                'description' => 'Notificaciones a un canal de Discord.',
                'icon' => 'discord', 'is_premium' => 0, 'min_plan' => 'professional',
                'fields' => [
                    ['key' => 'webhook_url', 'label' => 'Webhook URL', 'type' => 'text', 'required' => true],
                ],
                'docs' => 'https://support.discord.com/hc/articles/228383668',
            ],
            [
                'slug' => 'google_calendar', 'name' => 'Google Calendar', 'category' => 'productivity',
                'description' => 'Crea eventos cuando la IA agenda citas con clientes.',
                'icon' => 'gcal', 'is_premium' => 1, 'min_plan' => 'business',
                'fields' => [
                    ['key' => 'client_id',     'label' => 'OAuth Client ID',     'type' => 'text', 'required' => true],
                    ['key' => 'client_secret', 'label' => 'OAuth Client Secret', 'type' => 'password', 'required' => true],
                    ['key' => 'refresh_token', 'label' => 'Refresh Token',       'type' => 'password', 'required' => true],
                    ['key' => 'calendar_id',   'label' => 'Calendar ID',         'type' => 'text', 'required' => false],
                ],
                'docs' => 'https://developers.google.com/calendar',
            ],
            [
                'slug' => 'google_sheets', 'name' => 'Google Sheets', 'category' => 'productivity',
                'description' => 'Exporta leads y conversaciones a hojas de calculo automaticamente.',
                'icon' => 'gsheets', 'is_premium' => 1, 'min_plan' => 'business',
                'fields' => [
                    ['key' => 'spreadsheet_id', 'label' => 'Spreadsheet ID',     'type' => 'text', 'required' => true],
                    ['key' => 'service_account','label' => 'Service Account JSON','type' => 'textarea', 'required' => true],
                ],
                'docs' => 'https://developers.google.com/sheets',
            ],

            // ---------- AUTOMATION ----------
            [
                'slug' => 'zapier', 'name' => 'Zapier', 'category' => 'automation',
                'description' => '5000+ integraciones via Zapier triggers/actions.',
                'icon' => 'zapier', 'is_premium' => 0, 'min_plan' => 'professional',
                'fields' => [
                    ['key' => 'webhook_url', 'label' => 'Catch Hook URL', 'type' => 'text', 'required' => true],
                ],
                'docs' => 'https://zapier.com/help/create/code-webhooks',
            ],
            [
                'slug' => 'make', 'name' => 'Make (Integromat)', 'category' => 'automation',
                'description' => 'Visual automation. Conecta KyrosPulse con cualquier app.',
                'icon' => 'make', 'is_premium' => 0, 'min_plan' => 'professional',
                'fields' => [
                    ['key' => 'webhook_url', 'label' => 'Custom Webhook URL', 'type' => 'text', 'required' => true],
                ],
                'docs' => 'https://www.make.com/en/help/tools/webhooks',
            ],
            [
                'slug' => 'n8n', 'name' => 'n8n', 'category' => 'automation',
                'description' => 'Self-hosted automation open source.',
                'icon' => 'n8n', 'is_premium' => 0, 'min_plan' => 'business',
                'fields' => [
                    ['key' => 'webhook_url', 'label' => 'Webhook URL', 'type' => 'text', 'required' => true],
                    ['key' => 'auth_header', 'label' => 'Auth header (opcional)', 'type' => 'password', 'required' => false],
                ],
                'docs' => 'https://docs.n8n.io',
            ],

            // ---------- ANALYTICS / DEV ----------
            [
                'slug' => 'webhook_outbound', 'name' => 'Webhooks salientes', 'category' => 'developer',
                'description' => 'Envia eventos en tiempo real a tu propio backend.',
                'icon' => 'webhook', 'is_premium' => 0, 'min_plan' => 'business',
                'fields' => [
                    ['key' => 'url',         'label' => 'URL destino', 'type' => 'text', 'required' => true],
                    ['key' => 'secret',      'label' => 'Signing secret', 'type' => 'password', 'required' => false],
                    ['key' => 'events',      'label' => 'Eventos (coma)', 'type' => 'text', 'required' => false],
                ],
                'docs' => null,
            ],
            [
                'slug' => 'segment', 'name' => 'Segment', 'category' => 'analytics',
                'description' => 'Envia eventos a tu Customer Data Platform.',
                'icon' => 'segment', 'is_premium' => 1, 'min_plan' => 'enterprise',
                'fields' => [
                    ['key' => 'write_key', 'label' => 'Write Key', 'type' => 'password', 'required' => true],
                ],
                'docs' => 'https://segment.com/docs',
            ],
            [
                'slug' => 'mixpanel', 'name' => 'Mixpanel', 'category' => 'analytics',
                'description' => 'Tracking avanzado de eventos de cliente.',
                'icon' => 'mixpanel', 'is_premium' => 1, 'min_plan' => 'enterprise',
                'fields' => [
                    ['key' => 'project_token', 'label' => 'Project Token', 'type' => 'password', 'required' => true],
                ],
                'docs' => 'https://developer.mixpanel.com',
            ],

            // ---------- CONTACT CENTER ----------
            [
                'slug' => 'genesys', 'name' => 'Genesys Cloud', 'category' => 'contact_center',
                'description' => 'Sincroniza con tu plataforma de contact center.',
                'icon' => 'genesys', 'is_premium' => 1, 'min_plan' => 'enterprise',
                'fields' => [
                    ['key' => 'region',        'label' => 'Region',        'type' => 'text', 'required' => true],
                    ['key' => 'client_id',     'label' => 'Client ID',     'type' => 'text', 'required' => true],
                    ['key' => 'client_secret', 'label' => 'Client Secret', 'type' => 'password', 'required' => true],
                ],
                'docs' => 'https://developer.genesys.cloud',
            ],
            [
                'slug' => 'five9', 'name' => 'Five9', 'category' => 'contact_center',
                'description' => 'Marcacion predictiva y voz integrada.',
                'icon' => 'five9', 'is_premium' => 1, 'min_plan' => 'enterprise',
                'fields' => [
                    ['key' => 'username',  'label' => 'Username',  'type' => 'text', 'required' => true],
                    ['key' => 'password',  'label' => 'Password',  'type' => 'password', 'required' => true],
                    ['key' => 'farm',      'label' => 'Datacenter', 'type' => 'text', 'required' => false],
                ],
                'docs' => 'https://www.five9.com/products/platform/apis',
            ],
            [
                'slug' => 'aircall', 'name' => 'Aircall', 'category' => 'contact_center',
                'description' => 'Llamadas VoIP integradas con la timeline del contacto.',
                'icon' => 'aircall', 'is_premium' => 1, 'min_plan' => 'enterprise',
                'fields' => [
                    ['key' => 'api_id',    'label' => 'API ID',    'type' => 'text', 'required' => true],
                    ['key' => 'api_token', 'label' => 'API Token', 'type' => 'password', 'required' => true],
                ],
                'docs' => 'https://developer.aircall.io',
            ],

            // ---------- AI providers (extra) ----------
            [
                'slug' => 'gemini', 'name' => 'Google Gemini', 'category' => 'ai',
                'description' => 'Provider IA de Google para respuestas y resumen.',
                'icon' => 'gemini', 'is_premium' => 0, 'min_plan' => 'business',
                'fields' => [
                    ['key' => 'api_key', 'label' => 'API Key', 'type' => 'password', 'required' => true],
                    ['key' => 'model',   'label' => 'Modelo (gemini-1.5-pro)', 'type' => 'text', 'required' => false],
                ],
                'docs' => 'https://ai.google.dev',
            ],
        ];
    }

    public const CATEGORIES = [
        'messaging'      => ['Mensajeria', '#10B981', 'message-square'],
        'email'          => ['Email',      '#06B6D4', 'mail'],
        'crm'            => ['CRM',        '#7C3AED', 'users'],
        'ecommerce'      => ['Ecommerce',  '#F59E0B', 'shopping-bag'],
        'payments'       => ['Pagos',      '#22C55E', 'credit-card'],
        'productivity'   => ['Productividad', '#0EA5E9', 'check-square'],
        'automation'     => ['Automatizacion', '#A855F7', 'zap'],
        'analytics'      => ['Analitica',   '#F43F5E', 'bar-chart'],
        'contact_center' => ['Contact Center', '#0F766E', 'phone-call'],
        'developer'      => ['Developer',   '#94A3B8', 'code'],
        'ai'             => ['Inteligencia Artificial', '#EC4899', 'cpu'],
    ];

    public static function findCatalog(string $slug): ?array
    {
        foreach (self::catalog() as $entry) {
            if ($entry['slug'] === $slug) return $entry;
        }
        return null;
    }

    public static function findForTenant(int $tenantId, string $slug): ?array
    {
        return Database::fetch(
            "SELECT * FROM integrations WHERE tenant_id = :t AND slug = :s",
            ['t' => $tenantId, 's' => $slug]
        );
    }

    public static function listForTenant(int $tenantId): array
    {
        $rows = Database::fetchAll(
            "SELECT * FROM integrations WHERE tenant_id = :t ORDER BY status DESC, name ASC",
            ['t' => $tenantId]
        );
        $byKey = [];
        foreach ($rows as $r) $byKey[$r['slug']] = $r;
        return $byKey;
    }

    public static function upsert(int $tenantId, string $slug, array $patch): int
    {
        $existing = self::findForTenant($tenantId, $slug);
        $catalog  = self::findCatalog($slug);
        if (!$catalog) {
            throw new \RuntimeException("Integracion desconocida: $slug");
        }

        $base = [
            'tenant_id'   => $tenantId,
            'slug'        => $slug,
            'name'        => $catalog['name'],
            'category'    => $catalog['category'],
            'description' => $catalog['description'] ?? null,
            'icon'        => $catalog['icon'] ?? null,
            'is_premium'  => !empty($catalog['is_premium']) ? 1 : 0,
            'min_plan'    => $catalog['min_plan'] ?? null,
        ];
        $data = array_merge($base, $patch);

        if ($existing) {
            Database::update('integrations', $data, ['id' => (int) $existing['id'], 'tenant_id' => $tenantId]);
            return (int) $existing['id'];
        }

        return Database::insert('integrations', $data);
    }

    public static function setStatus(int $tenantId, string $slug, string $status, ?string $error = null): void
    {
        Database::run(
            "UPDATE integrations SET status = :st, last_error = :err
             WHERE tenant_id = :t AND slug = :s",
            ['st' => $status, 'err' => $error, 't' => $tenantId, 's' => $slug]
        );
    }

    public static function disconnect(int $tenantId, string $slug): void
    {
        Database::run(
            "UPDATE integrations
             SET status = 'disconnected', is_enabled = 0, credentials = NULL, connected_at = NULL
             WHERE tenant_id = :t AND slug = :s",
            ['t' => $tenantId, 's' => $slug]
        );
    }

    public static function logEvent(int $tenantId, string $slug, string $event, array $extra = []): void
    {
        try {
            $existing = self::findForTenant($tenantId, $slug);
            Database::insert('integration_logs', [
                'tenant_id'      => $tenantId,
                'integration_id' => $existing ? (int) $existing['id'] : null,
                'slug'           => $slug,
                'event'          => $event,
                'direction'      => $extra['direction'] ?? 'sync',
                'request_body'   => isset($extra['request']) ? mb_substr((string) $extra['request'], 0, 8000) : null,
                'response_body'  => isset($extra['response']) ? mb_substr((string) $extra['response'], 0, 8000) : null,
                'status_code'    => $extra['status'] ?? null,
                'success'        => !empty($extra['success']) ? 1 : 0,
                'error_message'  => $extra['error'] ?? null,
                'duration_ms'    => $extra['duration'] ?? null,
            ]);
        } catch (\Throwable) {
            // Silenciar
        }
    }
}
