<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\AgentSkill;

/**
 * Hub de skills componibles. Combina sales + support + cobranza + ... en
 * un solo agente, con prioridades y un router heuristico de relevancia.
 *
 * Flujo en tiempo de ejecucion:
 *   1. AiProviderService::buildSystemPrompt llama a buildSkillsBlock($agentId, $userMsg)
 *   2. El service:
 *        - lista skills activas del agente (priority asc)
 *        - si hay $userMsg, rankea por keyword/regex match (router heuristico)
 *        - inserta el system_prompt de cada skill como bloque "MODO X"
 *        - lista los tools que la skill expone (catalogo declarativo)
 *   3. La IA recibe instrucciones de TODAS sus skills + un hint de cual aplica primero
 *
 * El router heuristico es deterministico y barato (no llama a IA). Si no
 * matchea ninguna skill, devuelve el orden por priority.
 */
final class AgentSkillService
{
    /**
     * Patrones de matching por slug. Si el mensaje del cliente contiene
     * cualquiera de estos patrones (case-insensitive), la skill se considera
     * relevante y sube en el ranking.
     *
     * Custom skills sin patrones declarativos heredan el default
     * "siempre con baja relevancia" — no penalizan, solo no impulsan.
     */
    private const KEYWORDS = [
        'sales' => [
            'precio','cuanto','cuesta','vale','comprar','pedir','quiero','catalogo','menu',
            'producto','recomendacion','recomienda','interesa','disponible','stock',
        ],
        'support' => [
            'ayuda','problema','no funciona','error','queja','fallo','no me llego','reclamo',
            'soporte','tecnico','averia','no sirve','duda','consulta',
        ],
        'cart_recover' => [
            'carrito','abandone','deje pendiente','olvide','aun puedo','sigo interesado',
        ],
        'scheduling' => [
            'cita','agendar','reservar','horario','disponibilidad','reunion','demo','llamada',
        ],
        'collections' => [
            'pago','factura','deuda','vencido','pendiente de pago','cobro','transferencia',
            'comprobante','vence','recibo',
        ],
    ];

    public function __construct(private int $tenantId) {}

    /**
     * Devuelve las skills activas del agente con datos de la skill + override
     * del link. Si no hay skills enlazadas, devuelve [].
     */
    public function activeSkills(int $agentId): array
    {
        return AgentSkill::listForAgent($this->tenantId, $agentId, true);
    }

    /**
     * Construye el bloque que se inyecta en el system prompt.
     * Ranking: si $userMsg viene, las skills con keywords matched suben.
     * Si no, orden por priority.
     */
    public function buildSkillsBlock(int $agentId, ?string $userMsg = null): string
    {
        $skills = $this->activeSkills($agentId);
        if (empty($skills)) {
            return '';
        }

        $ranked = $this->rankSkills($skills, (string) $userMsg);

        $lines = [];
        $lines[] = 'SKILLS HABILITADAS PARA ESTE AGENTE (combinables — usa la mas relevante segun el contexto del cliente):';

        $top = $ranked[0] ?? null;
        if ($top && !empty($top['_matched'])) {
            $lines[] = '';
            $lines[] = 'INTENT DETECTADO: el mensaje del cliente parece relacionado con la skill "' . $top['name'] . '" (' . $top['slug'] . '). Aplica primero esa skill, pero conserva el resto disponibles.';
        }

        foreach ($ranked as $i => $s) {
            $tools = $this->decodeJson($s['tools']);
            $linkCfg = $this->decodeJson($s['link_config'] ?? null);
            $skillCfg = $this->decodeJson($s['config']);
            $cfg = array_merge(is_array($skillCfg) ? $skillCfg : [], is_array($linkCfg) ? $linkCfg : []);

            $lines[] = '';
            $lines[] = '─── MODO ' . strtoupper((string) $s['slug']) . ' ───';
            $lines[] = 'Nombre: ' . (string) $s['name'];
            if (!empty($s['description'])) {
                $lines[] = 'Cuando aplicar: ' . (string) $s['description'];
            }
            if (!empty($s['system_prompt'])) {
                $lines[] = 'Instrucciones: ' . trim((string) $s['system_prompt']);
            }
            if (is_array($tools) && $tools) {
                $lines[] = 'Herramientas disponibles: ' . implode(', ', array_map('strval', $tools));
            }
            if ($cfg) {
                $cfgLine = $this->renderConfigHints($cfg);
                if ($cfgLine !== '') {
                    $lines[] = 'Config: ' . $cfgLine;
                }
            }
            if (!empty($s['_matched'])) {
                $lines[] = '⭐ RELEVANTE para el mensaje actual.';
            }
        }

        $lines[] = '';
        $lines[] = 'REGLA DE COMBINACION:';
        $lines[] = '- Identifica la intencion del cliente y aplica la skill mas afin como guia principal.';
        $lines[] = '- Si la conversacion cambia de intencion (ej. de ventas a soporte), cambia de skill SIN avisar al cliente — solo ajusta tu respuesta.';
        $lines[] = '- Las herramientas listadas son una guia. Las acciones REALES disponibles estan en la seccion ACCIONES ESPECIALES (mas abajo).';
        $lines[] = '- Si ninguna skill encaja, responde con el tono general del agente.';

        return implode("\n", $lines);
    }

    /**
     * Devuelve solo la skill mas relevante (top 1) para el mensaje. Util si
     * un caller quiere routing simple sin armar el bloque entero.
     */
    public function pickPrimarySkill(int $agentId, string $userMsg): ?array
    {
        $skills = $this->activeSkills($agentId);
        if (empty($skills)) return null;
        $ranked = $this->rankSkills($skills, $userMsg);
        $top = $ranked[0] ?? null;
        if (!$top || empty($top['_matched'])) return null;
        return $top;
    }

    /**
     * Estadistica simple de skills por agente para mostrar en UI.
     */
    public function summary(int $agentId): array
    {
        $skills = $this->activeSkills($agentId);
        return [
            'count'  => count($skills),
            'slugs'  => array_map(fn($s) => (string) $s['slug'], $skills),
            'names'  => array_map(fn($s) => (string) $s['name'], $skills),
        ];
    }

    /**
     * Rank por keyword match + priority. Devuelve array con `_matched` boolean
     * y `_score` numerico para debugging.
     */
    private function rankSkills(array $skills, string $userMsg): array
    {
        $msg = mb_strtolower($userMsg);
        $msg = preg_replace('/[^a-z0-9á-úñ\s]/u', ' ', $msg) ?? $msg;

        foreach ($skills as &$s) {
            $slug = (string) $s['slug'];
            $kws  = self::KEYWORDS[$slug] ?? [];
            $hits = 0;
            foreach ($kws as $kw) {
                if ($msg !== '' && str_contains($msg, $kw)) $hits++;
            }
            $priority = (int) ($s['link_priority'] ?? 100);
            // Score: matches dominan, priority desempata (menor priority = mas alto).
            $s['_matched'] = $hits > 0;
            $s['_score']   = ($hits * 100) - $priority;
        }
        unset($s);

        usort($skills, fn($a, $b) => $b['_score'] <=> $a['_score']);
        return $skills;
    }

    private function decodeJson(mixed $raw): mixed
    {
        if (!$raw) return null;
        if (is_array($raw)) return $raw;
        $decoded = json_decode((string) $raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function renderConfigHints(array $cfg): string
    {
        $bits = [];
        foreach ($cfg as $k => $v) {
            if (is_scalar($v)) {
                $bits[] = $k . '=' . (is_bool($v) ? ($v ? 'true' : 'false') : (string) $v);
            } elseif (is_array($v)) {
                $bits[] = $k . '=[' . implode(',', array_map(fn($x) => is_scalar($x) ? (string) $x : '?', $v)) . ']';
            }
        }
        return implode('; ', array_slice($bits, 0, 8));
    }
}
