<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Services\ApiKeyService;

/**
 * Endpoints de meta:
 *   GET  /api/v1/status     ping publico (sin auth) — uptime check
 *   GET  /api/v1/me         info de la API key autenticada
 *   GET  /api/v1/openapi    OpenAPI 3.0 spec (publico)
 *   GET  /api/v1/scopes     lista de scopes disponibles
 */
final class ApiMetaController extends ApiController
{
    /** Endpoint publico — no requiere auth. */
    public function status(Request $request): void
    {
        Response::json([
            'data' => [
                'status'     => 'ok',
                'service'    => 'kyros-pulse-api',
                'version'    => 'v1',
                'time'       => date('c'),
            ],
        ]);
        exit;
    }

    /**
     * Healthcheck para monitoreo externo (Pingdom, UptimeRobot, etc.).
     * Chequea DB conectada + storage/cache escribible. 200 si todo OK,
     * 503 si algo critico falla.
     *
     * GET /api/v1/health
     */
    public function health(Request $request): void
    {
        $checks = [];
        $allOk = true;

        // DB
        $dbOk = false; $dbLatency = 0;
        try {
            $t0 = microtime(true);
            $val = \App\Core\Database::fetchColumn("SELECT 1");
            $dbLatency = (int) round((microtime(true) - $t0) * 1000);
            $dbOk = (int) $val === 1;
        } catch (\Throwable $e) {
            $dbOk = false;
        }
        $checks['database'] = ['ok' => $dbOk, 'latency_ms' => $dbLatency];
        if (!$dbOk) $allOk = false;

        // Cache writable
        $cacheOk = false;
        try {
            $base = (string) \App\Core\Config::get('app.paths.cache');
            if ($base !== '') {
                if (!is_dir($base)) @mkdir($base, 0775, true);
                $probe = $base . '/.healthcheck_' . random_int(1000, 9999);
                @file_put_contents($probe, 'ok');
                $cacheOk = is_file($probe);
                if ($cacheOk) @unlink($probe);
            }
        } catch (\Throwable) {
            $cacheOk = false;
        }
        $checks['cache_writable'] = ['ok' => $cacheOk];
        // No critico — cache fallido no degrada totalmente el servicio

        // Schema healed flag
        try {
            $checks['schema_ok'] = ['ok' => is_file(((string) \App\Core\Config::get('app.paths.storage')) . '/cache/.schema_v18_ok')];
        } catch (\Throwable) {
            $checks['schema_ok'] = ['ok' => false];
        }

        $status = $allOk ? 200 : 503;
        Response::json([
            'data' => [
                'status'  => $allOk ? 'healthy' : 'degraded',
                'service' => 'kyros-pulse-api',
                'version' => 'v1',
                'time'    => date('c'),
                'checks'  => $checks,
                'uptime'  => self::serverUptime(),
            ],
        ], $status);
        exit;
    }

    private static function serverUptime(): ?int
    {
        if (!function_exists('shell_exec')) return null;
        // Linux only; Windows return null gracefully
        if (stripos(PHP_OS, 'WIN') === 0) return null;
        $out = @shell_exec('cat /proc/uptime 2>/dev/null');
        if (!$out) return null;
        $parts = explode(' ', trim($out));
        return isset($parts[0]) ? (int) round((float) $parts[0]) : null;
    }

    public function me(Request $request): void
    {
        $key = $this->apiKey();
        $this->ok([
            'key_id'     => (int) ($key['id'] ?? 0),
            'name'       => (string) ($key['name'] ?? ''),
            'prefix'     => (string) ($key['prefix'] ?? ''),
            'last4'      => (string) ($key['last4'] ?? ''),
            'tenant_id'  => (int) ($key['tenant_id'] ?? 0),
            'scopes'     => ApiKeyService::decodeScopes($key),
            'created_at' => $key['created_at']    ?? null,
            'last_used_at' => $key['last_used_at'] ?? null,
            'expires_at' => $key['expires_at']    ?? null,
        ]);
    }

    /** Endpoint publico — devuelve specs OpenAPI 3.0 para clientes. */
    public function openapi(Request $request): void
    {
        $base = (string) (function_exists('url') ? url('/api/v1') : '/api/v1');

        $spec = [
            'openapi' => '3.0.3',
            'info' => [
                'title'       => 'Kyros Pulse API',
                'description' => 'Agent-as-a-Service: ejecuta agentes IA, gestiona contactos, ordenes y envia mensajes WhatsApp programaticamente.',
                'version'     => '1.0.0',
                'contact'     => ['name' => 'Kyros Solutions'],
            ],
            'servers' => [['url' => $base]],
            'security' => [['bearerAuth' => []]],
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type'         => 'http',
                        'scheme'       => 'bearer',
                        'bearerFormat' => 'kp_live_xxxxxxxx',
                        'description'  => 'API key con prefijo kp_live_. Scopes en el header `Authorization: Bearer kp_live_...`.',
                    ],
                ],
                'schemas' => [
                    'Error' => [
                        'type' => 'object',
                        'properties' => [
                            'error' => [
                                'type' => 'object',
                                'properties' => [
                                    'code'       => ['type' => 'string'],
                                    'message'    => ['type' => 'string'],
                                    'request_id' => ['type' => 'string'],
                                    'details'    => ['type' => 'object', 'nullable' => true],
                                ],
                            ],
                        ],
                    ],
                    'Agent' => [
                        'type' => 'object',
                        'properties' => [
                            'id'         => ['type' => 'integer'],
                            'uuid'       => ['type' => 'string'],
                            'name'       => ['type' => 'string'],
                            'role'       => ['type' => 'string'],
                            'tone'       => ['type' => 'string'],
                            'status'     => ['type' => 'string'],
                            'is_default' => ['type' => 'boolean'],
                            'model'      => ['type' => 'string'],
                            'temperature'=> ['type' => 'number'],
                        ],
                    ],
                    'AgentRunRequest' => [
                        'type' => 'object',
                        'required' => ['input'],
                        'properties' => [
                            'input'    => ['type' => 'string', 'maxLength' => 8000],
                            'history'  => ['type' => 'string'],
                            'metadata' => ['type' => 'object'],
                        ],
                    ],
                    'AgentRun' => [
                        'type' => 'object',
                        'properties' => [
                            'run_id'     => ['type' => 'string', 'format' => 'uuid'],
                            'output'     => ['type' => 'string'],
                            'actions'    => ['type' => 'array', 'items' => ['type' => 'object']],
                            'tokens'     => [
                                'type' => 'object',
                                'properties' => [
                                    'input'  => ['type' => 'integer'],
                                    'output' => ['type' => 'integer'],
                                ],
                            ],
                            'cost_usd'   => ['type' => 'number', 'format' => 'float'],
                            'latency_ms' => ['type' => 'integer'],
                        ],
                    ],
                    'Contact' => [
                        'type' => 'object',
                        'properties' => [
                            'id'         => ['type' => 'integer'],
                            'first_name' => ['type' => 'string'],
                            'last_name'  => ['type' => 'string'],
                            'phone'      => ['type' => 'string'],
                            'whatsapp'   => ['type' => 'string'],
                            'email'      => ['type' => 'string', 'format' => 'email'],
                            'status'     => ['type' => 'string'],
                        ],
                    ],
                    'Order' => [
                        'type' => 'object',
                        'properties' => [
                            'id'     => ['type' => 'integer'],
                            'code'   => ['type' => 'string'],
                            'status' => ['type' => 'string'],
                            'total'  => ['type' => 'number'],
                            'currency'=>['type' => 'string'],
                        ],
                    ],
                    'SendMessageRequest' => [
                        'type' => 'object',
                        'required' => ['text'],
                        'properties' => [
                            'to'         => ['type' => 'string', 'description' => 'phone in E.164'],
                            'contact_id' => ['type' => 'integer'],
                            'text'       => ['type' => 'string', 'maxLength' => 4096],
                            'channel_id' => ['type' => 'integer'],
                        ],
                    ],
                ],
            ],
            'paths' => [
                '/status' => [
                    'get' => [
                        'summary' => 'Healthcheck publico',
                        'security' => [],
                        'responses' => ['200' => ['description' => 'OK']],
                    ],
                ],
                '/me' => [
                    'get' => [
                        'summary' => 'Info de la API key actual',
                        'responses' => ['200' => ['description' => 'OK']],
                    ],
                ],
                '/agents' => [
                    'get' => [
                        'summary' => 'Listar agentes IA',
                        'tags' => ['Agents'],
                        'responses' => ['200' => ['description' => 'OK']],
                    ],
                ],
                '/agents/{id}' => [
                    'get' => [
                        'summary' => 'Detalle de agente',
                        'tags' => ['Agents'],
                        'parameters' => [['name'=>'id','in'=>'path','required'=>true,'schema'=>['type'=>'integer']]],
                        'responses' => ['200' => ['description' => 'OK'], '404' => ['$ref' => '#/components/responses/NotFound'] ?? null],
                    ],
                ],
                '/agents/{id}/run' => [
                    'post' => [
                        'summary' => 'Ejecutar agente con un input',
                        'tags' => ['Agents'],
                        'parameters' => [['name'=>'id','in'=>'path','required'=>true,'schema'=>['type'=>'integer']]],
                        'requestBody' => [
                            'required' => true,
                            'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/AgentRunRequest']]],
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'Run completed',
                                'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/AgentRun']]],
                            ],
                        ],
                    ],
                ],
                '/agents/runs' => [
                    'get' => [
                        'summary' => 'Listar ejecuciones recientes',
                        'tags' => ['Agents'],
                        'responses' => ['200' => ['description' => 'OK']],
                    ],
                ],
                '/agents/runs/{uuid}' => [
                    'get' => [
                        'summary' => 'Detalle de ejecucion',
                        'tags' => ['Agents'],
                        'parameters' => [['name'=>'uuid','in'=>'path','required'=>true,'schema'=>['type'=>'string']]],
                        'responses' => ['200' => ['description' => 'OK']],
                    ],
                ],
                '/contacts' => [
                    'get'  => ['summary' => 'Listar contactos', 'tags' => ['Contacts'], 'responses' => ['200' => ['description' => 'OK']]],
                    'post' => ['summary' => 'Crear contacto',   'tags' => ['Contacts'], 'responses' => ['201' => ['description' => 'Created']]],
                ],
                '/contacts/{id}' => [
                    'get'    => ['summary' => 'Detalle',        'tags' => ['Contacts'], 'parameters'=>[['name'=>'id','in'=>'path','required'=>true,'schema'=>['type'=>'integer']]], 'responses' => ['200' => ['description' => 'OK']]],
                    'patch'  => ['summary' => 'Actualizar',     'tags' => ['Contacts'], 'parameters'=>[['name'=>'id','in'=>'path','required'=>true,'schema'=>['type'=>'integer']]], 'responses' => ['200' => ['description' => 'OK']]],
                    'delete' => ['summary' => 'Soft delete',    'tags' => ['Contacts'], 'parameters'=>[['name'=>'id','in'=>'path','required'=>true,'schema'=>['type'=>'integer']]], 'responses' => ['204' => ['description' => 'Deleted']]],
                ],
                '/orders' => [
                    'get' => ['summary' => 'Listar ordenes', 'tags' => ['Orders'], 'responses' => ['200' => ['description' => 'OK']]],
                ],
                '/orders/{id}' => [
                    'get' => ['summary' => 'Detalle de orden', 'tags' => ['Orders'], 'parameters'=>[['name'=>'id','in'=>'path','required'=>true,'schema'=>['type'=>'integer']]], 'responses' => ['200' => ['description' => 'OK']]],
                ],
                '/orders/{id}/status' => [
                    'patch' => ['summary' => 'Cambiar status', 'tags' => ['Orders'], 'parameters'=>[['name'=>'id','in'=>'path','required'=>true,'schema'=>['type'=>'integer']]], 'responses' => ['200' => ['description' => 'OK']]],
                ],
                '/messages' => [
                    'get'  => ['summary' => 'Listar mensajes',  'tags' => ['Messages'], 'responses' => ['200' => ['description' => 'OK']]],
                    'post' => [
                        'summary' => 'Enviar mensaje WhatsApp',
                        'tags' => ['Messages'],
                        'requestBody' => [
                            'required' => true,
                            'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/SendMessageRequest']]],
                        ],
                        'responses' => ['201' => ['description' => 'Sent']],
                    ],
                ],
            ],
        ];

        Response::json($spec, 200);
        exit;
    }

    public function scopes(Request $request): void
    {
        $this->ok(ApiKeyService::SCOPES_AVAILABLE);
    }
}
