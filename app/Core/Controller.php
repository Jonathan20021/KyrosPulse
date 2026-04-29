<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Controlador base con helpers comunes.
 */
abstract class Controller
{
    protected function view(string $view, array $data = [], ?string $layout = null): void
    {
        View::display($view, $data, $layout);
    }

    protected function json(array|object $data, int $status = 200): void
    {
        Response::json($data, $status);
    }

    protected function redirect(string $to, int $status = 302): void
    {
        Response::redirect(url($to), $status);
    }

    protected function back(): void
    {
        Response::back();
    }

    protected function flash(string $key, mixed $value): void
    {
        Session::flash($key, $value);
    }

    protected function withErrors(array $errors, array $oldInput = []): void
    {
        Session::flash('errors', $errors);
        if (!empty($oldInput)) {
            Session::setOldInput($oldInput);
        }
    }

    protected function validate(Request $request, array $rules, array $messages = []): array
    {
        $data = $request->input();
        $validator = new Validator($data, $rules, $messages);
        if ($validator->fails()) {
            if ($request->expectsJson()) {
                Response::json([
                    'message' => 'Validacion fallida.',
                    'errors'  => $validator->errors(),
                ], 422);
                exit;
            }
            $this->withErrors($validator->errors(), $data);
            $this->back();
            exit;
        }
        return $data;
    }

    protected function abort(int $status = 404, string $message = ''): never
    {
        http_response_code($status);
        if ($message === '') {
            $message = match ($status) {
                403 => 'Acceso prohibido.',
                404 => 'Recurso no encontrado.',
                422 => 'Datos no procesables.',
                500 => 'Error interno del servidor.',
                default => 'Error.',
            };
        }
        echo $message;
        exit;
    }
}
