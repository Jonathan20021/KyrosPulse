<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Events;
use App\Core\Logger;
use App\Models\Contact;
use App\Models\Workflow;

/**
 * Motor de workflows orquestables (v2). Diferente del AutomationEngine v1
 * (lineal trigger->conditions->actions): aqui hay steps tipados con
 * contexto persistido entre runs, branching, delays y reanudacion via cron.
 *
 * Triggers:
 *   - event:    suscribe a Events::dispatch (ej. "order.created").
 *   - schedule: cron expression evaluada por workflow:scheduler (cada minuto).
 *   - webhook:  URL publica /workflows/run/{token} POST con JSON body.
 *   - manual:   se dispara desde admin UI o API.
 *
 * Step types:
 *   - action:   ejecuta una accion. config = { action, params }
 *   - branch:   evalua expression. Si true sigue branch_yes, si false branch_no.
 *   - delay:    pausa el run hasta wait_until. Cron worker lo reanuda.
 *   - set_var:  guarda valor en context.
 *   - end:      termina el run con status indicado (default success).
 *
 * Resolucion de placeholders {{ vars.x.y }} en strings de config:
 *   "Hola {{ contact.first_name }}, tu orden {{ order.code }}"
 */
final class WorkflowEngine
{
    /** Hook al sistema de eventos para que workflows con trigger=event se disparen. */
    public static function bootstrap(): void
    {
        Events::listen('*', function (array $payload): void {
            $event = (string) ($payload['_event'] ?? '');
            $tenantId = (int) ($payload['tenant_id'] ?? 0);
            if ($event === '' || $tenantId === 0 || str_starts_with($event, '_')) return;
            try {
                self::onEvent($tenantId, $event, $payload);
            } catch (\Throwable $e) {
                Logger::warning('WorkflowEngine event listener fallo', ['event' => $event, 'msg' => $e->getMessage()]);
            }
        });
    }

    public static function onEvent(int $tenantId, string $event, array $payload): void
    {
        $matching = Workflow::listActiveByEvent($tenantId, $event);
        if (empty($matching)) return;

        $clean = $payload;
        unset($clean['_event']);
        foreach ($matching as $wf) {
            self::startAndRun((int) $wf['id'], $tenantId, 'event', [
                'event'   => $event,
                'payload' => $clean,
            ]);
        }
    }

    /**
     * Ejecuta un workflow desde el primer step. context = vars iniciales.
     */
    public static function startAndRun(int $workflowId, int $tenantId, string $triggerType, array $context): ?int
    {
        $first = Workflow::firstStep($workflowId);
        if (!$first) return null;

        $runId = Workflow::startRun($tenantId, $workflowId, $triggerType, $context, (string) $first['step_key']);
        Workflow::touchRun($workflowId);
        self::resume($runId);
        return $runId;
    }

    /**
     * Reanuda un run desde su current_step_key. Iteracion bounded (max 50 steps
     * por tick) para evitar loops infinitos. Si encuentra delay, deja status=
     * 'waiting' con wait_until y termina el tick — el cron lo retoma.
     */
    public static function resume(int $runId): array
    {
        $run = Database::fetch("SELECT * FROM `workflow_runs` WHERE `id` = :i LIMIT 1", ['i' => $runId]);
        if (!$run) return ['ok' => false, 'error' => 'run not found'];
        if (in_array($run['status'], ['succeeded','failed','cancelled'], true)) {
            return ['ok' => true, 'final' => true];
        }

        Workflow::updateRun($runId, ['status' => 'running']);

        $tenantId = (int) $run['tenant_id'];
        $context  = self::decodeContext($run['context']);
        $stepKey  = (string) $run['current_step_key'];
        $maxIters = 50;

        for ($i = 0; $i < $maxIters; $i++) {
            if ($stepKey === '') break;
            $step = Workflow::findStep((int) $run['workflow_id'], $stepKey);
            if (!$step) {
                self::finishRun($runId, 'failed', "Step '$stepKey' no encontrado");
                return ['ok' => false, 'error' => 'step missing'];
            }

            $type = (string) $step['type'];
            $cfg  = self::decodeJson($step['config'] ?? null) ?: [];
            $start = microtime(true);

            try {
                $next = self::executeStep($runId, $tenantId, $context, $step, $cfg);
                $latency = (int) round((microtime(true) - $start) * 1000);

                if ($next['action'] === 'wait') {
                    Workflow::updateRun($runId, [
                        'status'           => 'waiting',
                        'current_step_key' => $stepKey,
                        'wait_until'       => $next['wait_until'],
                        'context'          => $context,
                    ]);
                    Workflow::logRunStep($runId, $tenantId, $stepKey, $type, 'succeeded', $cfg, ['wait_until' => $next['wait_until']], null, $latency);
                    return ['ok' => true, 'waiting_until' => $next['wait_until']];
                }

                if ($next['action'] === 'end') {
                    Workflow::logRunStep($runId, $tenantId, $stepKey, $type, 'succeeded', $cfg, ['final_status' => $next['final_status'] ?? 'success'], null, $latency);
                    self::finishRun($runId, $next['final_status'] ?? 'succeeded', null, $context);
                    return ['ok' => true, 'final' => true];
                }

                Workflow::logRunStep($runId, $tenantId, $stepKey, $type, 'succeeded', $cfg, $next['output'] ?? null, null, $latency);
                $stepKey = (string) ($next['next'] ?? '');
                if ($stepKey === '') {
                    // Fin natural: no hay next.
                    self::finishRun($runId, 'succeeded', null, $context);
                    return ['ok' => true, 'final' => true];
                }
                Workflow::updateRun($runId, ['current_step_key' => $stepKey, 'context' => $context]);

            } catch (\Throwable $e) {
                $latency = (int) round((microtime(true) - $start) * 1000);
                Workflow::logRunStep($runId, $tenantId, $stepKey, $type, 'failed', $cfg, null, $e->getMessage(), $latency);
                self::finishRun($runId, 'failed', $e->getMessage(), $context);
                return ['ok' => false, 'error' => $e->getMessage()];
            }
        }

        // Hit del limite de iteraciones: pausamos y dejamos al cron seguir
        Workflow::updateRun($runId, ['status' => 'waiting', 'wait_until' => date('Y-m-d H:i:s', time() + 60), 'current_step_key' => $stepKey, 'context' => $context]);
        return ['ok' => true, 'iter_limit_reached' => true];
    }

    /**
     * Ejecuta un step y devuelve {action: 'continue|wait|end', next?, wait_until?, final_status?, output?}.
     */
    private static function executeStep(int $runId, int $tenantId, array &$context, array $step, array $cfg): array
    {
        switch ($step['type']) {
            case 'action':
                $output = self::runAction($tenantId, $context, $cfg);
                $context['last'] = $output;
                return ['action' => 'continue', 'next' => (string) ($step['next_step_key'] ?? ''), 'output' => $output];

            case 'branch':
                $expr = (string) ($cfg['expr'] ?? '');
                $val  = self::resolveValue($expr, $context);
                $cond = self::evalCondition($val, (string) ($cfg['op'] ?? 'truthy'), $cfg['value'] ?? null);
                $next = $cond ? (string) $step['branch_yes'] : (string) $step['branch_no'];
                return ['action' => 'continue', 'next' => $next, 'output' => ['cond' => $cond, 'expr' => $expr]];

            case 'delay':
                $secs = max(1, (int) ($cfg['seconds'] ?? 60));
                return ['action' => 'wait', 'wait_until' => date('Y-m-d H:i:s', time() + $secs)];

            case 'set_var':
                $key = (string) ($cfg['key'] ?? '');
                if ($key === '') throw new \RuntimeException("set_var: key vacio");
                $value = self::resolveTemplate($cfg['value'] ?? null, $context);
                self::setNested($context, $key, $value);
                return ['action' => 'continue', 'next' => (string) ($step['next_step_key'] ?? ''), 'output' => [$key => $value]];

            case 'end':
                return ['action' => 'end', 'final_status' => (string) ($cfg['status'] ?? 'succeeded')];
        }
        throw new \RuntimeException('Step type desconocido: ' . $step['type']);
    }

    /**
     * Acciones disponibles. Cada una recibe params del config y el context
     * resuelto (con placeholders {{ }} ya expandidos por resolveTemplate).
     */
    private static function runAction(int $tenantId, array &$context, array $cfg): array
    {
        $action = (string) ($cfg['action'] ?? '');
        $params = self::resolveTemplate($cfg['params'] ?? [], $context);
        if (!is_array($params)) $params = [];

        switch ($action) {
            case 'send_whatsapp':
                $to   = (string) ($params['to'] ?? '');
                $text = (string) ($params['text'] ?? '');
                if ($to === '' || $text === '') throw new \RuntimeException("send_whatsapp: 'to' y 'text' requeridos");
                $r = (new ChannelDispatcher($tenantId))->sendText($to, $text, isset($params['channel_id']) ? (int) $params['channel_id'] : null);
                if (empty($r['success'])) throw new \RuntimeException('send_whatsapp fallo: ' . ($r['error'] ?? 'unknown'));
                return ['sent' => true, 'external_id' => $r['body']['id'] ?? null];

            case 'run_agent':
                $agentId = (int) ($params['agent_id'] ?? 0);
                $input   = (string) ($params['input'] ?? '');
                if ($agentId === 0 || $input === '') throw new \RuntimeException("run_agent: 'agent_id' y 'input' requeridos");
                $provider = new AiProviderService($tenantId, $agentId);
                $provider->withContext(['user_message' => $input]);
                $r = $provider->autoReply($input, (string) ($params['history'] ?? ''));
                if (empty($r['success'])) throw new \RuntimeException('run_agent fallo: ' . ($r['error'] ?? 'no text'));
                return [
                    'output'    => (string) $r['text'],
                    'tokens_in' => (int) ($r['tokens_in'] ?? 0),
                    'tokens_out'=> (int) ($r['tokens_out'] ?? 0),
                    'cost_usd'  => (float) ($r['cost_usd'] ?? 0),
                ];

            case 'http':
                $method = strtoupper((string) ($params['method'] ?? 'GET'));
                $url    = (string) ($params['url'] ?? '');
                if ($url === '') throw new \RuntimeException("http: 'url' requerido");
                $headers = is_array($params['headers'] ?? null) ? $params['headers'] : [];
                $body    = $params['body'] ?? null;
                $timeout = (int) ($params['timeout'] ?? 15);
                $r = HttpClient::request($method, $url, $headers, $body, $timeout);
                if (empty($r['success'])) {
                    throw new \RuntimeException('http ' . ($r['status'] ?? 0) . ': ' . ($r['error'] ?? 'unknown'));
                }
                return ['status' => $r['status'], 'body' => $r['body']];

            case 'add_tag':
                $contactId = (int) ($params['contact_id'] ?? ($context['payload']['contact_id'] ?? 0));
                $tag = trim((string) ($params['tag'] ?? ''));
                if ($contactId === 0 || $tag === '') throw new \RuntimeException("add_tag: 'contact_id' y 'tag' requeridos");
                $c = Contact::findById($contactId);
                if (!$c) throw new \RuntimeException('contact not found');
                $tags = $c['tags'] ? json_decode((string) $c['tags'], true) : [];
                if (!is_array($tags)) $tags = [];
                if (!in_array($tag, $tags, true)) {
                    $tags[] = $tag;
                    Contact::update($tenantId, $contactId, ['tags' => json_encode($tags, JSON_UNESCAPED_UNICODE)]);
                }
                return ['tags' => $tags];

            case 'webhook_out':
                // Un webhook saliente "ad hoc" desde el workflow (no requiere subscripcion)
                $url = (string) ($params['url'] ?? '');
                $secret = (string) ($params['secret'] ?? '');
                if ($url === '') throw new \RuntimeException("webhook_out: 'url' requerido");
                $body = json_encode($params['payload'] ?? $context, JSON_UNESCAPED_UNICODE);
                $headers = ['Content-Type' => 'application/json'];
                if ($secret !== '') {
                    $headers['X-Kyros-Signature'] = WebhookDispatcher::sign($body, $secret);
                }
                $r = HttpClient::request('POST', $url, $headers, $body, 15);
                if (empty($r['success'])) throw new \RuntimeException('webhook_out fallo: HTTP ' . ($r['status'] ?? 0));
                return ['status' => $r['status']];

            case 'log':
                $msg = (string) ($params['message'] ?? '');
                Logger::info('Workflow log', ['msg' => $msg, 'tenant_id' => $tenantId]);
                return ['logged' => $msg];

            case 'noop':
                return ['ok' => true];
        }
        throw new \RuntimeException("Accion desconocida: $action");
    }

    /**
     * Worker: reanuda runs en estado 'waiting' cuyo wait_until ya cumplio.
     */
    public static function processWaitingRuns(int $maxBatch = 100): array
    {
        $rows = Workflow::listResumableRuns($maxBatch);
        $ok = 0; $fail = 0;
        foreach ($rows as $r) {
            $res = self::resume((int) $r['id']);
            if ($res['ok'] ?? false) $ok++; else $fail++;
        }
        return ['processed' => count($rows), 'resumed' => $ok, 'failed' => $fail];
    }

    /**
     * Worker: dispara workflows con trigger=schedule cuyo next_run_at cumplio.
     * Soporta cron expresiones simples (5 campos) via crontabPasses().
     */
    public static function processScheduledTriggers(int $maxBatch = 50): array
    {
        $rows = Workflow::listScheduledDue();
        $started = 0;
        foreach ($rows as $wf) {
            $cfg = $wf['trigger_config'] ? json_decode((string) $wf['trigger_config'], true) : [];
            $cron = (string) ($cfg['cron'] ?? '');
            if ($cron !== '' && !self::cronPasses($cron, time())) continue;
            try {
                self::startAndRun((int) $wf['id'], (int) $wf['tenant_id'], 'schedule', ['scheduled_at' => date('c')]);
                $next = self::nextRunFromCron($cron);
                Workflow::touchRun((int) $wf['id'], $next);
                $started++;
            } catch (\Throwable $e) {
                Logger::warning('Workflow schedule fallo', ['wf' => $wf['id'], 'msg' => $e->getMessage()]);
            }
        }
        return ['evaluated' => count($rows), 'started' => $started];
    }

    /**
     * Trigger manual desde admin/API. Devuelve run id.
     */
    public static function triggerManual(int $tenantId, int $workflowId, array $context = []): ?int
    {
        return self::startAndRun($workflowId, $tenantId, 'manual', $context);
    }

    /**
     * Trigger via webhook publico. Llamado desde controller publico.
     */
    public static function triggerByWebhookToken(string $token, array $body): ?array
    {
        $wf = Workflow::findByWebhookToken($token);
        if (!$wf) return null;
        $runId = self::startAndRun((int) $wf['id'], (int) $wf['tenant_id'], 'webhook', ['webhook_payload' => $body]);
        return $runId ? ['run_id' => $runId, 'workflow_id' => (int) $wf['id']] : null;
    }

    // ---- Helpers ----

    private static function finishRun(int $runId, string $status, ?string $error = null, ?array $context = null): void
    {
        $patch = [
            'status'      => in_array($status, ['succeeded','failed','cancelled'], true) ? $status : 'failed',
            'finished_at' => date('Y-m-d H:i:s'),
        ];
        if ($error !== null) $patch['error'] = mb_substr($error, 0, 4000);
        if ($context !== null) $patch['context'] = $context;
        Workflow::updateRun($runId, $patch);
    }

    private static function decodeContext(mixed $raw): array
    {
        if (!$raw) return [];
        if (is_array($raw)) return $raw;
        $d = json_decode((string) $raw, true);
        return is_array($d) ? $d : [];
    }

    private static function decodeJson(mixed $raw): ?array
    {
        if (!$raw) return null;
        if (is_array($raw)) return $raw;
        $d = json_decode((string) $raw, true);
        return is_array($d) ? $d : null;
    }

    /** Resuelve "vars.contact.first_name" sobre el context anidado. */
    private static function resolveValue(string $path, array $ctx): mixed
    {
        if ($path === '') return null;
        $parts = explode('.', $path);
        $cur = $ctx;
        foreach ($parts as $p) {
            if (is_array($cur) && array_key_exists($p, $cur)) {
                $cur = $cur[$p];
            } else {
                return null;
            }
        }
        return $cur;
    }

    /** Set anidado: setNested($ctx, 'a.b.c', 1) crea {a:{b:{c:1}}}. */
    private static function setNested(array &$ctx, string $path, mixed $value): void
    {
        $parts = explode('.', $path);
        $ref = &$ctx;
        foreach ($parts as $i => $p) {
            if ($i === count($parts) - 1) {
                $ref[$p] = $value;
                return;
            }
            if (!isset($ref[$p]) || !is_array($ref[$p])) {
                $ref[$p] = [];
            }
            $ref = &$ref[$p];
        }
    }

    /** Reemplaza {{ vars.x.y }} en strings. Recursivo en arrays. */
    private static function resolveTemplate(mixed $value, array $ctx): mixed
    {
        if (is_string($value)) {
            return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.\[\]]+)\s*\}\}/', function ($m) use ($ctx) {
                $val = self::resolveValue($m[1], $ctx);
                if ($val === null) return '';
                if (is_scalar($val)) return (string) $val;
                return json_encode($val, JSON_UNESCAPED_UNICODE);
            }, $value);
        }
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = self::resolveTemplate($v, $ctx);
            }
            return $out;
        }
        return $value;
    }

    private static function evalCondition(mixed $left, string $op, mixed $right): bool
    {
        switch ($op) {
            case 'truthy':       return (bool) $left;
            case 'falsy':        return !((bool) $left);
            case 'eq':           return (string) $left === (string) $right;
            case 'neq':          return (string) $left !== (string) $right;
            case 'gt':           return is_numeric($left) && is_numeric($right) && (float) $left > (float) $right;
            case 'gte':          return is_numeric($left) && is_numeric($right) && (float) $left >= (float) $right;
            case 'lt':           return is_numeric($left) && is_numeric($right) && (float) $left < (float) $right;
            case 'lte':          return is_numeric($left) && is_numeric($right) && (float) $left <= (float) $right;
            case 'contains':     return is_string($left) && is_string($right) && str_contains($left, $right);
            case 'not_contains': return !(is_string($left) && is_string($right) && str_contains($left, $right));
            case 'in':           return is_array($right) && in_array($left, $right, true);
        }
        return false;
    }

    /**
     * Mini cron evaluator: 5 campos (m h dom mon dow), soporta * y listas (1,5,10),
     * rangos (1-5), steps (asterisco/5). NO soporta nombres de mes/dia.
     */
    private static function cronPasses(string $cron, int $ts): bool
    {
        $parts = preg_split('/\s+/', trim($cron));
        if (!$parts || count($parts) !== 5) return false;
        $m = (int) date('i', $ts);
        $h = (int) date('G', $ts);
        $dom = (int) date('j', $ts);
        $mon = (int) date('n', $ts);
        $dow = (int) date('w', $ts);
        return self::cronField($parts[0], $m, 0, 59)
            && self::cronField($parts[1], $h, 0, 23)
            && self::cronField($parts[2], $dom, 1, 31)
            && self::cronField($parts[3], $mon, 1, 12)
            && self::cronField($parts[4], $dow, 0, 6);
    }

    private static function cronField(string $expr, int $value, int $min, int $max): bool
    {
        $expr = trim($expr);
        if ($expr === '*') return true;
        // Step: */5
        if (str_starts_with($expr, '*/')) {
            $step = (int) substr($expr, 2);
            return $step > 0 && (($value - $min) % $step === 0);
        }
        // Lista: 1,5,10
        foreach (explode(',', $expr) as $part) {
            $part = trim($part);
            if (str_contains($part, '-')) {
                [$a, $b] = explode('-', $part, 2);
                if ((int) $a <= $value && $value <= (int) $b) return true;
            } elseif (ctype_digit($part) && (int) $part === $value) {
                return true;
            }
        }
        return false;
    }

    private static function nextRunFromCron(string $cron): ?string
    {
        // Estimacion sencilla: proxima ejecucion en el siguiente minuto que matchee.
        // Bound a 7 dias para evitar loops imposibles.
        $t = time();
        for ($i = 0; $i < 60 * 24 * 7; $i++) {
            $t += 60;
            if (self::cronPasses($cron, $t)) return date('Y-m-d H:i:s', $t);
        }
        return null;
    }
}
