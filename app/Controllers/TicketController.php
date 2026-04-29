<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Events;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Session;
use App\Core\Tenant;
use App\Models\Contact;
use App\Models\Ticket;
use App\Models\User;

final class TicketController extends Controller
{
    public function index(Request $request): void
    {
        $tenantId = Tenant::id();
        $filters = [
            'status'   => (string) $request->query('status', ''),
            'priority' => (string) $request->query('priority', ''),
            'agent'    => $request->query('agent') ?: null,
            'q'        => trim((string) $request->query('q', '')),
        ];
        $page = Paginator::fromRequest($request, 25);
        $page->setTotal(Ticket::count($tenantId, $filters));

        $this->view('tickets.index', [
            'page'      => 'tickets',
            'tickets'   => Ticket::listFiltered($tenantId, $filters, $page->limit, $page->offset),
            'filters'   => $filters,
            'paginator' => $page,
            'agents'    => User::listByTenant($tenantId),
        ], 'layouts.app');
    }

    public function create(Request $request): void
    {
        $tenantId = Tenant::id();
        $this->view('tickets.create', [
            'page'   => 'tickets',
            'agents' => User::listByTenant($tenantId),
            'errors' => errors(),
        ], 'layouts.app');
    }

    public function store(Request $request): void
    {
        $tenantId = Tenant::id();
        $data = $this->validate($request, [
            'subject'  => 'required|min:3|max:255',
            'priority' => 'in:low,medium,high,critical',
        ]);

        $priority = (string) ($data['priority'] ?? 'medium');
        $sla      = Ticket::slaHoursFor($priority);

        $id = Ticket::create([
            'tenant_id'   => $tenantId,
            'code'        => Ticket::generateCode(),
            'contact_id'  => !empty($data['contact_id']) ? (int) $data['contact_id'] : null,
            'subject'     => $data['subject'],
            'description' => $data['description'] ?? null,
            'priority'    => $priority,
            'category'    => $data['category'] ?? 'general',
            'status'      => 'open',
            'channel'     => $data['channel'] ?? 'panel',
            'assigned_to' => !empty($data['assigned_to']) ? (int) $data['assigned_to'] : null,
            'created_by'  => Auth::id(),
            'due_at'      => date('Y-m-d H:i:s', time() + $sla * 3600),
        ]);

        Audit::log('ticket.created', 'ticket', $id);
        Events::dispatch('ticket.created', [
            'tenant_id'  => $tenantId,
            'ticket_id'  => $id,
            'entity_type'=> 'ticket',
            'entity_id'  => $id,
        ]);

        Session::flash('success', 'Ticket creado.');
        $this->redirect("/tickets/$id");
    }

    public function show(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        $ticket = Ticket::findById($tenantId, $id);
        if (!$ticket) $this->abort(404);

        $this->view('tickets.show', [
            'page'     => 'tickets',
            'ticket'   => $ticket,
            'comments' => Ticket::comments($tenantId, $id),
            'agents'   => User::listByTenant($tenantId),
        ], 'layouts.app');
    }

    public function update(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        $ticket = Ticket::findById($tenantId, $id);
        if (!$ticket) $this->abort(404);

        $data = $request->only(['subject','description','status','priority','category','assigned_to','due_at']);
        $data['assigned_to'] = !empty($data['assigned_to']) ? (int) $data['assigned_to'] : null;

        Ticket::update($tenantId, $id, $data);
        if (!empty($data['status']) && $data['status'] !== $ticket['status']) {
            Ticket::setStatus($tenantId, $id, (string) $data['status']);
        }

        Audit::log('ticket.updated', 'ticket', $id, $ticket, $data);
        Session::flash('success', 'Ticket actualizado.');
        $this->redirect("/tickets/$id");
    }

    public function comment(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        $body = trim((string) $request->input('body', ''));
        if ($body === '') {
            Session::flash('error', 'El comentario no puede estar vacio.');
            $this->redirect("/tickets/$id");
            return;
        }
        Ticket::addComment($tenantId, $id, (int) Auth::id(), $body, !empty($request->input('is_internal')));
        Session::flash('success', 'Comentario agregado.');
        $this->redirect("/tickets/$id");
    }

    public function destroy(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        Ticket::softDelete($tenantId, $id);
        Audit::log('ticket.deleted', 'ticket', $id);
        Session::flash('success', 'Ticket eliminado.');
        $this->redirect('/tickets');
    }
}
