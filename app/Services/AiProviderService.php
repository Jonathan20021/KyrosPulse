<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\AiAgent;
use App\Models\GlobalAiProvider;

/**
 * Capa de abstraccion que decide entre Claude u OpenAI segun la
 * configuracion del tenant (campo `ai_provider`). Construye el system
 * prompt unificado que incluye la base de conocimiento + el rol del agente.
 */
final class AiProviderService
{
    public function __construct(private int $tenantId, private ?int $agentId = null) {}

    public function tenantConfig(): array
    {
        $row = Database::fetch(
            "SELECT name, ai_assistant_name, ai_tone, ai_enabled, ai_provider,
                    claude_api_key, claude_model, openai_api_key, openai_model,
                    global_ai_provider_id, ai_token_quota, ai_tokens_used_period, ai_token_period_starts_at,
                    business_hours, out_of_hours_msg, welcome_message, language
             FROM tenants WHERE id = :id",
            ['id' => $this->tenantId]
        );
        return is_array($row) ? $row : [];
    }

    /**
     * Resuelve cual proveedor IA usar para este tenant.
     * Reglas (en orden):
     *   1. Si el tenant tiene su propia API key (claude o openai segun ai_provider) -> usa la propia.
     *   2. Si el super admin asigno un global_ai_provider al tenant -> usa el global asignado.
     *   3. Si hay un global_ai_provider con is_default = 1 -> usa ese.
     *   4. Else null (sin IA disponible).
     *
     * Devuelve un array con: source ('own'|'global'), provider ('claude'|'openai'),
     * api_key, model, global_provider_id (si aplica), display_name.
     */
    public function resolve(): ?array
    {
        $cfg = $this->tenantConfig();
        $providerType = strtolower((string) ($cfg['ai_provider'] ?? 'claude'));
        if (!in_array($providerType, ['claude','openai'], true)) $providerType = 'claude';

        // 1) Key propia del tenant
        $ownKey = $providerType === 'openai'
            ? trim((string) ($cfg['openai_api_key'] ?? ''))
            : trim((string) ($cfg['claude_api_key'] ?? ''));
        if ($ownKey !== '') {
            $ownModel = $providerType === 'openai'
                ? (string) ($cfg['openai_model'] ?? '')
                : (string) ($cfg['claude_model'] ?? '');
            return [
                'source'             => 'own',
                'provider'           => $providerType,
                'api_key'            => $ownKey,
                'model'              => $ownModel,
                'global_provider_id' => null,
                'display_name'       => 'Tu key (' . ucfirst($providerType) . ')',
            ];
        }

        // 2) Global asignado al tenant
        if (!empty($cfg['global_ai_provider_id'])) {
            $g = GlobalAiProvider::findById((int) $cfg['global_ai_provider_id']);
            if ($g && !empty($g['is_active'])) {
                return [
                    'source'             => 'global',
                    'provider'           => (string) $g['provider'],
                    'api_key'            => (string) $g['api_key'],
                    'model'              => (string) $g['model'],
                    'global_provider_id' => (int) $g['id'],
                    'display_name'       => (string) $g['name'],
                ];
            }
        }

        // 3) Default global del SaaS
        $g = GlobalAiProvider::findDefault();
        if ($g) {
            return [
                'source'             => 'global',
                'provider'           => (string) $g['provider'],
                'api_key'            => (string) $g['api_key'],
                'model'              => (string) $g['model'],
                'global_provider_id' => (int) $g['id'],
                'display_name'       => (string) $g['name'] . ' (default)',
            ];
        }

        return null;
    }

    /**
     * Reinicia el contador de tokens si entramos en un nuevo mes calendario.
     */
    private function ensurePeriod(): void
    {
        $cfg = $this->tenantConfig();
        $start = $cfg['ai_token_period_starts_at'] ?? null;
        $now = date('Y-m-01');
        if ($start !== $now) {
            Database::run(
                "UPDATE tenants SET ai_token_period_starts_at = :s, ai_tokens_used_period = 0 WHERE id = :id",
                ['s' => $now, 'id' => $this->tenantId]
            );
        }
    }

    /**
     * Suma tokens consumidos al periodo. Solo trackeamos si la respuesta usa
     * el provider GLOBAL (los tenants con su propia key pagan a su cuenta).
     */
    private function recordTokens(string $source, int $tokensIn, int $tokensOut): void
    {
        if ($source !== 'global') return;
        $total = max(0, $tokensIn) + max(0, $tokensOut);
        if ($total === 0) return;
        Database::run(
            "UPDATE tenants SET ai_tokens_used_period = ai_tokens_used_period + :t WHERE id = :id",
            ['t' => $total, 'id' => $this->tenantId]
        );
    }

    /**
     * Verifica si al tenant le quedan tokens disponibles (solo si usa global).
     */
    public function hasTokensAvailable(): bool
    {
        $cfg = $this->tenantConfig();
        $resolved = $this->resolve();
        if (!$resolved || $resolved['source'] !== 'global') return true; // tenant con key propia, sin limite del SaaS

        $quota = $cfg['ai_token_quota'] !== null ? (int) $cfg['ai_token_quota'] : null;
        if ($quota === null || $quota <= 0) return true; // sin cuota = ilimitado
        $used = (int) ($cfg['ai_tokens_used_period'] ?? 0);
        return $used < $quota;
    }

    /** Resumen util para mostrar al usuario en el dashboard. */
    public function tokenSummary(): array
    {
        $cfg = $this->tenantConfig();
        $resolved = $this->resolve();
        $quota = $cfg['ai_token_quota'] !== null ? (int) $cfg['ai_token_quota'] : null;
        $used  = (int) ($cfg['ai_tokens_used_period'] ?? 0);
        return [
            'source'       => $resolved['source'] ?? null,
            'display_name' => $resolved['display_name'] ?? null,
            'provider'     => $resolved['provider'] ?? null,
            'model'        => $resolved['model'] ?? null,
            'quota'        => $quota,
            'used'         => $used,
            'available'    => $quota === null || $quota <= 0 ? null : max(0, $quota - $used),
            'pct'          => $quota && $quota > 0 ? min(100, (int) round($used / $quota * 100)) : 0,
            'period_start' => $cfg['ai_token_period_starts_at'] ?? null,
        ];
    }

    public function provider(): string
    {
        $r = $this->resolve();
        return $r['provider'] ?? 'claude';
    }

    public function agent(): ?array
    {
        if ($this->agentId !== null) {
            $row = Database::fetch(
                "SELECT * FROM ai_agents WHERE id = :id AND tenant_id = :t",
                ['id' => $this->agentId, 't' => $this->tenantId]
            );
            if ($row) return $row;
        }
        try {
            return AiAgent::findDefault($this->tenantId);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Detecta nombres genericos como "Soporte", "Asistente", "Bot", etc. para
     * preferir el nombre real del owner cuando esten configurados asi.
     */
    private function isGenericName(string $name): bool
    {
        $generic = [
            'soporte', 'soporte tecnico', 'soporte técnico',
            'asistente', 'asistente ia', 'asistente virtual',
            'bot', 'ia', 'ai', 'agente', 'agente ia',
            'servicio al cliente', 'atencion al cliente', 'atención al cliente',
            'mesero', 'mesera', 'vendedor', 'vendedora',
            'operador', 'operadora',
        ];
        return in_array(mb_strtolower(trim($name)), $generic, true);
    }

    /**
     * Devuelve el nombre del usuario propietario del tenant para usar como
     * identidad de la IA cuando el agente no tiene un nombre personalizado.
     */
    private function resolveTenantOwnerName(): string
    {
        try {
            $row = Database::fetch(
                "SELECT u.first_name, u.last_name
                 FROM users u
                 INNER JOIN user_roles ur ON ur.user_id = u.id AND ur.tenant_id = :t
                 INNER JOIN roles r ON r.id = ur.role_id
                 WHERE u.tenant_id = :t AND u.deleted_at IS NULL AND u.is_active = 1
                   AND r.slug IN ('owner','admin')
                 ORDER BY FIELD(r.slug, 'owner','admin'), u.id ASC
                 LIMIT 1",
                ['t' => $this->tenantId]
            );
            if ($row) {
                return trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
            }
        } catch (\Throwable) {}
        return '';
    }

    /**
     * Construye el prompt de sistema con marca, tono, rol del agente y
     * base de conocimiento. Tambien inyecta el "manual de acciones" para
     * que la IA pueda ejecutar handoff/cierre/agenda con marcadores.
     */
    public function buildSystemPrompt(string $feature, bool $allowActions = true): string
    {
        $cfg   = $this->tenantConfig();
        $agent = $this->agent();

        $brand     = $cfg['name'] ?? 'la empresa';

        // Resolver nombre del asistente: agente -> tenant.ai_assistant_name -> nombre del owner -> default.
        // Asi la IA siempre tiene una identidad humana y nunca firma como "Soporte Tecnico" generico.
        $assistant = trim((string) ($agent['name'] ?? ''));
        if ($assistant === '' || $this->isGenericName($assistant)) {
            $assistant = trim((string) ($cfg['ai_assistant_name'] ?? ''));
        }
        if ($assistant === '' || $this->isGenericName($assistant)) {
            $owner = $this->resolveTenantOwnerName();
            if ($owner !== '') $assistant = $owner;
        }
        if ($assistant === '') $assistant = 'Asistente';

        $tone      = $agent['tone'] ?? $cfg['ai_tone'] ?? 'profesional, cercano y claro';
        $lang      = $cfg['language'] ?? 'es';
        $role      = trim((string) ($agent['role'] ?? ''));
        $objective = trim((string) ($agent['objective'] ?? ''));
        $instructions = trim((string) ($agent['instructions'] ?? ''));

        $kb = Database::fetchAll(
            "SELECT category, title, content FROM knowledge_base
             WHERE tenant_id = :t AND is_active = 1
             ORDER BY sort_order ASC LIMIT 20",
            ['t' => $this->tenantId]
        );
        $kbText = '';
        foreach ($kb as $entry) {
            $kbText .= "\n[" . $entry['category'] . '] ' . $entry['title'] . "\n" . $entry['content'] . "\n";
        }

        $actionsBlock = '';
        if ($allowActions) {
            $actionsBlock = <<<ACTIONS

ACCIONES ESPECIALES (al final de tu respuesta, en una linea aparte, puedes incluir uno o mas marcadores entre corchetes; el sistema los ejecuta y los ELIMINA antes de enviar tu mensaje al cliente):
- [TRANSFER] - Pide transferir a un agente humano. Usalo si el cliente pide hablar con humano, hay queja delicada o tu no puedes resolver.
- [CLOSE_SALE: monto, producto] - Marca que se concreto una venta (ej. [CLOSE_SALE: 1500, Plan Pro]).
- [SCHEDULE: YYYY-MM-DD HH:MM, motivo] - Agenda una cita o seguimiento (ej. [SCHEDULE: 2026-05-10 15:00, demo]).
- [END_CHAT: motivo] - Cierra la conversacion porque el caso fue resuelto (ej. [END_CHAT: pedido confirmado]).
- [TAG: nombre] - Aplica una etiqueta al contacto (ej. [TAG: cliente_premium]).
- [TICKET: titulo, prioridad] - Crea un ticket de soporte (prioridad: low, medium, high, critical).
- [CART_ADD: {"items":[{"name":"Hamburguesa Clasica","qty":2,"notes":"sin cebolla","modifiers":[{"name":"Extra queso","price":50}]}]}] - Agrega items al carrito persistente cuando el cliente menciona algo (NO esperes a que confirme). El sistema acumula entre mensajes. Usa SIEMPRE que el cliente diga "quiero X", "agrega Y", "tambien una Z". El carrito se inyecta en cada turno para que no olvides.
- [CART_ADD: {"customer_name":"Maria","address":"Calle X","zone":"Naco","payment":"cash","delivery_type":"delivery"}] - Tambien agrega/actualiza datos del cliente (sin items necesariamente).
- [CART_CLEAR] - Vacia el carrito si el cliente quiere empezar de cero o cancelo.
- [ORDER: {"items":[...],"delivery_type":"delivery","address":"...","payment":"cash","customer_name":"..."}] - Crea la orden FIRME cuando el cliente CONFIRMA. Si el carrito ya tiene items, puedes emitir [ORDER:{}] vacio y el sistema usa el carrito automaticamente. delivery_type: delivery|pickup|dine_in. payment: cash|card|transfer|online.
- [ORDER_STATUS: id, status] - Cambia el estado de una orden existente. status: confirmed|preparing|ready|out_for_delivery|delivered|cancelled.
- [PAYMENT_LINK: id] - Genera y comparte el link de pago de una orden.

NUNCA muestres los marcadores en la respuesta visible. Siempre escribe primero tu mensaje al cliente y luego en linea aparte el/los marcadores si aplican.
ACTIONS;
        }

        // Modo minimal: omite bloques pesados (menu, productos, KB grande) para
        // reintentar tras un fallo (token limit, prompt demasiado largo, etc.)
        if ($this->minimalPrompt) {
            $kbText = '';
            $productsBlock   = '';
            $restaurantBlock = "\n[NOTA: el menu detallado no esta disponible en este reintento. Mantente generico, pide al cliente que precise lo que quiere; si esta listo, aplica las reglas de toma de pedidos.]\n";
        } else {
            $productsBlock   = $this->buildProductsBlock();
            $restaurantBlock = $this->buildRestaurantBlock();
        }

        return <<<PROMPT
Eres "$assistant", representante de $brand. Hablas DIRECTAMENTE con un cliente por WhatsApp.

REGLAS DE FORMATO (CRITICO - violarlas hace que el cliente reciba basura):
1. Responde SOLO con el texto del mensaje, sin etiquetas ni encabezados.
2. NUNCA antepongas tu nombre, tu rol o cualquier prefijo. Esta PROHIBIDO comenzar con "$assistant:", "$role:", "Agente:", "Asistente:", "Cliente:", "IA:", "Soporte:", "Soporte Tecnico:", "Mesero:", "Vendedor:" ni similares. Empieza DIRECTAMENTE con el saludo o la respuesta.
3. NO uses formato tipo guion teatral. NO escribas "Yo:" ni "Tu:". Solo el mensaje natural.
4. NO digas "Como modelo de IA..." ni reveles que eres una IA. Eres parte del equipo de $brand.
5. Maximo 1-3 parrafos cortos, estilo conversacional WhatsApp, sin Markdown salvo *negritas* puntuales.
6. MANEJO DEL CARRITO (CRITICO para restaurantes):
   - El sistema guarda automaticamente los items que mencionas en tu respuesta. NO TIENES que recordarlos: REVISA siempre el bloque "ESTADO ACTUAL DEL CARRITO" que te llega en el contexto antes de responder.
   - Si el carrito YA tiene items y el cliente responde con UNA palabra como "delivery", "pickup", "efectivo", "tarjeta", "Naco", etc., NO LE PREGUNTES "que te apetece ordenar". El cliente esta respondiendo la pregunta anterior. Confirma su respuesta y pide el SIGUIENTE dato faltante (direccion si es delivery, hora si es pickup, etc.) o emite el resumen final si ya tienes todo.
   - SIEMPRE que el cliente confirme ("dale", "confirmo", "perfecto", "si", "esta bien", "manda"), emite [ORDER:{}] vacio. El sistema completa con el carrito automaticamente.
   - Si el cliente confirma y el carrito esta VACIO de verdad, NO digas "no hemos armado un pedido". Pregunta natural: "Genial, dime que te apetece — combo, hamburguesa, parrilla?".
   - Antes de pedir confirmacion final, muestra resumen con totales: subtotal, envio, ITBIS, total.
   - PROHIBIDO mostrar codigo, JSON, llaves "{}", corchetes "[]" en tu respuesta visible. Los marcadores [ACCION:...] van SOLOS en una linea aparte al final, sin texto extra alrededor.

CONTEXTO COMERCIAL:
- Marca: $brand
- Tono: $tone
- Idioma: $lang
- Tu rol interno (NO lo menciones literalmente): $role
- Tu objetivo: $objective

REGLAS DE NEGOCIO:
- Usa la base de conocimiento y catalogo para responder con precisas. Si no sabes algo, no inventes: emite [TRANSFER].
- Si el cliente quiere humano, agente, asesor, persona real: emite [TRANSFER].
- Sé breve y orientado a accion. Cierra cada mensaje con UNA pregunta o paso siguiente concreto cuando sea posible.
- Cuando puedas cerrar venta, agendar o resolver, hazlo proactivamente con los marcadores.
- Adapta saludo a la hora local del cliente.

Tarea actual: $feature

Instrucciones especificas del agente:
$instructions
$actionsBlock

Base de conocimiento de $brand:
$kbText
$productsBlock
$restaurantBlock
PROMPT;
    }

    /**
     * Inyecta menu + reglas de toma de pedidos cuando el tenant es restaurante.
     */
    private function buildRestaurantBlock(): string
    {
        try {
            $tenant = Database::fetch(
                "SELECT id, uuid, is_restaurant, restaurant_settings, currency, public_menu_enabled FROM tenants WHERE id = :t",
                ['t' => $this->tenantId]
            );
        } catch (\Throwable) {
            return '';
        }
        if (!$tenant || empty($tenant['is_restaurant'])) return '';

        $settings = !empty($tenant['restaurant_settings']) ? (json_decode((string) $tenant['restaurant_settings'], true) ?: []) : [];
        $currency = (string) ($tenant['currency'] ?? 'USD');

        // Link al menu publico web. La IA debe priorizar enviar este link en
        // lugar de pegar el menu en texto plano (mejor UX, mas conversion).
        $publicMenuLink = '';
        $publicMenuRule = '';
        if (!empty($tenant['uuid']) && (int) ($tenant['public_menu_enabled'] ?? 1) === 1) {
            $publicMenuLink = url('/m/' . $tenant['uuid']);
            $publicMenuRule = "\nMENU PUBLICO ONLINE: {$publicMenuLink}\n"
                . "→ Cuando el cliente pregunte por el menu, los precios o que tienen, ENVIA SIEMPRE este link primero con un mensaje breve y amable. NO pegues la lista completa en texto plano (es ilegible en WhatsApp). Ejemplo: \"Aqui tienes nuestro menu para que armes tu pedido facil 👉 {$publicMenuLink}\". El cliente puede armar su orden en el link y volvera con el resumen para confirmar.\n"
                . "→ Si el cliente pide UN plato concreto (\"cuanto cuesta X\", \"que tienen de Y\"), responde la info especifica en el chat sin enviar el link de nuevo.\n"
                . "→ Cuando el cliente regrese del link con un mensaje tipo \"Hola! Quiero confirmar mi orden #OR-XXXX:\" YA HAY UNA ORDEN CREADA con ese codigo. Solo confirma con el cliente, no la dupliques.\n";
        }

        $menu = '';
        try {
            // Si hay link publico, mantener el bloque de menu MAS pequeno (solo
            // como referencia para responder preguntas puntuales sobre platos).
            $menu = $publicMenuLink !== ''
                ? \App\Models\MenuItem::buildPromptBlock($this->tenantId, 40, 6, 3000)
                : \App\Models\MenuItem::buildPromptBlock($this->tenantId);
        } catch (\Throwable) { $menu = ''; }

        // Zonas de entrega
        $zonesText = '';
        try {
            $zones = Database::fetchAll(
                "SELECT name, fee, eta_min, min_order FROM delivery_zones
                 WHERE tenant_id = :t AND is_active = 1 ORDER BY name ASC",
                ['t' => $this->tenantId]
            );
            if (!empty($zones)) {
                $zonesText = "\nZONAS DE ENTREGA Y COSTO:\n";
                foreach ($zones as $z) {
                    $eta = !empty($z['eta_min']) ? ' · ETA ~' . (int) $z['eta_min'] . ' min' : '';
                    $min = !empty($z['min_order']) ? ' · pedido min ' . $currency . ' ' . number_format((float) $z['min_order'], 2) : '';
                    $zonesText .= "- " . $z['name'] . " — envio " . $currency . " " . number_format((float) $z['fee'], 2) . $eta . $min . "\n";
                }
            }
        } catch (\Throwable) {}

        $methods = !empty($settings['payment_methods']) && is_array($settings['payment_methods'])
            ? implode(', ', $settings['payment_methods'])
            : 'cash, card';
        $deliveryOps = [];
        if (!empty($settings['allow_delivery'])) $deliveryOps[] = 'delivery';
        if (!empty($settings['allow_pickup']))   $deliveryOps[] = 'pickup';
        if (!empty($settings['allow_dine_in']))  $deliveryOps[] = 'dine_in';
        $deliveryStr = empty($deliveryOps) ? 'delivery' : implode(', ', $deliveryOps);
        $taxRate    = (float) ($settings['tax_rate'] ?? 0);
        $minOrder   = (float) ($settings['min_order'] ?? 0);
        $minOrderFmt = number_format($minOrder, 2);
        $prepMin    = (int) ($settings['order_prep_min'] ?? 25);

        return <<<RESTAURANT

MODO RESTAURANTE ACTIVO — eres responsable de tomar pedidos por WhatsApp:

REGLAS DE TOMA DE PEDIDOS:
1. Cuando el cliente pida ver el menu, ENVIA EL LINK del menu publico online (ver arriba). Si no hay link, presentalo organizado por categoria sin inventar platos.
2. Sugiere combos, postres y bebidas para subir el ticket promedio.
3. Cuando el cliente decida que quiere ordenar, recolecta:
   - Items y cantidades (con modificadores si aplica).
   - Tipo de pedido: $deliveryStr.
   - Si es delivery: direccion completa + zona.
   - Metodo de pago: $methods.
   - Nombre del cliente y un telefono de contacto.
4. Antes de confirmar, RESUME el pedido con totales y pregunta "¿Confirmo el pedido?".
5. Cuando el cliente confirme con "si", "confirmo", "dale", "perfecto", emite [ORDER: ...] con TODO el JSON.
6. Si el cliente quiere modificar despues de confirmar, emite [ORDER_STATUS: id, cancelled] y crea uno nuevo.
7. Tiempo de preparacion estimado: {$prepMin} minutos.
8. Pedido minimo: {$currency} {$minOrderFmt}.
9. Si te preguntan por una orden anterior, busca en el contexto de la conversacion el codigo "OR-...".
10. Tras emitir [ORDER:...] confirma al cliente con codigo provisional y comparte tiempo estimado.
$publicMenuRule
$menu
$zonesText
RESTAURANT;
    }

    private function buildProductsBlock(): string
    {
        try {
            $rows = Database::fetchAll(
                "SELECT name, sku, price, currency, stock, description
                 FROM products
                 WHERE tenant_id = :t AND is_active = 1
                 ORDER BY priority DESC, name ASC
                 LIMIT 30",
                ['t' => $this->tenantId]
            );
        } catch (\Throwable) {
            return '';
        }
        if (empty($rows)) return '';

        $out = "\nCATALOGO DE PRODUCTOS/SERVICIOS DISPONIBLES (precios en la moneda indicada):\n";
        foreach ($rows as $p) {
            $price = (float) ($p['price'] ?? 0);
            $cur   = (string) ($p['currency'] ?? 'USD');
            $stock = $p['stock'] !== null ? " | stock:" . (int) $p['stock'] : '';
            $desc  = trim((string) ($p['description'] ?? ''));
            $sku   = !empty($p['sku']) ? " [SKU:" . $p['sku'] . "]" : '';
            $out  .= sprintf("- %s%s — %s %.2f%s%s\n",
                $p['name'], $sku, $cur, $price, $stock,
                $desc !== '' ? " — " . mb_substr($desc, 0, 140) : ''
            );
        }
        return $out;
    }

    /**
     * Llama al proveedor IA resuelto. $userInput puede ser string o array
     * de mensajes [{role, content}]. Devuelve un shape uniforme.
     */
    public function call(string $feature, string|array $userInput, int $maxTokens = 1024, bool $allowActions = true): array
    {
        $resolved = $this->resolve();
        if (!$resolved) {
            return [
                'success' => false,
                'error'   => 'No hay IA configurada. Agrega tu API key en Integraciones o pide al administrador que asigne un proveedor IA global.',
            ];
        }

        // Verificar cuota si usa global
        $this->ensurePeriod();
        if ($resolved['source'] === 'global' && !$this->hasTokensAvailable()) {
            return [
                'success' => false,
                'error'   => 'Tokens IA del periodo agotados. Contacta al administrador para ampliar tu cuota o agrega tu propia API key.',
                'quota_exceeded' => true,
            ];
        }

        $system = $this->buildSystemPrompt($feature, $allowActions);
        $providerType = $resolved['provider'];
        $apiKey       = $resolved['api_key'];
        $model        = $resolved['model'] !== '' ? $resolved['model'] : ($providerType === 'openai' ? 'gpt-4o-mini' : 'claude-sonnet-4-6');

        if ($providerType === 'openai') {
            $result = $this->callOpenAi($apiKey, $model, $system, $userInput, $maxTokens);
            $modelUsed = $model;
        } else {
            $result = $this->callClaude($apiKey, $this->normalizeClaudeModel($model), $system, $userInput, $maxTokens);
            $modelUsed = $this->normalizeClaudeModel($model);
        }

        // Trackear tokens contra la cuota del tenant si usa global
        $usage = $result['usage'] ?? [];
        $this->recordTokens(
            $resolved['source'],
            (int) ($usage['input_tokens']  ?? 0),
            (int) ($usage['output_tokens'] ?? 0)
        );

        $this->logCall($feature, $userInput, $result, $modelUsed, $providerType, $resolved['source']);
        return $result;
    }

    private function callOpenAi(string $apiKey, string $model, string $system, string|array $userInput, int $maxTokens): array
    {
        $messages = [['role' => 'system', 'content' => $system]];
        if (is_array($userInput)) {
            foreach ($userInput as $m) {
                if (is_array($m) && isset($m['role'], $m['content'])) {
                    $messages[] = ['role' => (string) $m['role'], 'content' => (string) $m['content']];
                }
            }
        } else {
            $messages[] = ['role' => 'user', 'content' => $userInput];
        }

        $payload = [
            'model'       => $model,
            'messages'    => $messages,
            'max_tokens'  => $maxTokens,
            'temperature' => 0.6,
        ];

        $headers = [
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type'  => 'application/json',
        ];
        $org = (string) config('services.openai.organization', '');
        if ($org !== '') $headers['OpenAI-Organization'] = $org;

        $resp = HttpClient::post(
            (string) config('services.openai.api_url', 'https://api.openai.com/v1/chat/completions'),
            $payload, $headers,
            (int) config('services.openai.timeout', 60)
        );

        $output = '';
        if (!empty($resp['body']['choices']) && is_array($resp['body']['choices'])) {
            foreach ($resp['body']['choices'] as $c) {
                if (isset($c['message']['content']) && is_string($c['message']['content'])) {
                    $output .= $c['message']['content'];
                }
            }
        }
        $usage = $resp['body']['usage'] ?? [];

        return [
            'success' => $resp['success'],
            'text'    => trim($output),
            'usage'   => [
                'input_tokens'  => $usage['prompt_tokens']     ?? null,
                'output_tokens' => $usage['completion_tokens'] ?? null,
            ],
            'error'   => $resp['success'] ? null : ($resp['body']['error']['message'] ?? $resp['error'] ?? 'Error OpenAI.'),
            'raw'     => $resp,
        ];
    }

    private function callClaude(string $apiKey, string $model, string $system, string|array $userInput, int $maxTokens): array
    {
        $messages = is_array($userInput) ? $userInput : [['role' => 'user', 'content' => $userInput]];
        $payload = [
            'model'      => $model,
            'max_tokens' => $maxTokens,
            'system'     => $system,
            'messages'   => $messages,
        ];

        $resp = HttpClient::post(
            (string) config('services.claude.api_url'),
            $payload,
            [
                'x-api-key'         => $apiKey,
                'anthropic-version' => (string) config('services.claude.version', '2023-06-01'),
                'Content-Type'      => 'application/json',
            ],
            (int) config('services.claude.timeout', 60)
        );

        $output = '';
        if (!empty($resp['body']['content']) && is_array($resp['body']['content'])) {
            foreach ($resp['body']['content'] as $block) {
                if (($block['type'] ?? '') === 'text') $output .= ($block['text'] ?? '');
            }
        }
        $usage = $resp['body']['usage'] ?? [];

        return [
            'success' => $resp['success'],
            'text'    => trim($output),
            'usage'   => [
                'input_tokens'  => $usage['input_tokens']  ?? null,
                'output_tokens' => $usage['output_tokens'] ?? null,
            ],
            'error'   => $resp['success'] ? null : ($resp['body']['error']['message'] ?? $resp['error'] ?? 'Error Claude.'),
            'raw'     => $resp,
        ];
    }

    private function normalizeClaudeModel(string $candidate): string
    {
        $aliases = [
            'claude-sonnet-6'     => 'claude-sonnet-4-6',
            'claude-opus-6'       => 'claude-opus-4-7',
            'claude-haiku-6'      => 'claude-haiku-4-5-20251001',
            'claude-3-5-sonnet'   => 'claude-sonnet-4-6',
            'claude-3-opus'       => 'claude-opus-4-7',
        ];
        return $aliases[$candidate] ?? ($candidate ?: 'claude-sonnet-4-6');
    }

    public function ping(): array
    {
        return $this->call('healthcheck', 'Responde unicamente con la palabra OK.', 8, false);
    }

    public function autoReply(string $userMessage, string $history = '', bool $minimal = false): array
    {
        $messages = [];
        if ($history !== '') {
            $messages[] = ['role' => 'user', 'content' => "Historial reciente de la conversacion:\n$history"];
            $messages[] = ['role' => 'assistant', 'content' => 'Entendido. Continuo la conversacion.'];
        }
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        // Modo minimal: usado en reintento cuando el primer intento fallo.
        // Reduce maxTokens y desactiva inyecciones grandes (menu/KB/productos)
        // via flag interno leido por buildSystemPrompt.
        $maxTokens = $minimal ? 600 : (int) config('services.claude.max_tokens', 1024);
        $this->minimalPrompt = $minimal;
        try {
            return $this->call('auto_reply', $messages, $maxTokens);
        } finally {
            $this->minimalPrompt = false;
        }
    }

    /** Flag transitorio para que buildSystemPrompt sepa si debe omitir bloques pesados. */
    private bool $minimalPrompt = false;

    public function summarizeConversation(string $transcript): array
    {
        return $this->call(
            'summarize_conversation',
            "Resume esta conversacion en 3-4 frases destacando intencion del cliente, pendientes y proxima accion sugerida:\n\n$transcript",
            400,
            false
        );
    }

    public function suggestReply(string $transcript): array
    {
        return $this->call(
            'suggest_reply',
            "Sugiere una respuesta breve, profesional y empatica que un agente humano pueda enviar ahora. Sin saludo si la conversacion ya esta en curso.\n\nConversacion:\n$transcript",
            512,
            false
        );
    }

    public function evaluateSentiment(string $message): array
    {
        return $this->call(
            'evaluate_sentiment',
            "Clasifica el sentimiento del siguiente mensaje como positive, neutral o negative. Responde SOLO la palabra.\n\n$message",
            10,
            false
        );
    }

    public function detectIntent(string $message): array
    {
        return $this->call(
            'detect_intent',
            "Analiza este mensaje y responde SOLO en JSON valido con: {\"intent\":\"...\",\"sentiment\":\"positive|neutral|negative\",\"urgency\":\"low|normal|high\"}.\n\nMensaje: $message",
            300,
            false
        );
    }

    public function scoreLead(string $contactInfo, string $conversationSummary = ''): array
    {
        return $this->call(
            'score_lead',
            "Califica este lead del 1 al 100 segun probabilidad de cierre. Responde SOLO en JSON: {\"score\":<int>,\"reason\":\"...\",\"next_action\":\"...\"}.\n\nDatos: $contactInfo\n\nResumen: $conversationSummary",
            300,
            false
        );
    }

    /**
     * Transforma un texto del agente (composer) según una intención: mejorar redacción,
     * cambiar tono, traducir, acortar, expandir, corregir gramática.
     *
     * Devuelve solo el texto transformado, sin comillas ni preámbulo.
     */
    public function transformText(string $text, string $mode, string $context = ''): array
    {
        $text = trim($text);
        if ($text === '') {
            return ['success' => false, 'error' => 'Texto vacío'];
        }

        $instruction = match ($mode) {
            'improve'    => 'Mejora la redacción de este mensaje manteniendo su intención. Hazlo más claro, profesional y conciso. NO añadas explicaciones.',
            'formal'     => 'Reescribe este mensaje en un tono formal, profesional y respetuoso. Mantén el contenido original. NO añadas explicaciones.',
            'casual'     => 'Reescribe este mensaje en un tono amigable, cercano y conversacional. Mantén el contenido original. NO añadas explicaciones.',
            'shorter'    => 'Acorta este mensaje al máximo manteniendo la idea principal. NO añadas explicaciones.',
            'longer'     => 'Expande este mensaje añadiendo detalles relevantes y contexto útil. Mantén el tono. NO añadas explicaciones.',
            'fix'        => 'Corrige errores de ortografía, gramática y puntuación de este mensaje. NO cambies el contenido ni el tono.',
            'translate'  => 'Traduce este mensaje al idioma del cliente según el contexto de la conversación. Si la conversación está en español, traduce al inglés y viceversa. NO añadas explicaciones.',
            'continue'   => 'Continúa este mensaje del agente de forma natural, escribiendo la siguiente frase o dos. NO repitas lo escrito.',
            'emojify'    => 'Añade emojis apropiados a este mensaje sin cambiar el contenido. Máximo 3 emojis bien colocados.',
            default      => 'Mejora la redacción de este mensaje manteniendo su intención.',
        };

        $prompt  = "$instruction\n\n";
        if ($context !== '') {
            $prompt .= "Contexto de la conversación:\n$context\n\n";
        }
        $prompt .= "Mensaje original del agente:\n$text\n\nMensaje transformado:";

        $maxTokens = $mode === 'shorter' ? 200 : ($mode === 'longer' ? 600 : 400);

        return $this->call("transform_$mode", $prompt, $maxTokens, false);
    }

    public function recommendNextAction(string $context): array
    {
        return $this->call(
            'recommend_next_action',
            "Recomienda la mejor proxima accion comercial para este lead. Responde en una frase corta accionable.\n\nContexto: $context",
            150,
            false
        );
    }

    public function generateCampaignMessage(string $audience, string $goal): array
    {
        return $this->call(
            'generate_campaign',
            "Crea un mensaje de WhatsApp comercial breve (max 350 caracteres) para esta audiencia: $audience. Objetivo: $goal. Incluye una llamada a la accion clara.",
            400,
            false
        );
    }


    private function logCall(string $feature, mixed $prompt, array $result, string $model, string $provider, string $source = 'own'): void
    {
        try {
            $usage = $result['usage'] ?? [];
            $tag = $provider . ($source === 'global' ? ':global' : '');
            Database::insert('ai_logs', [
                'tenant_id'     => $this->tenantId,
                'feature'       => $feature . ":$tag",
                'model'         => $model,
                'prompt'        => is_string($prompt) ? mb_substr($prompt, 0, 4000) : (json_encode($prompt, JSON_UNESCAPED_UNICODE) ?: ''),
                'response'      => mb_substr((string) ($result['text'] ?? ''), 0, 4000),
                'tokens_input'  => $usage['input_tokens']  ?? null,
                'tokens_output' => $usage['output_tokens'] ?? null,
                'success'       => !empty($result['success']) ? 1 : 0,
                'error_message' => $result['error'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Logger::error('AI log fail', ['msg' => $e->getMessage()]);
        }
    }
}
