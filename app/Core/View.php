<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Motor minimo de vistas con layouts y secciones.
 *
 *   View::render('dashboard.index', ['stats' => $stats], layout: 'app');
 */
final class View
{
    private static array $sections = [];
    private static array $sectionStack = [];
    private static ?string $extends = null;
    private static array $sharedData = [];

    public static function share(string $key, mixed $value): void
    {
        self::$sharedData[$key] = $value;
    }

    public static function render(string $view, array $data = [], ?string $layout = null): string
    {
        $data = array_merge(self::$sharedData, $data);

        // Renderizar la vista (puede setear $layout via extend)
        $content = self::renderFile($view, $data);

        $useLayout = self::$extends ?? $layout;

        if ($useLayout) {
            // El contenido devuelto se considera la seccion 'content' por defecto
            self::$sections['content'] = self::$sections['content'] ?? $content;
            $output = self::renderFile($useLayout, $data);
        } else {
            $output = $content;
        }

        // Reset estado tras render completo
        self::$sections = [];
        self::$extends = null;
        self::$sectionStack = [];

        return $output;
    }

    public static function display(string $view, array $data = [], ?string $layout = null): void
    {
        echo self::render($view, $data, $layout);
    }

    private static function renderFile(string $view, array $data): string
    {
        $path = self::resolve($view);
        if (!is_file($path)) {
            throw new \RuntimeException("Vista no encontrada: $view ($path)");
        }

        extract($data, EXTR_SKIP);
        ob_start();
        include $path;
        return (string) ob_get_clean();
    }

    private static function resolve(string $view): string
    {
        $base = (string) Config::get('app.paths.views');
        $relative = str_replace('.', DIRECTORY_SEPARATOR, $view) . '.php';
        return $base . DIRECTORY_SEPARATOR . $relative;
    }

    // ---- API para usar dentro de las vistas ----

    public static function extend(string $layout): void
    {
        self::$extends = $layout;
    }

    public static function start(string $name): void
    {
        self::$sectionStack[] = $name;
        ob_start();
    }

    public static function stop(): void
    {
        $name = array_pop(self::$sectionStack);
        if ($name === null) {
            return;
        }
        self::$sections[$name] = (string) ob_get_clean();
    }

    public static function section(string $name, string $default = ''): string
    {
        return self::$sections[$name] ?? $default;
    }

    public static function include(string $view, array $data = []): void
    {
        echo self::renderFile($view, array_merge(self::$sharedData, $data));
    }
}
