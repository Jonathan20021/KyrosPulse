<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Events;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Session;
use App\Core\Tenant;
use App\Models\Contact;
use App\Models\Tag;
use App\Models\User;

final class ContactController extends Controller
{
    public function index(Request $request): void
    {
        $tenantId = Tenant::id();
        $filters = [
            'q'      => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', ''),
            'source' => (string) $request->query('source', ''),
            'agent'  => $request->query('agent') ?: null,
            'tag'    => (string) $request->query('tag', ''),
        ];

        $page = Paginator::fromRequest($request, 25);
        $total = Contact::countFiltered($tenantId, $filters);
        $page->setTotal($total);

        $contacts = Contact::listFiltered($tenantId, $filters, $page->limit, $page->offset);

        // Cargar tags por contacto en bulk
        if ($contacts) {
            $ids = array_column($contacts, 'id');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $rows = Database::fetchAll(
                "SELECT ct.contact_id, t.id, t.name, t.color FROM contact_tags ct
                 INNER JOIN tags t ON t.id = ct.tag_id
                 WHERE ct.contact_id IN ($placeholders)",
                $ids
            );
            $byContact = [];
            foreach ($rows as $r) { $byContact[(int) $r['contact_id']][] = $r; }
            foreach ($contacts as &$c) { $c['tags'] = $byContact[(int) $c['id']] ?? []; }
            unset($c);
        }

        $this->view('contacts.index', [
            'contacts' => $contacts,
            'filters'  => $filters,
            'page'     => 'contactos',
            'paginator'=> $page,
            'agents'   => User::listByTenant($tenantId),
            'allTags'  => Tag::listForTenant($tenantId),
            'total'    => $total,
        ], 'layouts.app');
    }

    public function create(Request $request): void
    {
        $tenantId = Tenant::id();
        $this->view('contacts.create', [
            'page'    => 'contactos',
            'errors'  => errors(),
            'agents'  => User::listByTenant($tenantId),
            'allTags' => Tag::listForTenant($tenantId),
        ], 'layouts.app');
    }

    public function store(Request $request): void
    {
        $tenantId = Tenant::id();
        $data = $this->validate($request, [
            'first_name' => 'required|min:2|max:120',
            'last_name'  => 'max:120',
            'email'      => 'email|max:180',
            'phone'      => 'phone',
            'whatsapp'   => 'phone',
            'status'     => 'in:lead,active,inactive,vip,blocked',
        ]);

        $contactData = [
            'tenant_id'        => $tenantId,
            'uuid'             => uuid4(),
            'first_name'       => $data['first_name'],
            'last_name'        => $data['last_name']  ?? null,
            'company'          => $data['company']    ?? null,
            'position'         => $data['position']   ?? null,
            'email'            => $data['email']      ?? null,
            'phone'            => $data['phone']      ?? null,
            'whatsapp'         => $data['whatsapp']   ?? ($data['phone'] ?? null),
            'document_type'    => $data['document_type']   ?? null,
            'document_number'  => $data['document_number'] ?? null,
            'address'          => $data['address']    ?? null,
            'city'             => $data['city']       ?? null,
            'country'          => $data['country']    ?? null,
            'source'           => $data['source']     ?? 'manual',
            'status'           => $data['status']     ?? 'lead',
            'estimated_value'  => !empty($data['estimated_value']) ? (float) $data['estimated_value'] : null,
            'notes'            => $data['notes']      ?? null,
            'assigned_to'      => !empty($data['assigned_to']) ? (int) $data['assigned_to'] : null,
            'consent_marketing'=> !empty($data['consent_marketing']) ? 1 : 0,
            'consent_at'       => !empty($data['consent_marketing']) ? date('Y-m-d H:i:s') : null,
        ];

        $id = Database::insert('contacts', $contactData);

        // Tags
        if (!empty($data['tags'])) {
            $tags = is_array($data['tags']) ? $data['tags'] : array_map('trim', explode(',', (string) $data['tags']));
            Tag::syncContactTags($id, $tags, $tenantId);
        }

        Audit::log('contact.created', 'contact', $id, [], $contactData);
        Events::dispatch('contact.created', [
            'tenant_id'  => $tenantId,
            'contact_id' => $id,
            'entity_type'=> 'contact',
            'entity_id'  => $id,
            'contact_phone' => $contactData['phone'] ?? '',
            'contact_email' => $contactData['email'] ?? '',
        ]);

        Session::flash('success', 'Contacto creado correctamente.');
        $this->redirect("/contacts/$id");
    }

    public function show(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        $contact = Contact::findById($id);
        if (!$contact) $this->abort(404, 'Contacto no encontrado.');

        $this->view('contacts.show', [
            'page'      => 'contactos',
            'contact'   => $contact,
            'tags'      => Tag::tagsOfContact($id),
            'timeline'  => Contact::timeline($tenantId, $id),
            'agents'    => User::listByTenant($tenantId),
            'allTags'   => Tag::listForTenant($tenantId),
        ], 'layouts.app');
    }

    public function edit(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        $contact = Contact::findById($id);
        if (!$contact) $this->abort(404);

        $this->view('contacts.edit', [
            'page'    => 'contactos',
            'contact' => $contact,
            'tags'    => Tag::tagsOfContact($id),
            'errors'  => errors(),
            'agents'  => User::listByTenant($tenantId),
            'allTags' => Tag::listForTenant($tenantId),
        ], 'layouts.app');
    }

    public function update(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        $contact = Contact::findById($id);
        if (!$contact) $this->abort(404);

        $data = $this->validate($request, [
            'first_name' => 'required|min:2|max:120',
            'email'      => 'email|max:180',
            'phone'      => 'phone',
            'status'     => 'in:lead,active,inactive,vip,blocked',
        ]);

        $update = array_intersect_key($data, array_flip([
            'first_name','last_name','company','position','email','phone','whatsapp',
            'document_type','document_number','address','city','country','source','status','notes'
        ]));
        if (!empty($data['estimated_value'])) $update['estimated_value'] = (float) $data['estimated_value'];
        $update['assigned_to'] = !empty($data['assigned_to']) ? (int) $data['assigned_to'] : null;
        $update['consent_marketing'] = !empty($data['consent_marketing']) ? 1 : 0;

        Contact::update($tenantId, $id, $update);

        if (isset($data['tags'])) {
            $tags = is_array($data['tags']) ? $data['tags'] : array_map('trim', explode(',', (string) $data['tags']));
            Tag::syncContactTags($id, $tags, $tenantId);
        }

        Audit::log('contact.updated', 'contact', $id, $contact, $update);
        Session::flash('success', 'Contacto actualizado.');
        $this->redirect("/contacts/$id");
    }

    public function destroy(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        $contact = Contact::findById($id);
        if (!$contact) $this->abort(404);

        Contact::softDelete($tenantId, $id);
        Audit::log('contact.deleted', 'contact', $id, $contact);

        Session::flash('success', 'Contacto eliminado.');
        $this->redirect('/contacts');
    }

    public function exportCsv(Request $request): void
    {
        $tenantId = Tenant::id();
        $rows = Contact::listFiltered($tenantId, [], 5000, 0);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="contactos-' . date('Ymd-His') . '.csv"');
        $out = fopen('php://output', 'w');
        // BOM para Excel UTF-8
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Nombre','Apellido','Empresa','Email','Telefono','WhatsApp','Pais','Estado','Fuente','Valor estimado','Creado']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['first_name'], $r['last_name'], $r['company'],
                $r['email'], $r['phone'], $r['whatsapp'],
                $r['country'], $r['status'], $r['source'],
                $r['estimated_value'], $r['created_at'],
            ]);
        }
        fclose($out);
        exit;
    }

    public function importForm(Request $request): void
    {
        $this->view('contacts.import', ['page' => 'contactos'], 'layouts.app');
    }

    public function importCsv(Request $request): void
    {
        $tenantId = Tenant::id();
        $file = $request->file('csv');
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Session::flash('error', 'Sube un archivo CSV valido.');
            $this->redirect('/contacts/import');
            return;
        }

        $fh = fopen($file['tmp_name'], 'r');
        if (!$fh) {
            Session::flash('error', 'No se pudo leer el archivo.');
            $this->redirect('/contacts/import');
            return;
        }

        $header = fgetcsv($fh);
        if (!$header) {
            fclose($fh);
            Session::flash('error', 'CSV vacio o invalido.');
            $this->redirect('/contacts/import');
            return;
        }
        $header = array_map(fn($h) => strtolower(trim((string) $h)), $header);

        $created = 0;
        $skipped = 0;
        while (($row = fgetcsv($fh)) !== false) {
            $assoc = @array_combine($header, array_pad($row, count($header), null)) ?: [];
            $first = trim((string) ($assoc['first_name'] ?? $assoc['nombre'] ?? ''));
            if ($first === '') { $skipped++; continue; }

            try {
                Database::insert('contacts', [
                    'tenant_id'  => $tenantId,
                    'uuid'       => uuid4(),
                    'first_name' => $first,
                    'last_name'  => $assoc['last_name']  ?? $assoc['apellido']  ?? null,
                    'company'    => $assoc['company']    ?? $assoc['empresa']   ?? null,
                    'email'      => $assoc['email']      ?? $assoc['correo']    ?? null,
                    'phone'      => $assoc['phone']      ?? $assoc['telefono']  ?? null,
                    'whatsapp'   => $assoc['whatsapp']   ?? $assoc['phone']     ?? $assoc['telefono'] ?? null,
                    'country'    => $assoc['country']    ?? $assoc['pais']      ?? null,
                    'source'     => $assoc['source']     ?? 'csv_import',
                    'status'     => $assoc['status']     ?? 'lead',
                    'notes'      => $assoc['notes']      ?? $assoc['notas']     ?? null,
                ]);
                $created++;
            } catch (\Throwable) {
                $skipped++;
            }
        }
        fclose($fh);

        Audit::log('contacts.imported', 'contact', null, [], ['created' => $created, 'skipped' => $skipped]);
        Session::flash('success', "Importacion completada: $created contactos agregados, $skipped omitidos.");
        $this->redirect('/contacts');
    }

    public function assign(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        $userId = (int) ($request->input('assigned_to') ?? 0);
        Contact::update($tenantId, $id, ['assigned_to' => $userId ?: null]);
        Session::flash('success', 'Asignacion actualizada.');
        $this->redirect("/contacts/$id");
    }
}
