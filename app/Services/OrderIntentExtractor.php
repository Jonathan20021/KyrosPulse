<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\MenuItem;
use App\Models\Message;

/**
 * Extrae items de pedido a partir de:
 *  - El estado del carrito persistente.
 *  - El ULTIMO mensaje de la IA que contenga un resumen estilo lista
 *    ("1x Smash Burger — DOP 625", "- 2× Coca Cola - 90", etc.).
 *
 * Sirve como fallback bulletproof cuando la IA enseña el resumen pero olvida
 * llamar [CART_ADD]. Cuando el cliente confirma, podemos crear la orden igual.
 */
final class OrderIntentExtractor
{
    public function __construct(private int $tenantId) {}

    /** Detecta si el mensaje del cliente es una confirmacion explicita. */
    public function isConfirmation(string $msg): bool
    {
        $clean = mb_strtolower(trim(preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $msg) ?? ''));
        if ($clean === '') return false;
        $patterns = [
            '/^(si|sí|claro|dale|listo|ok|okay|vale|perfecto|excelente|genial)\b/u',
            '/\b(confirmo|confirmar|confirma|confirmalo|confirmamelo|comfirmo)\b/u',
            '/\b(va|vamos|hagamoslo|hagámoslo|procede|adelante|de una|hazlo|mandalo|envialo)\b/u',
            '/\b(esta\s+bien|está\s+bien|todo\s+bien|asi\s+esta|así\s+está)\b/u',
            '/\bya\s*(esta|está)\b/u',
        ];
        foreach ($patterns as $p) {
            if (preg_match($p, $clean)) return true;
        }
        return false;
    }

    /**
     * Devuelve los items detectados en los ultimos N mensajes outbound de la IA.
     * Estructura: [['name' => ..., 'qty' => 1, 'unit_price' => 625.0], ...]
     */
    public function extractItemsFromHistory(int $conversationId, int $lookback = 6): array
    {
        $messages = Message::listByConversation($this->tenantId, $conversationId, max(20, $lookback * 4));
        $messages = array_reverse($messages); // de mas reciente a mas antiguo
        $aiMessages = [];
        foreach ($messages as $m) {
            if (!empty($m['is_internal'])) continue;
            if (($m['type'] ?? 'text') === 'system') continue;
            if (($m['direction'] ?? '') !== 'outbound') continue;
            $aiMessages[] = (string) ($m['content'] ?? '');
            if (count($aiMessages) >= $lookback) break;
        }

        // Buscar el primero (mas reciente) que tenga forma de resumen con precios
        foreach ($aiMessages as $body) {
            $items = $this->parseItemsFromText($body);
            if (!empty($items)) return $items;
        }
        return [];
    }

    /**
     * Parsea lineas tipo:
     *   "1x Smash Burger — DOP 625.00"
     *   "- 2× Coca Cola — DOP 90.00"
     *   "• 1x Pinchos de pollo - 575"
     *   "Hamburguesa Clasica x2 ($1250)"
     */
    public function parseItemsFromText(string $text): array
    {
        if (trim($text) === '') return [];

        $lines = preg_split('/\r?\n/', $text) ?: [];
        $items = [];

        // Regex 1: "[bullet] [qty]x|× Nombre — DOP precio" (formato comun)
        $reMain = '/^[\s•\-\*\d\.\)]*?(?P<qty>\d+)\s*[x×]\s+(?P<name>.+?)\s*[—\-:–]\s*(?:DOP|RD\$|\$)?\s*(?P<price>\d+[\d\.,]*)/iu';
        // Regex 2: "Nombre x qty" sin precio
        $reAlt  = '/^[\s•\-\*]*(?P<name>[^\d\n].*?)\s+x\s*(?P<qty>\d+)\b/iu';

        // Stop words: lineas que NO son items aunque tengan numeros
        $stopRe = '/\b(subtotal|total|envio|env[ií]o|propina|impuesto|itbis|tax|delivery|pago|min|prep|tiempo|estimado|confirm|datos|pedido|orden)\b/iu';

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            // Saltar lineas de totales/metadata
            if (preg_match($stopRe, $line)) continue;

            if (preg_match($reMain, $line, $m)) {
                $name = trim($m['name']);
                if ($name === '' || mb_strlen($name) < 3) continue;
                $qty  = max(1, (int) $m['qty']);
                $priceRaw = (string) ($m['price'] ?? '');
                $price = (float) str_replace([','], [''], $priceRaw);
                $items[] = ['name' => $name, 'qty' => $qty, 'unit_price' => $price > 0 ? $price : null];
                continue;
            }
            if (preg_match($reAlt, $line, $m)) {
                $name = trim($m['name']);
                if (mb_strlen($name) >= 3) {
                    $items[] = ['name' => $name, 'qty' => max(1, (int) $m['qty']), 'unit_price' => null];
                }
            }
        }

        // Validar: deben ser items reales del menu (fuzzy match contra menu_items)
        $validated = [];
        foreach ($items as $it) {
            $matched = MenuItem::findByNameFuzzy($this->tenantId, $it['name']);
            if ($matched) {
                $validated[] = [
                    'name'         => (string) $matched['name'],
                    'qty'          => (int) $it['qty'],
                    'unit_price'   => $it['unit_price'] ?? (float) $matched['price'],
                    'menu_item_id' => (int) $matched['id'],
                ];
            }
        }

        return $validated;
    }

    /**
     * Extrae datos de entrega del historial: zona, direccion, tipo, pago, nombre.
     * Estos campos suelen aparecer en el resumen de la IA o en mensajes anteriores
     * del cliente.
     */
    public function extractDeliveryContext(int $conversationId): array
    {
        $messages = Message::listByConversation($this->tenantId, $conversationId, 30);
        $combined = '';
        foreach ($messages as $m) {
            if (!empty($m['is_internal'])) continue;
            if (($m['type'] ?? 'text') === 'system') continue;
            $combined .= "\n" . (string) ($m['content'] ?? '');
        }

        $ctx = [];

        // Tipo
        if (preg_match('/\b(delivery|domicilio|llevar a|entreg)/iu', $combined)) {
            $ctx['delivery_type'] = 'delivery';
        } elseif (preg_match('/\b(pickup|recoger|busc(ar|o|amos))\b/iu', $combined)) {
            $ctx['delivery_type'] = 'pickup';
        } elseif (preg_match('/\b(en\s+local|aqui|aquí|mesa|comer\s+aqui)\b/iu', $combined)) {
            $ctx['delivery_type'] = 'dine_in';
        } else {
            $ctx['delivery_type'] = 'delivery';
        }

        // Pago
        if (preg_match('/\b(efectivo|cash|en mano)\b/iu', $combined)) {
            $ctx['payment'] = 'cash';
        } elseif (preg_match('/\b(tarjeta|debito|credito|card|visa|master)\b/iu', $combined)) {
            $ctx['payment'] = 'card';
        } elseif (preg_match('/\b(transferencia|deposito|trf)\b/iu', $combined)) {
            $ctx['payment'] = 'transfer';
        } elseif (preg_match('/\b(stripe|paypal|online|link de pago)\b/iu', $combined)) {
            $ctx['payment'] = 'online';
        } else {
            $ctx['payment'] = 'cash';
        }

        // Zona: busca el nombre de zona en delivery_zones del tenant
        try {
            $zones = Database::fetchAll(
                "SELECT name, fee FROM delivery_zones WHERE tenant_id = :t AND is_active = 1",
                ['t' => $this->tenantId]
            );
            foreach ($zones as $z) {
                $needle = mb_strtolower(preg_quote((string) $z['name'], '/'));
                if (preg_match('/\b' . $needle . '\b/iu', $combined)) {
                    $ctx['zone'] = (string) $z['name'];
                    break;
                }
            }
        } catch (\Throwable) {}

        return $ctx;
    }
}
