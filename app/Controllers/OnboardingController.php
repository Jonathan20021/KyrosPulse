<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Core\Tenant;
use App\Models\AiAgent;
use App\Models\Tenant as TenantModel;
use App\Models\WorkflowTemplate;
use App\Services\WorkflowTemplateService;

/**
 * Wizard de onboarding para tenants nuevos.
 *
 * 5 pasos secuenciales con state machine en tenants.onboarding_step:
 *   0 → welcome    : intro + value props
 *   1 → business   : nombre, industria, moneda, timezone
 *   2 → channel    : WhatsApp (config minima o skip)
 *   3 → agent      : crear primer agente IA o elegir preset
 *   4 → workflow   : activar primer workflow desde template
 *   5 → done       : completado
 *
 * El usuario puede saltarse el wizard entero (onboarding_skipped=1) o saltarse
 * pasos individuales. Banner persistente en dashboard hasta completar.
 */
final class OnboardingController extends Controller
{
    private const STEPS = ['welcome', 'business', 'channel', 'agent', 'workflow'];

    public function index(Request $request): void
    {
        $tenant = TenantModel::findById(Tenant::id());
        if (!$tenant) {
            $this->redirect('/login');
            return;
        }
        if (!empty($tenant['onboarding_completed_at'])) {
            $this->redirect('/dashboard');
            return;
        }

        $step = (int) ($tenant['onboarding_step'] ?? 0);
        $stepKey = self::STEPS[$step] ?? 'welcome';

        $featuredTemplates = $step >= 4 ? array_slice(WorkflowTemplate::listAvailable(Tenant::id()), 0, 4) : [];

        $this->view('onboarding.' . $stepKey, [
            'tenant'   => $tenant,
            'step'     => $step,
            'stepName' => $stepKey,
            'progress' => (int) round(($step / 5) * 100),
            'totalSteps' => 5,
            'featuredTemplates' => $featuredTemplates,
        ], 'layouts.onboarding');
    }

    public function advanceWelcome(Request $request): void
    {
        $this->advanceTo(1);
        $this->redirect('/onboarding');
    }

    public function saveBusiness(Request $request): void
    {
        $tenantId = Tenant::id();
        $name     = trim((string) $request->input('name', ''));
        $industry = trim((string) $request->input('industry', ''));
        $currency = trim((string) $request->input('currency', 'USD'));
        $timezone = trim((string) $request->input('timezone', 'America/Santo_Domingo'));
        $isRestaurant = !empty($request->input('is_restaurant'));

        if ($name === '') {
            Session::flash('error', 'El nombre del negocio es obligatorio.');
            $this->redirect('/onboarding');
            return;
        }

        $patch = [
            'name'     => mb_substr($name, 0, 150),
            'industry' => $industry !== '' ? mb_substr($industry, 0, 80) : null,
            'currency' => mb_substr(strtoupper($currency), 0, 3),
            'timezone' => mb_substr($timezone, 0, 50),
        ];
        // is_restaurant existe si esta el schema multichannel
        try {
            if (Database::fetchColumn(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenants' AND COLUMN_NAME = 'is_restaurant'"
            )) {
                $patch['is_restaurant'] = $isRestaurant ? 1 : 0;
            }
        } catch (\Throwable) {}

        Database::update('tenants', $patch, ['id' => $tenantId]);
        Audit::log('onboarding.business', 'tenant', $tenantId, [], $patch);

        $this->advanceTo(2);
        $this->redirect('/onboarding');
    }

    public function saveChannel(Request $request): void
    {
        $tenantId = Tenant::id();
        $skip = !empty($request->input('skip'));

        if ($skip) {
            $this->advanceTo(3);
            $this->redirect('/onboarding');
            return;
        }

        $phone   = trim((string) $request->input('wasapi_phone', ''));
        $apiKey  = trim((string) $request->input('wasapi_api_key', ''));

        $patch = [];
        if ($phone !== '')  $patch['wasapi_phone']   = mb_substr($phone, 0, 40);
        if ($apiKey !== '') $patch['wasapi_api_key'] = mb_substr($apiKey, 0, 255);

        if ($patch) {
            Database::update('tenants', $patch, ['id' => $tenantId]);
            Audit::log('onboarding.channel', 'tenant', $tenantId, [], ['phone_set' => !empty($patch['wasapi_phone']), 'key_set' => !empty($patch['wasapi_api_key'])]);
        }

        $this->advanceTo(3);
        $this->redirect('/onboarding');
    }

    public function saveAgent(Request $request): void
    {
        $tenantId = Tenant::id();
        $skip = !empty($request->input('skip'));

        if ($skip) {
            $this->advanceTo(4);
            $this->redirect('/onboarding');
            return;
        }

        $name      = trim((string) $request->input('name', ''));
        $preset    = (string) $request->input('preset', '');
        $tone      = trim((string) $request->input('tone', 'profesional, cercano y claro'));
        $role      = '';
        $objective = '';
        $instructions = '';

        // Presets pre-armados
        $presets = [
            'sales' => [
                'name'         => $name !== '' ? $name : 'Asistente de Ventas',
                'role'         => 'Vendedor consultivo',
                'objective'    => 'Calificar el lead, recomendar el producto adecuado y cerrar la venta.',
                'instructions' => 'Saluda calidamente. Haz 1-2 preguntas para entender que necesita el cliente. Recomienda 1-2 productos con precio claro. Cuando confirme, agenda o crea la orden.',
            ],
            'support' => [
                'name'         => $name !== '' ? $name : 'Asistente de Soporte',
                'role'         => 'Especialista en atencion al cliente',
                'objective'    => 'Resolver dudas tecnicas, abrir tickets y escalar a humano cuando sea necesario.',
                'instructions' => 'Empatiza con el cliente. Confirma que entendiste su problema. Da pasos concretos. Si no puedes resolver en 2 turnos, escala con [TRANSFER].',
            ],
            'restaurant' => [
                'name'         => $name !== '' ? $name : 'Asistente del Restaurante',
                'role'         => 'Mesero virtual',
                'objective'    => 'Tomar pedidos, sugerir items del menu y armar la orden.',
                'instructions' => 'Saluda. Pregunta delivery o pickup. Sugiere 2-3 platos del menu segun el antojo. Confirma items, direccion y metodo de pago. Crea la orden con [ORDER:{}].',
            ],
        ];

        if (isset($presets[$preset])) {
            $p = $presets[$preset];
            $name = $p['name'];
            $role = $p['role'];
            $objective = $p['objective'];
            $instructions = $p['instructions'];
        } elseif ($name === '') {
            Session::flash('error', 'Indica un nombre o elige un preset.');
            $this->redirect('/onboarding');
            return;
        }

        AiAgent::create([
            'tenant_id'    => $tenantId,
            'name'         => mb_substr($name, 0, 80),
            'role'         => mb_substr($role, 0, 120),
            'objective'    => $objective !== '' ? $objective : null,
            'tone'         => mb_substr($tone, 0, 80),
            'instructions' => $instructions !== '' ? $instructions : null,
            'status'       => 'active',
            'is_default'   => 1,
            'auto_reply_enabled' => 1,
        ]);

        // Marcar ai_enabled en tenant
        Database::update('tenants', ['ai_enabled' => 1], ['id' => $tenantId]);

        Audit::log('onboarding.agent', 'tenant', $tenantId, [], ['preset' => $preset, 'name' => $name]);

        $this->advanceTo(4);
        $this->redirect('/onboarding');
    }

    public function saveWorkflow(Request $request): void
    {
        $tenantId = Tenant::id();
        $skip = !empty($request->input('skip'));

        if (!$skip) {
            $templateId = (int) $request->input('template_id', 0);
            if ($templateId > 0) {
                $tpl = WorkflowTemplate::findById($tenantId, $templateId);
                if ($tpl) {
                    try {
                        $wfId = WorkflowTemplateService::clone($tenantId, $templateId, Auth::id());
                        // Lo activamos automaticamente para que el usuario vea valor inmediato
                        Database::update('workflows', ['is_active' => 1], ['id' => $wfId, 'tenant_id' => $tenantId]);
                        Audit::log('onboarding.workflow', 'workflow', $wfId, [], ['template_id' => $templateId, 'template_slug' => $tpl['slug']]);
                    } catch (\Throwable $e) {
                        Session::flash('error', 'No se pudo activar el workflow: ' . $e->getMessage());
                        $this->redirect('/onboarding');
                        return;
                    }
                }
            }
        }

        // Marcar onboarding completado
        Database::update('tenants', [
            'onboarding_step'         => 5,
            'onboarding_completed_at' => date('Y-m-d H:i:s'),
        ], ['id' => $tenantId]);
        Audit::log('onboarding.completed', 'tenant', $tenantId);

        Session::flash('success', '¡Setup completado! Bienvenido a Evallish Pulse.');
        $this->redirect('/dashboard');
    }

    /**
     * Skip total del wizard (puede reabrirse desde el banner del dashboard
     * si el usuario cambia de opinion).
     */
    public function skipAll(Request $request): void
    {
        $tenantId = Tenant::id();
        Database::update('tenants', [
            'onboarding_skipped'      => 1,
            'onboarding_completed_at' => date('Y-m-d H:i:s'),
        ], ['id' => $tenantId]);
        Audit::log('onboarding.skipped', 'tenant', $tenantId);
        Session::flash('success', 'Wizard cerrado. Puedes configurar todo manualmente desde Configuracion.');
        $this->redirect('/dashboard');
    }

    /**
     * Volver a abrir el wizard (desde el banner del dashboard).
     */
    public function resume(Request $request): void
    {
        $tenantId = Tenant::id();
        Database::update('tenants', [
            'onboarding_skipped' => 0,
            'onboarding_completed_at' => null,
        ], ['id' => $tenantId]);
        $this->redirect('/onboarding');
    }

    private function advanceTo(int $step): void
    {
        $step = max(0, min(5, $step));
        Database::update('tenants', ['onboarding_step' => $step], ['id' => Tenant::id()]);
    }
}
