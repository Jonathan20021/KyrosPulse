<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Calcula el costo en USD de una llamada IA segun el modelo y los tokens
 * consumidos. Las tarifas viven en config/services.php (ai_pricing) para que
 * sean faciles de actualizar cuando Anthropic / OpenAI cambien precios.
 *
 * Si un modelo no esta listado, retorna 0 — los tokens igual se cuentan,
 * pero el costo no se imputa al budget USD del tenant.
 */
final class AiPricing
{
    /** @return array{in: float, out: float} */
    public static function rates(string $model): array
    {
        $table = (array) config('services.ai_pricing', []);
        if (isset($table[$model]) && is_array($table[$model])) {
            return [
                'in'  => (float) ($table[$model]['in']  ?? 0),
                'out' => (float) ($table[$model]['out'] ?? 0),
            ];
        }

        // Fallback: prefix-match (ej. "claude-sonnet-4-6-2025xxxx" -> "claude-sonnet-4-6").
        foreach ($table as $key => $rates) {
            if (is_string($key) && str_starts_with($model, $key)) {
                return [
                    'in'  => (float) ($rates['in']  ?? 0),
                    'out' => (float) ($rates['out'] ?? 0),
                ];
            }
        }

        return ['in' => 0.0, 'out' => 0.0];
    }

    /**
     * USD = (input_tokens / 1_000_000) * rate_in + (output_tokens / 1_000_000) * rate_out.
     * Devuelve el costo redondeado a 6 decimales (ai_logs.cost es DECIMAL(10,6)).
     */
    public static function compute(string $model, int $tokensIn, int $tokensOut): float
    {
        $rates = self::rates($model);
        $cost  = ($tokensIn / 1_000_000.0) * $rates['in']
               + ($tokensOut / 1_000_000.0) * $rates['out'];
        return round($cost, 6);
    }

    public static function isPriced(string $model): bool
    {
        $rates = self::rates($model);
        return $rates['in'] > 0 || $rates['out'] > 0;
    }
}
