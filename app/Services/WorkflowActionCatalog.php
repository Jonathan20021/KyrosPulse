<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Tenant;

/**
 * Catalogo declarativo de step types + actions del WorkflowEngine.
 *
 * El editor visual usa este catalogo para renderizar formularios tipados
 * (en lugar de pedir al usuario que edite JSON crudo). Cada entrada define:
 *   - type/action slug
 *   - label / description / icon / color
 *   - fields[]: array de inputs con type, key, label, placeholder, required, options
 *
 * Mantener este catalogo en sync con WorkflowEngine::executeStep + runAction.
 */
final class WorkflowActionCatalog
{
    /**
     * Catalogo completo: step types y, para 'action', sus sub-tipos (actions).
     * Devuelve estructura serializable a JSON para el editor.
     */
    public static function full(): array
    {
        return [
            'step_types' => [
                [
                    'type'  => 'action',
                    'label' => 'Accion',
                    'icon'  => '⚡',
                    'color' => '#06B6D4',
                    'description' => 'Ejecuta algo: enviar mensaje, llamar agente IA, hacer HTTP request...',
                    'fields' => [
                        // Las "fields" reales dependen de la action elegida (resuelto en cliente).
                        ['type' => 'select', 'key' => 'action', 'label' => 'Accion', 'required' => true, 'options' => self::actionOptions()],
                    ],
                ],
                [
                    'type'  => 'branch',
                    'label' => 'Condicion (if/else)',
                    'icon'  => '🔀',
                    'color' => '#F59E0B',
                    'description' => 'Evalua una expresion del context y bifurca el flujo.',
                    'fields' => [
                        ['type' => 'text',   'key' => 'expr',  'label' => 'Variable a evaluar', 'placeholder' => 'last.output', 'required' => true, 'help' => 'Path dentro del context. Ej: payload.amount, last.cost_usd, vars.score'],
                        ['type' => 'select', 'key' => 'op',    'label' => 'Operador', 'required' => true, 'default' => 'truthy', 'options' => [
                            ['value' => 'truthy',       'label' => 'es verdadero / no vacio'],
                            ['value' => 'falsy',        'label' => 'es falso / vacio'],
                            ['value' => 'eq',           'label' => '== igual a'],
                            ['value' => 'neq',          'label' => '!= distinto de'],
                            ['value' => 'gt',           'label' => '> mayor que'],
                            ['value' => 'gte',          'label' => '>= mayor o igual'],
                            ['value' => 'lt',           'label' => '< menor que'],
                            ['value' => 'lte',          'label' => '<= menor o igual'],
                            ['value' => 'contains',     'label' => 'contiene texto'],
                            ['value' => 'not_contains', 'label' => 'NO contiene texto'],
                        ]],
                        ['type' => 'text', 'key' => 'value', 'label' => 'Valor a comparar', 'placeholder' => '100', 'help' => 'Solo si el operador requiere un valor. Vacio para truthy/falsy.'],
                    ],
                    'connections' => ['branch_yes', 'branch_no'],
                ],
                [
                    'type'  => 'delay',
                    'label' => 'Esperar',
                    'icon'  => '⏳',
                    'color' => '#8B5CF6',
                    'description' => 'Pausa el run. El cron lo reanuda cuando cumple el tiempo.',
                    'fields' => [
                        ['type' => 'duration', 'key' => 'seconds', 'label' => 'Esperar', 'required' => true, 'default' => 60, 'help' => 'Tiempo antes del proximo step.'],
                    ],
                ],
                [
                    'type'  => 'set_var',
                    'label' => 'Set variable',
                    'icon'  => '📌',
                    'color' => '#10B981',
                    'description' => 'Guarda un valor en el context para usarlo en steps siguientes.',
                    'fields' => [
                        ['type' => 'text', 'key' => 'key',   'label' => 'Nombre (path)', 'required' => true, 'placeholder' => 'vars.score', 'help' => 'Soporta paths: vars.x, custom.y.z'],
                        ['type' => 'text', 'key' => 'value', 'label' => 'Valor', 'placeholder' => '{{ last.output }} o un literal'],
                    ],
                ],
                [
                    'type'  => 'end',
                    'label' => 'Terminar run',
                    'icon'  => '🏁',
                    'color' => '#64748B',
                    'description' => 'Marca el run como terminado.',
                    'fields' => [
                        ['type' => 'select', 'key' => 'status', 'label' => 'Status final', 'default' => 'succeeded', 'options' => [
                            ['value' => 'succeeded', 'label' => '✓ Succeeded (exito)'],
                            ['value' => 'failed',    'label' => '✗ Failed (error)'],
                            ['value' => 'cancelled', 'label' => 'Cancelled'],
                        ]],
                    ],
                ],
            ],
            'actions' => self::actionsFull(),
        ];
    }

    /** Lista de actions con metadata + fields tipados de sus params. */
    public static function actionsFull(): array
    {
        return [
            'send_whatsapp' => [
                'label' => 'Enviar WhatsApp',
                'icon'  => '💬',
                'description' => 'Envia un mensaje de texto via el canal WhatsApp del tenant.',
                'fields' => [
                    ['type' => 'text',     'key' => 'to',         'label' => 'Telefono destino', 'required' => true, 'placeholder' => '{{ payload.contact_phone }}', 'help' => 'Soporta variables {{ }}'],
                    ['type' => 'textarea', 'key' => 'text',       'label' => 'Mensaje', 'required' => true, 'placeholder' => 'Hola {{ payload.first_name }}!', 'rows' => 3],
                    ['type' => 'number',   'key' => 'channel_id', 'label' => 'Channel ID (opcional)', 'help' => 'ID del canal WhatsApp si tienes varios. Vacio = default del tenant.'],
                ],
            ],
            'run_agent' => [
                'label' => 'Ejecutar agente IA',
                'icon'  => '🧠',
                'description' => 'Corre un agente IA con un input dado. Disponible en {{ last.output }} para steps siguientes.',
                'fields' => [
                    ['type' => 'agent_select', 'key' => 'agent_id', 'label' => 'Agente IA', 'required' => true],
                    ['type' => 'textarea',     'key' => 'input',    'label' => 'Input al agente', 'required' => true, 'placeholder' => 'Califica este lead: {{ payload.first_name }} {{ payload.last_name }}, empresa: {{ payload.company }}', 'rows' => 4],
                    ['type' => 'textarea',     'key' => 'history',  'label' => 'History (opcional)', 'rows' => 2],
                ],
            ],
            'http' => [
                'label' => 'HTTP request',
                'icon'  => '🌐',
                'description' => 'Llama a una API externa. Response en {{ last.body }}.',
                'fields' => [
                    ['type' => 'select', 'key' => 'method', 'label' => 'Metodo', 'default' => 'GET', 'options' => [
                        ['value' => 'GET',    'label' => 'GET'],
                        ['value' => 'POST',   'label' => 'POST'],
                        ['value' => 'PUT',    'label' => 'PUT'],
                        ['value' => 'PATCH',  'label' => 'PATCH'],
                        ['value' => 'DELETE', 'label' => 'DELETE'],
                    ]],
                    ['type' => 'text',     'key' => 'url',     'label' => 'URL', 'required' => true, 'placeholder' => 'https://api.midominio.com/...'],
                    ['type' => 'kv',       'key' => 'headers', 'label' => 'Headers'],
                    ['type' => 'textarea', 'key' => 'body',    'label' => 'Body (JSON)', 'rows' => 4, 'placeholder' => '{"key":"value"}'],
                    ['type' => 'number',   'key' => 'timeout', 'label' => 'Timeout (segundos)', 'default' => 15],
                ],
            ],
            'add_tag' => [
                'label' => 'Agregar tag al contacto',
                'icon'  => '🏷',
                'description' => 'Anade un tag al contacto.',
                'fields' => [
                    ['type' => 'text', 'key' => 'contact_id', 'label' => 'Contact ID', 'required' => true, 'placeholder' => '{{ payload.contact_id }}'],
                    ['type' => 'text', 'key' => 'tag',        'label' => 'Tag', 'required' => true, 'placeholder' => 'cliente_premium'],
                ],
            ],
            'webhook_out' => [
                'label' => 'Webhook saliente (ad hoc)',
                'icon'  => '🪝',
                'description' => 'POST JSON a una URL externa. Opcionalmente firma con HMAC.',
                'fields' => [
                    ['type' => 'text',     'key' => 'url',     'label' => 'URL', 'required' => true],
                    ['type' => 'text',     'key' => 'secret',  'label' => 'HMAC secret (opcional)'],
                    ['type' => 'textarea', 'key' => 'payload', 'label' => 'Payload JSON', 'rows' => 4, 'placeholder' => '{"event":"custom","data":"{{ last }}"}', 'help' => 'Si vacio, envia el context completo.'],
                ],
            ],
            'log' => [
                'label' => 'Log debug',
                'icon'  => '📝',
                'description' => 'Escribe una linea en el log para debugging.',
                'fields' => [
                    ['type' => 'text', 'key' => 'message', 'label' => 'Mensaje', 'placeholder' => 'Run llego al checkpoint X con valor {{ last.output }}'],
                ],
            ],
            'noop' => [
                'label' => 'No-op (placeholder)',
                'icon'  => '⚪',
                'description' => 'No hace nada. Util como placeholder o para testing.',
                'fields' => [],
            ],
        ];
    }

    /** Para el selector inicial de action en step type=action. */
    public static function actionOptions(): array
    {
        $out = [];
        foreach (self::actionsFull() as $slug => $meta) {
            $out[] = [
                'value' => $slug,
                'label' => $meta['icon'] . ' ' . $meta['label'],
            ];
        }
        return $out;
    }

    /**
     * Variables disponibles segun el trigger del workflow + agentes/canales del
     * tenant. El editor las expone en el "variable picker" para click-to-insert.
     */
    public static function variablesForWorkflow(array $workflow): array
    {
        $vars = [
            ['path' => 'last.output',     'label' => 'Output del step anterior (run_agent o http)', 'group' => 'Step previo'],
            ['path' => 'last.tokens_in',  'label' => 'Tokens entrada (agente IA)', 'group' => 'Step previo'],
            ['path' => 'last.tokens_out', 'label' => 'Tokens salida (agente IA)',  'group' => 'Step previo'],
            ['path' => 'last.cost_usd',   'label' => 'Costo USD (agente IA)',      'group' => 'Step previo'],
            ['path' => 'last.status',     'label' => 'Status (HTTP)',              'group' => 'Step previo'],
            ['path' => 'last.body',       'label' => 'Body (HTTP response)',       'group' => 'Step previo'],
        ];

        $trigger = (string) ($workflow['trigger_type'] ?? 'event');
        $cfg = $workflow['trigger_config'] ? json_decode((string) $workflow['trigger_config'], true) : [];
        if (!is_array($cfg)) $cfg = [];

        if ($trigger === 'event') {
            $event = (string) ($cfg['event'] ?? '');
            $vars[] = ['path' => 'event',           'label' => 'Nombre del evento', 'group' => 'Trigger event'];
            $vars[] = ['path' => 'payload',         'label' => 'Payload completo del evento', 'group' => 'Trigger event'];
            // Eventos comunes -> hints especificos
            if (str_starts_with($event, 'order.') || $event === '*') {
                $vars[] = ['path' => 'payload.order_id',     'label' => 'ID de orden',     'group' => 'Trigger event'];
                $vars[] = ['path' => 'payload.contact_id',   'label' => 'ID de contacto',  'group' => 'Trigger event'];
                $vars[] = ['path' => 'payload.code',         'label' => 'Codigo de orden', 'group' => 'Trigger event'];
                $vars[] = ['path' => 'payload.total',        'label' => 'Total',           'group' => 'Trigger event'];
            }
            if (str_starts_with($event, 'contact.') || $event === '*') {
                $vars[] = ['path' => 'payload.contact_id',   'label' => 'ID de contacto', 'group' => 'Trigger event'];
                $vars[] = ['path' => 'payload.first_name',   'label' => 'Nombre',         'group' => 'Trigger event'];
                $vars[] = ['path' => 'payload.last_name',    'label' => 'Apellido',       'group' => 'Trigger event'];
                $vars[] = ['path' => 'payload.phone',        'label' => 'Telefono',       'group' => 'Trigger event'];
                $vars[] = ['path' => 'payload.email',        'label' => 'Email',          'group' => 'Trigger event'];
            }
            if (str_starts_with($event, 'agent.run.') || $event === '*') {
                $vars[] = ['path' => 'payload.run_id',     'label' => 'Run ID',     'group' => 'Trigger event'];
                $vars[] = ['path' => 'payload.agent_id',   'label' => 'Agent ID',   'group' => 'Trigger event'];
                $vars[] = ['path' => 'payload.output',     'label' => 'Output del agente', 'group' => 'Trigger event'];
                $vars[] = ['path' => 'payload.cost_usd',   'label' => 'Costo USD',  'group' => 'Trigger event'];
            }
            if (str_starts_with($event, 'message.') || $event === '*') {
                $vars[] = ['path' => 'payload.message_id',     'label' => 'Message ID',     'group' => 'Trigger event'];
                $vars[] = ['path' => 'payload.contact_id',     'label' => 'Contact ID',     'group' => 'Trigger event'];
                $vars[] = ['path' => 'payload.content',        'label' => 'Contenido',      'group' => 'Trigger event'];
                $vars[] = ['path' => 'payload.from_phone',     'label' => 'Telefono origen','group' => 'Trigger event'];
            }
        } elseif ($trigger === 'webhook') {
            $vars[] = ['path' => 'webhook_payload',  'label' => 'Body JSON del POST recibido', 'group' => 'Trigger webhook'];
        } elseif ($trigger === 'schedule') {
            $vars[] = ['path' => 'scheduled_at', 'label' => 'Timestamp ISO de la ejecucion', 'group' => 'Trigger schedule'];
        } elseif ($trigger === 'manual') {
            $vars[] = ['path' => 'triggered_by', 'label' => 'User ID que disparo el run', 'group' => 'Trigger manual'];
            $vars[] = ['path' => 'manual_at',    'label' => 'Timestamp ISO',              'group' => 'Trigger manual'];
            $vars[] = ['path' => 'payload',      'label' => 'Payload custom (si se paso)', 'group' => 'Trigger manual'];
        }

        return $vars;
    }

    /** Lista de agentes IA del tenant para el field type=agent_select. */
    public static function agentsForSelect(): array
    {
        $tenantId = Tenant::id();
        if (!$tenantId) return [];
        try {
            $rows = Database::fetchAll(
                "SELECT id, name, role FROM `ai_agents` WHERE `tenant_id` = :t AND `status` = 'active' ORDER BY `is_default` DESC, `name` ASC",
                ['t' => $tenantId]
            );
            return array_map(fn($r) => [
                'value' => (int) $r['id'],
                'label' => (string) $r['name'] . ($r['role'] ? ' — ' . $r['role'] : ''),
            ], $rows);
        } catch (\Throwable) {
            return [];
        }
    }
}
