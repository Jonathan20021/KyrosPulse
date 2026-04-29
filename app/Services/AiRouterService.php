<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Decide cual agente IA debe atender un mensaje. Reglas (en orden):
 *   1. Si la conversacion tiene `ai_agent_id` asignado, usa ese (override manual).
 *   2. Filtra agentes activos del tenant que aceptan el canal y estan en horario.
 *   3. Match por palabras clave del mensaje contra `trigger_keywords` del agente.
 *   4. Si nadie matchea, prefiere agente con category = 'generic' o is_default = 1.
 *   5. En empates, ordena por `priority` ASC, luego is_default DESC.
 */
final class AiRouterService
{
    public function __construct(private int $tenantId) {}

    public function pickAgentForMessage(?int $conversationId, string $channel, string $message): ?array
    {
        // Override manual: el humano fijo cual agente usa esta conversacion.
        if ($conversationId) {
            $conv = Database::fetch(
                "SELECT ai_agent_id FROM conversations WHERE id = :id AND tenant_id = :t",
                ['id' => $conversationId, 't' => $this->tenantId]
            );
            if ($conv && !empty($conv['ai_agent_id'])) {
                $agent = Database::fetch(
                    "SELECT * FROM ai_agents WHERE id = :id AND tenant_id = :t AND status = 'active'",
                    ['id' => (int) $conv['ai_agent_id'], 't' => $this->tenantId]
                );
                if ($agent) return $agent;
            }
        }

        $candidates = Database::fetchAll(
            "SELECT * FROM ai_agents
             WHERE tenant_id = :t AND status = 'active'
             ORDER BY priority ASC, is_default DESC, id ASC",
            ['t' => $this->tenantId]
        );
        if (empty($candidates)) return null;

        $now = new \DateTimeImmutable('now');
        $msgLower = mb_strtolower($message, 'UTF-8');

        $available = array_values(array_filter($candidates, function ($a) use ($channel, $now) {
            return $this->channelAllowed($a, $channel) && $this->withinWorkingHours($a, $now);
        }));
        if (empty($available)) {
            // Si nadie esta disponible por horario/canal, caemos al primero activo
            // para no dejar al cliente sin respuesta. Mejor responder algo que nada.
            $available = $candidates;
        }

        // Score por keywords
        $scored = [];
        foreach ($available as $a) {
            $kws = $this->decodeJsonArray($a['trigger_keywords'] ?? null);
            $hits = 0;
            foreach ($kws as $k) {
                $k = mb_strtolower(trim((string) $k), 'UTF-8');
                if ($k === '') continue;
                if (str_contains($msgLower, $k)) $hits++;
            }
            $scored[] = ['agent' => $a, 'score' => $hits];
        }

        // Si alguno matcheo, devolvemos el de mayor score (priority desempata)
        $matched = array_filter($scored, fn ($r) => $r['score'] > 0);
        if (!empty($matched)) {
            usort($matched, function ($x, $y) {
                if ($x['score'] !== $y['score']) return $y['score'] - $x['score'];
                $px = (int) ($x['agent']['priority'] ?? 100);
                $py = (int) ($y['agent']['priority'] ?? 100);
                return $px - $py;
            });
            return $matched[0]['agent'];
        }

        // Sin match: prefiere is_default, luego category='generic', luego priority
        usort($available, function ($x, $y) {
            $dx = (int) ($x['is_default'] ?? 0);
            $dy = (int) ($y['is_default'] ?? 0);
            if ($dx !== $dy) return $dy - $dx;

            $cx = (string) ($x['category'] ?? '') === 'generic' ? 0 : 1;
            $cy = (string) ($y['category'] ?? '') === 'generic' ? 0 : 1;
            if ($cx !== $cy) return $cx - $cy;

            return ((int) $x['priority']) - ((int) $y['priority']);
        });
        return $available[0] ?? null;
    }

    /** Detecta si el cliente quiere humano segun transfer_keywords del agente. */
    public function clientWantsHuman(array $agent, string $message): bool
    {
        $kws = $this->decodeJsonArray($agent['transfer_keywords'] ?? null);
        if (empty($kws)) {
            // Defaults sensatos: todos los tenants los heredan.
            $kws = ['humano', 'agente humano', 'persona', 'asesor real', 'hablar con alguien', 'representante'];
        }
        $msg = mb_strtolower($message, 'UTF-8');
        foreach ($kws as $k) {
            $k = mb_strtolower(trim((string) $k), 'UTF-8');
            if ($k !== '' && str_contains($msg, $k)) return true;
        }
        return false;
    }

    private function channelAllowed(array $agent, string $channel): bool
    {
        $channels = $this->decodeJsonArray($agent['channels'] ?? null);
        if (empty($channels)) return true; // sin restriccion = todos
        return in_array(strtolower($channel), array_map('strtolower', $channels), true);
    }

    /**
     * working_hours es un JSON tipo:
     *   { "monday": {"enabled": true, "start":"09:00","end":"18:00"}, ... }
     * Si no existe o no esta habilitado para ese dia, se considera disponible.
     */
    private function withinWorkingHours(array $agent, \DateTimeImmutable $now): bool
    {
        $hours = $this->decodeJsonArray($agent['working_hours'] ?? null);
        if (empty($hours)) return true;

        $dayKey = strtolower($now->format('l')); // monday/tuesday/...
        $cfg = $hours[$dayKey] ?? null;
        if (!is_array($cfg)) return true;
        if (empty($cfg['enabled'])) return false;

        $start = (string) ($cfg['start'] ?? '00:00');
        $end   = (string) ($cfg['end']   ?? '23:59');
        $cur = $now->format('H:i');
        return $cur >= $start && $cur <= $end;
    }

    private function decodeJsonArray(mixed $raw): array
    {
        if (is_array($raw)) return $raw;
        if (!is_string($raw) || $raw === '') return [];
        $d = json_decode($raw, true);
        return is_array($d) ? $d : [];
    }
}
