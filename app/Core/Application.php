<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Bootstrap de la aplicacion: carga env, config, sesion, autoload y dispatcher.
 */
final class Application
{
    private string $rootPath;
    private Router $router;

    public function __construct(string $rootPath)
    {
        $this->rootPath = rtrim($rootPath, DIRECTORY_SEPARATOR);
    }

    public function boot(): self
    {
        $this->registerAutoloader();
        Env::load($this->rootPath . DIRECTORY_SEPARATOR . '.env');
        Config::setPath($this->rootPath . DIRECTORY_SEPARATOR . 'config');

        // Cargar helpers ANTES de Config::get() porque config/*.php usa env()
        require_once $this->rootPath . '/app/Helpers/helpers.php';

        date_default_timezone_set((string) Config::get('app.timezone', 'UTC'));
        mb_internal_encoding('UTF-8');

        $debug = (bool) Config::get('app.debug', false);
        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('display_startup_errors', $debug ? '1' : '0');
        error_reporting(E_ALL);

        $this->registerErrorHandlers();
        $this->ensureStorageDirs();

        Session::start();

        // Auto-aplicar schema multi-canal e integraciones (idempotente, cacheado)
        Schema::ensure();

        // Activar motor de automatizaciones (escucha global de eventos)
        \App\Services\AutomationEngine::bootstrap();

        $this->router = new Router();
        return $this;
    }

    public function loadRoutes(): self
    {
        $router = $this->router;
        require $this->rootPath . '/routes/web.php';
        require $this->rootPath . '/routes/api.php';
        return $this;
    }

    public function run(): void
    {
        $request = new Request();
        $this->router->dispatch($request);
    }

    public function router(): Router
    {
        return $this->router;
    }

    private function registerAutoloader(): void
    {
        spl_autoload_register(function (string $class): void {
            $prefix = 'App\\';
            if (!str_starts_with($class, $prefix)) {
                return;
            }
            $relative = substr($class, strlen($prefix));
            $file = $this->rootPath . '/app/' . str_replace('\\', '/', $relative) . '.php';
            if (is_file($file)) {
                require_once $file;
            }
        });
    }

    private function registerErrorHandlers(): void
    {
        $debug = (bool) Config::get('app.debug', false);

        set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
            if (!(error_reporting() & $errno)) {
                return false;
            }
            Logger::error('PHP error', [
                'errno' => $errno, 'msg' => $errstr,
                'file' => $errfile, 'line' => $errline,
            ]);
            return false;
        });

        set_exception_handler(function (\Throwable $e) use ($debug): void {
            Logger::critical('Uncaught exception', [
                'msg'   => $e->getMessage(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            http_response_code(500);
            if ($debug) {
                echo '<h1>Error</h1><pre style="white-space:pre-wrap;font-family:monospace;padding:1rem;background:#0B1020;color:#fff;border-radius:8px">';
                echo htmlspecialchars($e->getMessage() . "\n\n" . $e->getFile() . ':' . $e->getLine() . "\n\n" . $e->getTraceAsString(), ENT_QUOTES, 'UTF-8');
                echo '</pre>';
            } else {
                echo '<h1>500 - Error interno</h1>';
            }
        });
    }

    private function ensureStorageDirs(): void
    {
        $dirs = [
            (string) Config::get('app.paths.storage'),
            (string) Config::get('app.paths.logs'),
            (string) Config::get('app.paths.cache'),
            (string) Config::get('app.paths.uploads'),
        ];
        foreach ($dirs as $dir) {
            if ($dir && !is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
        }
    }
}
