<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Core\Tenant;
use App\Models\Campaign;
use App\Models\Tag;
use App\Services\WasapiService;

final class CampaignController extends Controller
{
    public function index(Request $request): void
    {
        $tenantId = Tenant::id();
        $this->view('campaigns.index', [
            'page'      => 'campanas',
            'campaigns' => Campaign::listForTenant($tenantId, 100),
        ], 'layouts.app');
    }

    public function create(Request $request): void
    {
        $tenantId = Tenant::id();
        $this->view('campaigns.create', [
            'page'   => 'campanas',
            'errors' => errors(),
            'tags'   => Tag::listForTenant($tenantId),
            'templates' => Database::fetchAll(
                "SELECT id, name, language, body, external_id FROM templates
                 WHERE tenant_id = :t AND status = 'approved'
                 ORDER BY name ASC",
                ['t' => $tenantId]
            ),
        ], 'layouts.app');
    }

    public function store(Request $request): void
    {
        $tenantId = Tenant::id();
        $data = $this->validate($request, [
            'name'    => 'required|min:3|max:150',
            'channel' => 'in:whatsapp,email,sms',
        ]);

        $templateId = $request->input('template_id') ? (int) $request->input('template_id') : null;
        $message = trim((string) $request->input('message', ''));
        $template = null;
        if ($templateId) {
            $template = Database::fetch(
                "SELECT * FROM templates WHERE id = :id AND tenant_id = :t AND status = 'approved'",
                ['id' => $templateId, 't' => $tenantId]
            );
        }
        if (!$template && mb_strlen($message) < 5) {
            Session::flash('error', 'Escribe un mensaje o selecciona una plantilla aprobada.');
            $this->redirect('/campaigns/create');
            return;
        }

        $filters = [
            'status'       => $request->input('audience_status'),
            'source'       => $request->input('audience_source'),
            'country'      => $request->input('audience_country'),
            'tag'          => $request->input('audience_tag'),
            'has_whatsapp' => !empty($request->input('audience_only_whatsapp')),
        ];

        $audience = Campaign::buildAudience($tenantId, array_filter($filters));

        $id = Campaign::create([
            'tenant_id'        => $tenantId,
            'name'             => $data['name'],
            'description'      => $data['description'] ?? null,
            'channel'          => $data['channel'] ?? 'whatsapp',
            'template_id'      => $template ? (int) $template['id'] : null,
            'message'          => $template ? (string) $template['body'] : $message,
            'audience_filters' => json_encode($filters, JSON_UNESCAPED_UNICODE),
            'scheduled_at'     => !empty($data['scheduled_at']) ? $data['scheduled_at'] : null,
            'status'           => !empty($data['scheduled_at']) ? 'scheduled' : 'draft',
            'created_by'       => Auth::id(),
        ]);

        Campaign::addRecipients($tenantId, $id, $audience);

        Audit::log('campaign.created', 'campaign', $id, [], $data);
        Session::flash('success', 'Campana creada con ' . count($audience) . ' destinatarios.');
        $this->redirect("/campaigns/$id");
    }

    public function show(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        $campaign = Campaign::findById($tenantId, $id);
        if (!$campaign) $this->abort(404);

        Campaign::refreshMetrics($id);
        $campaign = Campaign::findById($tenantId, $id);

        $recipients = Database::fetchAll(
            "SELECT cr.*, c.first_name, c.last_name FROM campaign_recipients cr
             LEFT JOIN contacts c ON c.id = cr.contact_id
             WHERE cr.campaign_id = :c
             ORDER BY cr.id DESC LIMIT 100",
            ['c' => $id]
        );

        $this->view('campaigns.show', [
            'page'       => 'campanas',
            'campaign'   => $campaign,
            'recipients' => $recipients,
        ], 'layouts.app');
    }

    public function send(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        $campaign = Campaign::findById($tenantId, $id);
        if (!$campaign) $this->abort(404);

        Campaign::update($tenantId, $id, ['status' => 'sending', 'started_at' => date('Y-m-d H:i:s')]);
        $sent = $this->dispatchPending($tenantId, $campaign);

        Campaign::update($tenantId, $id, [
            'status'      => 'completed',
            'finished_at' => date('Y-m-d H:i:s'),
        ]);
        Campaign::refreshMetrics($id);

        Session::flash('success', "Campana enviada. $sent mensajes despachados.");
        $this->redirect("/campaigns/$id");
    }

    private function dispatchPending(int $tenantId, array $campaign): int
    {
        $svc = new WasapiService($tenantId);
        $campaignId = (int) $campaign['id'];
        $message = (string) ($campaign['message'] ?? '');
        $template = null;
        if (!empty($campaign['template_id'])) {
            $template = Database::fetch(
                "SELECT * FROM templates WHERE id = :id AND tenant_id = :t",
                ['id' => (int) $campaign['template_id'], 't' => $tenantId]
            );
        }
        $sent = 0;
        while (true) {
            $batch = Campaign::pendingRecipients($campaignId, 50);
            if (empty($batch)) break;
            foreach ($batch as $r) {
                $phone = (string) ($r['phone'] ?? '');
                if ($phone === '') {
                    Campaign::recordSend((int) $r['id'], false, null, 'Sin telefono');
                    continue;
                }
                $resp = $template
                    ? $svc->sendTemplate($phone, (string) $template['external_id'])
                    : $svc->sendTextMessage($phone, $this->interpolateCampaignMessage($message, $r));
                Campaign::recordSend(
                    (int) $r['id'],
                    !empty($resp['success']),
                    $resp['body']['id'] ?? null,
                    !empty($resp['success']) ? null : ($resp['error'] ?? 'Error envio')
                );
                $sent++;
                usleep(80000); // throttle 80ms
            }
        }
        return $sent;
    }

    private function interpolateCampaignMessage(string $message, array $recipient): string
    {
        $values = [
            'first_name' => (string) ($recipient['first_name'] ?? ''),
            'last_name'  => (string) ($recipient['last_name'] ?? ''),
            'company'    => (string) ($recipient['company'] ?? ''),
            'phone'      => (string) ($recipient['phone'] ?? ''),
        ];
        return preg_replace_callback('/\{\{([a-z0-9_]+)\}\}/i', static function ($m) use ($values) {
            return $values[$m[1]] ?? $m[0];
        }, $message) ?? $message;
    }

    public function destroy(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        Campaign::delete($tenantId, $id);
        Session::flash('success', 'Campana eliminada.');
        $this->redirect('/campaigns');
    }
}
