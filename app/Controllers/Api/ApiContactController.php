<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Database;
use App\Core\Request;
use App\Models\Contact;

/**
 * Endpoints de contactos.
 *
 *   GET    /api/v1/contacts             listar (?q=, ?status=, ?page=, ?per_page=)
 *   GET    /api/v1/contacts/{id}        detalle
 *   POST   /api/v1/contacts             crear
 *   PATCH  /api/v1/contacts/{id}        actualizar
 *   DELETE /api/v1/contacts/{id}        soft delete
 */
final class ApiContactController extends ApiController
{
    public function index(Request $request): void
    {
        $tenantId = $this->tenantId();
        [$page, $perPage, $offset] = $this->pagination($request, 25, 100);

        $filters = [
            'search' => (string) $request->query('q', ''),
            'status' => (string) $request->query('status', ''),
        ];

        $rows  = Contact::listFiltered($tenantId, $filters, $perPage, $offset);
        $total = Contact::countFiltered($tenantId, $filters);

        $items = array_map(fn($r) => $this->transform($r), $rows);
        $this->paginated($items, $page, $perPage, $total);
    }

    public function show(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $row = Contact::findById($id);
        if (!$row) {
            $this->error('not_found', 'Contact not found.', 404);
        }
        $this->ok($this->transform($row));
    }

    public function store(Request $request): void
    {
        $tenantId = $this->tenantId();
        $clean = $this->validateApi($request, [
            'first_name' => 'required|string|min:1|max:120',
            'last_name'  => 'string|max:120',
            'phone'      => 'string|max:32',
            'whatsapp'   => 'string|max:32',
            'email'      => 'email|max:160',
        ]);

        // Necesitamos al menos uno de phone/whatsapp/email
        if (empty($clean['phone']) && empty($clean['whatsapp']) && empty($clean['email'])) {
            $this->error('validation_failed', 'At least one of phone, whatsapp or email is required.', 422);
        }

        $existing = null;
        if (!empty($clean['whatsapp'])) {
            $existing = Contact::findByPhone($tenantId, (string) $clean['whatsapp']);
        }
        if (!$existing && !empty($clean['phone'])) {
            $existing = Contact::findByPhone($tenantId, (string) $clean['phone']);
        }
        if ($existing) {
            $this->error('contact_exists', 'A contact with that phone/whatsapp already exists.', 409, [
                'contact_id' => (int) $existing['id'],
            ]);
        }

        $payload = array_filter([
            'first_name' => $clean['first_name'] ?? null,
            'last_name'  => $clean['last_name']  ?? null,
            'phone'      => $clean['phone']      ?? null,
            'whatsapp'   => $clean['whatsapp']   ?? $clean['phone'] ?? null,
            'email'      => $clean['email']      ?? null,
            'status'     => 'lead',
        ], fn($v) => $v !== null && $v !== '');

        $id = Contact::createMinimal($tenantId, $payload);
        $row = Contact::findById($id);
        $this->created($this->transform($row ?? []));
    }

    public function update(Request $request, array $params): void
    {
        $tenantId = $this->tenantId();
        $id = (int) ($params['id'] ?? 0);
        $existing = Contact::findById($id);
        if (!$existing) {
            $this->error('not_found', 'Contact not found.', 404);
        }

        $allowed = ['first_name','last_name','phone','whatsapp','email','company','position','status','notes'];
        $patch = [];
        foreach ($allowed as $f) {
            $v = $request->input($f, null);
            if ($v !== null) $patch[$f] = $v;
        }
        if (!$patch) {
            $this->error('validation_failed', 'No editable fields provided.', 422);
        }
        Contact::update($tenantId, $id, $patch);
        $row = Contact::findById($id);
        $this->ok($this->transform($row ?? []));
    }

    public function destroy(Request $request, array $params): void
    {
        $tenantId = $this->tenantId();
        $id = (int) ($params['id'] ?? 0);
        $existing = Contact::findById($id);
        if (!$existing) {
            $this->error('not_found', 'Contact not found.', 404);
        }
        Contact::softDelete($tenantId, $id);
        $this->noContent();
    }

    private function transform(array $c): array
    {
        return [
            'id'            => (int) ($c['id'] ?? 0),
            'uuid'          => (string) ($c['uuid'] ?? ''),
            'first_name'    => (string) ($c['first_name'] ?? ''),
            'last_name'     => (string) ($c['last_name'] ?? ''),
            'full_name'     => trim(((string) ($c['first_name'] ?? '')) . ' ' . ((string) ($c['last_name'] ?? ''))),
            'phone'         => $c['phone']    ?? null,
            'whatsapp'      => $c['whatsapp'] ?? null,
            'email'         => $c['email']    ?? null,
            'company'       => $c['company']  ?? null,
            'position'      => $c['position'] ?? null,
            'status'        => (string) ($c['status'] ?? 'lead'),
            'tags'          => $c['tags'] ?? null,
            'notes'         => $c['notes'] ?? null,
            'last_interaction' => $c['last_interaction'] ?? null,
            'created_at'    => $c['created_at'] ?? null,
            'updated_at'    => $c['updated_at'] ?? null,
        ];
    }
}
