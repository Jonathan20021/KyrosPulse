<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Tenant;

/**
 * Base de los controllers JSON de /api/v1/*.
 *
 * Envelope de respuesta estandarizado:
 *   { "data": ... , "meta": { "request_id": "...", ... } }
 *   { "error": { "code": "...", "message": "...", "request_id": "..." } }
 *
 * Todas las subclases asumen que ApiAuthMiddleware ya autentico la key
 * y fijo el tenant via Tenant::setCurrent().
 */
abstract class ApiController extends Controller
{
    protected function tenantId(): int
    {
        $id = Tenant::id();
        if (!$id) {
            $this->error('no_tenant', 'No tenant resolved for this request.', 500);
        }
        return $id;
    }

    protected function apiKey(): array
    {
        return $GLOBALS['__api_key'] ?? [];
    }

    protected function requestId(): string
    {
        return (string) ($GLOBALS['__api_request_id'] ?? '');
    }

    protected function ok(array|object $data, int $status = 200, array $extraMeta = []): never
    {
        $payload = [
            'data' => $data,
            'meta' => array_merge([
                'request_id' => $this->requestId(),
                'tenant_id'  => Tenant::id(),
            ], $extraMeta),
        ];
        Response::json($payload, $status);
        exit;
    }

    protected function paginated(array $items, int $page, int $perPage, ?int $total = null): never
    {
        $this->ok($items, 200, [
            'page'      => $page,
            'per_page'  => $perPage,
            'total'     => $total,
            'has_more'  => $total !== null ? ($page * $perPage) < $total : count($items) >= $perPage,
        ]);
    }

    protected function created(array|object $data): never
    {
        $this->ok($data, 201);
    }

    protected function noContent(): never
    {
        http_response_code(204);
        exit;
    }

    protected function error(string $code, string $message, int $status = 400, array $details = []): never
    {
        $payload = [
            'error' => [
                'code'       => $code,
                'message'    => $message,
                'request_id' => $this->requestId(),
            ],
        ];
        if ($details) {
            $payload['error']['details'] = $details;
        }
        Response::json($payload, $status);
        exit;
    }

    /**
     * Validacion ligera. $rules: ['email' => 'required|email', 'qty' => 'required|int|min:1']
     * Soporta: required, email, int, numeric, string, min:N, max:N, in:a,b,c, url
     */
    protected function validateApi(Request $request, array $rules): array
    {
        $input  = $request->input();
        $errors = [];
        $clean  = [];

        foreach ($rules as $field => $rule) {
            $checks = explode('|', $rule);
            $val    = $input[$field] ?? null;
            $isReq  = in_array('required', $checks, true);

            if ($isReq && ($val === null || $val === '' || (is_array($val) && empty($val)))) {
                $errors[$field] = 'is required';
                continue;
            }
            if ($val === null || $val === '') {
                $clean[$field] = $val;
                continue;
            }

            foreach ($checks as $check) {
                if ($check === '' || $check === 'required') continue;
                if ($check === 'email' && !filter_var($val, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field] = 'invalid email';
                } elseif ($check === 'int' && filter_var($val, FILTER_VALIDATE_INT) === false) {
                    $errors[$field] = 'must be integer';
                } elseif ($check === 'numeric' && !is_numeric($val)) {
                    $errors[$field] = 'must be numeric';
                } elseif ($check === 'string' && !is_string($val)) {
                    $errors[$field] = 'must be string';
                } elseif ($check === 'url' && !filter_var($val, FILTER_VALIDATE_URL)) {
                    $errors[$field] = 'invalid url';
                } elseif (str_starts_with($check, 'min:')) {
                    $n = (int) substr($check, 4);
                    if ((is_string($val) && mb_strlen($val) < $n) || (is_numeric($val) && $val < $n)) {
                        $errors[$field] = "must be >= $n";
                    }
                } elseif (str_starts_with($check, 'max:')) {
                    $n = (int) substr($check, 4);
                    if ((is_string($val) && mb_strlen($val) > $n) || (is_numeric($val) && $val > $n)) {
                        $errors[$field] = "must be <= $n";
                    }
                } elseif (str_starts_with($check, 'in:')) {
                    $list = explode(',', substr($check, 3));
                    if (!in_array((string) $val, $list, true)) {
                        $errors[$field] = 'must be one of: ' . implode(',', $list);
                    }
                }
            }
            $clean[$field] = $val;
        }

        if ($errors) {
            $this->error('validation_failed', 'One or more fields failed validation.', 422, $errors);
        }

        return $clean;
    }

    /** Pagina (1-based) y per_page con limites razonables. */
    protected function pagination(Request $request, int $defaultPerPage = 25, int $maxPerPage = 100): array
    {
        $page    = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min($maxPerPage, (int) $request->query('per_page', $defaultPerPage)));
        $offset  = ($page - 1) * $perPage;
        return [$page, $perPage, $offset];
    }
}
