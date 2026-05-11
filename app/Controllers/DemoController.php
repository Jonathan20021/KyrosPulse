<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Session;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;

/**
 * Demo express: crea una cuenta de prueba completa con datos sembrados
 * a partir de un plan elegido en la landing. La cuenta se autodestruye
 * 24 horas despues mediante cron/cleanup.php (ver tabla tenants.is_demo /
 * demo_expires_at, FK ON DELETE CASCADE limpia el resto).
 */
final class DemoController extends Controller
{
    /** Ventana de vida de la cuenta demo. */
    private const DEMO_TTL_HOURS = 24;

    /**
     * POST /demo/start
     * Body: plan = slug del plan (starter|professional|business|enterprise)
     */
    public function start(Request $request): void
    {
        $planSlug = strtolower(trim((string) $request->input('plan', 'professional')));
        $plan     = Plan::findBySlug($planSlug);
        if (!$plan) {
            // Fallback al primer plan activo
            $active = Plan::listActive();
            $plan   = $active[0] ?? null;
        }
        if (!$plan) {
            Session::flash('error', 'No hay planes disponibles para iniciar la demo. Intenta de nuevo mas tarde.');
            $this->redirect('/');
            return;
        }

        $owner = Role::findBySlug('owner');
        if (!$owner) {
            Session::flash('error', 'Configuracion incompleta. Contacta a soporte.');
            $this->redirect('/');
            return;
        }

        $ip = $request->ip();

        try {
            [$tenantId, $userId, $email, $password] = Database::transaction(function () use ($plan, $owner, $planSlug, $ip) {
                // Identificador unico legible (corto) para el demo
                $token   = strtolower(substr(bin2hex(random_bytes(4)), 0, 8));
                $name    = ucfirst($planSlug) . ' Demo ' . strtoupper(substr($token, 0, 4));
                $slug    = Tenant::generateUniqueSlug('demo-' . $planSlug . '-' . $token);
                $email   = 'demo+' . $token . '@evallish.demo';
                // Password legible por si el usuario quiere copiar/pegar para volver
                $password = 'Demo' . strtoupper(substr($token, 0, 4)) . '!';

                $expiresAt = date('Y-m-d H:i:s', time() + self::DEMO_TTL_HOURS * 3600);

                $tenantId = Tenant::create([
                    'slug'             => $slug,
                    'name'             => $name,
                    'email'            => $email,
                    'country'          => 'DO',
                    'currency'         => (string) ($plan['currency'] ?? 'USD'),
                    'timezone'         => (string) config('app.timezone', 'America/Santo_Domingo'),
                    'language'         => 'es',
                    'status'           => 'trial',
                    'is_demo'          => 1,
                    'demo_expires_at'  => $expiresAt,
                    'trial_ends_at'    => $expiresAt,
                    'plan_id'          => (int) $plan['id'],
                ]);

                $userId = User::createUser([
                    'tenant_id'         => $tenantId,
                    'first_name'        => 'Demo',
                    'last_name'         => 'User',
                    'email'             => $email,
                    'password'          => $password,
                    'language'          => 'es',
                    'is_active'         => 1,
                    'email_verified_at' => date('Y-m-d H:i:s'),
                ]);

                User::assignRole($userId, (int) $owner['id'], $tenantId);

                // Saltar onboarding para que la demo abra directo en /dashboard.
                // Las columnas onboarding_* las anade la migration 013.
                try {
                    Database::run(
                        "UPDATE tenants
                            SET onboarding_skipped     = 1,
                                onboarding_completed_at = NOW()
                          WHERE id = :id",
                        ['id' => $tenantId]
                    );
                } catch (\Throwable) {
                    // Si la migracion 013 no se aplico, ignoramos
                }

                $this->seedDemoData($tenantId);

                Logger::info('demo.tenant.created', [
                    'tenant_id'  => $tenantId,
                    'user_id'    => $userId,
                    'plan'       => $planSlug,
                    'expires_at' => $expiresAt,
                    'ip'         => $ip,
                ]);

                return [$tenantId, $userId, $email, $password];
            });

            // Auto-login: cargar el user y dejar la sesion lista
            $user = User::findById((int) $userId);
            if ($user) {
                Auth::login($user);
            }

            Csrf::rotate();
            Session::clearOldInput();

            Session::flash('success', sprintf(
                'Demo lista! Plan %s con datos de ejemplo cargados. La cuenta se borrara en 24 horas. Email: %s · Password: %s',
                (string) $plan['name'],
                $email,
                $password
            ));

            $this->redirect('/dashboard');
        } catch (\Throwable $e) {
            Logger::error('demo.start.failed', ['msg' => $e->getMessage()]);
            Session::flash('error', 'No pudimos crear la demo. Intenta de nuevo en unos momentos.');
            $this->redirect('/');
        }
    }

    /**
     * Datos minimos sembrados para que la demo se sienta real:
     * etapas de pipeline, etiquetas, respuestas rapidas y FAQ.
     * No insertamos data de conversaciones/contactos para no inflar la BD
     * (los tenants demo viven solo 24h).
     */
    private function seedDemoData(int $tenantId): void
    {
        $stages = [
            ['Nuevo lead',         'nuevo',       '#06B6D4', 10,  0, 0, 1],
            ['Contactado',         'contactado',  '#3B82F6', 25,  0, 0, 2],
            ['Interesado',         'interesado',  '#7C3AED', 50,  0, 0, 3],
            ['Cotizacion enviada', 'cotizacion',  '#A855F7', 70,  0, 0, 4],
            ['Negociacion',        'negociacion', '#F59E0B', 85,  0, 0, 5],
            ['Ganado',             'ganado',      '#22C55E', 100, 1, 0, 6],
            ['Perdido',            'perdido',     '#EF4444', 0,   0, 1, 7],
        ];
        foreach ($stages as [$nm, $sl, $cl, $pr, $w, $l, $o]) {
            Database::insert('pipeline_stages', [
                'tenant_id'   => $tenantId,
                'name'        => $nm,
                'slug'        => $sl,
                'color'       => $cl,
                'probability' => $pr,
                'is_won'      => $w,
                'is_lost'     => $l,
                'sort_order'  => $o,
            ]);
        }

        $tags = [
            ['VIP',        '#F59E0B'],
            ['Nuevo',      '#06B6D4'],
            ['Recurrente', '#22C55E'],
            ['Riesgo',     '#EF4444'],
        ];
        foreach ($tags as [$tn, $tc]) {
            try {
                Database::insert('tags', [
                    'tenant_id' => $tenantId,
                    'name'      => $tn,
                    'color'     => $tc,
                ]);
            } catch (\Throwable) {}
        }

        $replies = [
            ['/saludo',  'Saludo inicial', 'Hola! Gracias por contactar a Evallish Pulse Demo. En que podemos ayudarte hoy?'],
            ['/horario', 'Horario',        'Atendemos de lunes a viernes de 9am a 6pm.'],
            ['/gracias', 'Despedida',      'Gracias por tu mensaje. Que tengas un excelente dia!'],
        ];
        foreach ($replies as [$sh, $ti, $bd]) {
            try {
                Database::insert('quick_replies', [
                    'tenant_id' => $tenantId,
                    'shortcut'  => $sh,
                    'title'     => $ti,
                    'body'      => $bd,
                ]);
            } catch (\Throwable) {}
        }

        $kb = [
            ['empresa',   'Sobre la demo',        'Esta es una cuenta demo de Evallish Pulse. Todos los datos se eliminan a las 24 horas.', 1],
            ['horario',   'Horario de atencion',  'Atendemos de lunes a viernes 9am-6pm hora local.', 2],
            ['productos', 'Servicios principales', 'Bandeja omnicanal, agentes IA, CRM y reportes en tiempo real.', 3],
        ];
        foreach ($kb as [$cat, $title, $body, $ord]) {
            try {
                Database::insert('knowledge_base', [
                    'tenant_id'  => $tenantId,
                    'category'   => $cat,
                    'title'      => $title,
                    'content'    => $body,
                    'is_active'  => 1,
                    'sort_order' => $ord,
                ]);
            } catch (\Throwable) {}
        }
    }
}
