<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Session;
use App\Core\Tenant;
use App\Models\Task;
use App\Models\User;

final class TaskController extends Controller
{
    public function index(Request $request): void
    {
        $tenantId = Tenant::id();
        $filters = [
            'status'      => (string) $request->query('status', ''),
            'assigned_to' => $request->query('assigned_to') ?: null,
            'q'           => trim((string) $request->query('q', '')),
            'due_today'   => $request->query('due_today') ? true : false,
            'overdue'     => $request->query('overdue') ? true : false,
        ];
        $page = Paginator::fromRequest($request, 30);
        $page->setTotal(Task::count($tenantId, $filters));

        $this->view('tasks.index', [
            'page'      => 'tareas',
            'tasks'     => Task::listFiltered($tenantId, $filters, $page->limit, $page->offset),
            'filters'   => $filters,
            'paginator' => $page,
            'agents'    => User::listByTenant($tenantId),
        ], 'layouts.app');
    }

    public function store(Request $request): void
    {
        $tenantId = Tenant::id();
        $data = $this->validate($request, ['title' => 'required|min:2|max:200']);

        $id = Task::create([
            'tenant_id'   => $tenantId,
            'title'       => $data['title'],
            'description' => $data['description'] ?? null,
            'contact_id'  => !empty($data['contact_id']) ? (int) $data['contact_id'] : null,
            'lead_id'     => !empty($data['lead_id']) ? (int) $data['lead_id'] : null,
            'assigned_to' => !empty($data['assigned_to']) ? (int) $data['assigned_to'] : Auth::id(),
            'created_by'  => Auth::id(),
            'due_at'      => !empty($data['due_at']) ? $data['due_at'] : null,
            'priority'    => (string) ($data['priority'] ?? 'medium'),
            'status'      => 'pending',
            'remind_email'    => !empty($data['remind_email']) ? 1 : 0,
            'remind_whatsapp' => !empty($data['remind_whatsapp']) ? 1 : 0,
        ]);

        Audit::log('task.created', 'task', $id);
        Session::flash('success', 'Tarea creada.');
        $this->redirect('/tasks');
    }

    public function complete(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        Task::complete($tenantId, $id);
        Audit::log('task.completed', 'task', $id);
        Session::flash('success', 'Tarea completada.');
        $this->redirect('/tasks');
    }

    public function destroy(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        Task::delete($tenantId, $id);
        Audit::log('task.deleted', 'task', $id);
        Session::flash('success', 'Tarea eliminada.');
        $this->redirect('/tasks');
    }
}
