-- ============================================================================
-- Kyros Pulse - Seeders basicos
-- Ejecutar despues de la migracion 001_initial_schema.sql
-- ============================================================================

-- ----------------------------------------------------------------------------
-- Planes
-- ----------------------------------------------------------------------------
INSERT INTO `plans` (`slug`, `name`, `description`, `price_monthly`, `price_yearly`, `currency`,
                     `max_users`, `max_contacts`, `max_messages`, `max_campaigns`, `max_automations`,
                     `ai_enabled`, `api_access`, `advanced_reports`, `support_level`, `sort_order`)
VALUES
('starter',     'Starter',     'Perfecto para emprendedores y negocios que empiezan.',     0,    0,    'USD', 1,  500,    500,    1,  3,  0, 0, 0, 'email',     1),
('professional','Professional','La opcion ideal para pequenas empresas y equipos.',         49,   470,  'USD', 5,  5000,   5000,   10, 15, 1, 0, 0, 'chat',      2),
('business',    'Business',    'Para empresas en crecimiento que necesitan automatizar.',   129,  1240, 'USD', 20, 50000,  50000,  50, 50, 1, 1, 1, 'priority',  3),
('enterprise',  'Enterprise',  'Sin limites, soporte dedicado y personalizacion total.',    349,  3350, 'USD', 999,999999, 999999, 999,999,1, 1, 1, 'dedicated', 4);

-- ----------------------------------------------------------------------------
-- Roles
-- ----------------------------------------------------------------------------
INSERT INTO `roles` (`slug`, `name`, `description`, `is_system`) VALUES
('super_admin', 'Super Admin',   'Administrador global de la plataforma Kyros.', 1),
('owner',       'Owner',         'Propietario de la empresa. Control total.',     1),
('admin',       'Administrador', 'Administrador con permisos amplios.',           1),
('supervisor',  'Supervisor',    'Supervisa agentes y conversaciones.',           1),
('agent',       'Agente',        'Agente que atiende conversaciones y leads.',    1),
('readonly',    'Solo lectura',  'Acceso de solo lectura para consultas.',        1);

-- ----------------------------------------------------------------------------
-- Permisos
-- ----------------------------------------------------------------------------
INSERT INTO `permissions` (`slug`, `name`, `module`, `description`) VALUES
-- Contactos
('contacts.view',   'Ver contactos',     'contacts', 'Ver lista y detalle de contactos.'),
('contacts.create', 'Crear contactos',   'contacts', 'Crear nuevos contactos.'),
('contacts.edit',   'Editar contactos',  'contacts', 'Modificar contactos existentes.'),
('contacts.delete', 'Eliminar contactos','contacts', 'Eliminar contactos.'),
('contacts.import', 'Importar contactos','contacts', 'Importar desde CSV.'),
('contacts.export', 'Exportar contactos','contacts', 'Exportar a Excel/CSV.'),

-- Conversaciones
('conversations.view',     'Ver conversaciones',      'inbox', 'Acceso a la bandeja.'),
('conversations.reply',    'Responder mensajes',      'inbox', 'Enviar mensajes a contactos.'),
('conversations.assign',   'Asignar conversaciones',  'inbox', 'Asignar a otros agentes.'),
('conversations.close',    'Cerrar conversaciones',   'inbox', 'Marcar como cerradas.'),

-- Leads
('leads.view',   'Ver leads',     'leads', 'Ver pipeline.'),
('leads.create', 'Crear leads',   'leads', 'Crear leads.'),
('leads.edit',   'Editar leads',  'leads', 'Mover entre etapas.'),

-- Tickets
('tickets.view',   'Ver tickets',     'tickets', 'Ver tickets de soporte.'),
('tickets.create', 'Crear tickets',   'tickets', 'Crear tickets.'),
('tickets.assign', 'Asignar tickets', 'tickets', 'Asignar tickets.'),

-- Campanas
('campaigns.view',   'Ver campanas',    'campaigns', 'Ver campanas y resultados.'),
('campaigns.create', 'Crear campanas',  'campaigns', 'Crear campanas.'),
('campaigns.send',   'Enviar campanas', 'campaigns', 'Disparar envios.'),

-- Automatizaciones
('automations.manage', 'Gestionar automatizaciones', 'automations', 'Crear y editar automatizaciones.'),

-- Reportes
('reports.view', 'Ver reportes', 'reports', 'Acceso a reportes.'),

-- Settings
('settings.manage',     'Gestionar configuracion', 'settings', 'Configurar parametros generales.'),
('settings.integrations','Gestionar integraciones','settings', 'Wasapi, Resend, Claude.'),
('settings.users',      'Gestionar usuarios',     'settings', 'Crear/editar usuarios y roles.'),

-- IA
('ai.use',     'Usar IA',     'ai', 'Disparar acciones de IA.'),
('ai.train',   'Entrenar IA', 'ai', 'Editar base de conocimiento.');

-- ----------------------------------------------------------------------------
-- Asignacion de permisos a roles
-- ----------------------------------------------------------------------------
-- Owner tiene todos los permisos
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.slug = 'owner';

-- Admin tiene todo excepto gestion de usuarios criticos (en este nivel los igualamos a owner)
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.slug = 'admin';

-- Supervisor: ver/editar todo CRM, leads, conversaciones, tickets, reportes
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM roles r INNER JOIN permissions p ON p.slug IN (
    'contacts.view','contacts.create','contacts.edit','contacts.export',
    'conversations.view','conversations.reply','conversations.assign','conversations.close',
    'leads.view','leads.create','leads.edit',
    'tickets.view','tickets.create','tickets.assign',
    'campaigns.view',
    'reports.view',
    'ai.use'
) WHERE r.slug = 'supervisor';

-- Agente: lo necesario para atender
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM roles r INNER JOIN permissions p ON p.slug IN (
    'contacts.view','contacts.create','contacts.edit',
    'conversations.view','conversations.reply','conversations.close',
    'leads.view','leads.create','leads.edit',
    'tickets.view','tickets.create',
    'ai.use'
) WHERE r.slug = 'agent';

-- Solo lectura: solo views
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM roles r INNER JOIN permissions p ON p.slug LIKE '%.view'
WHERE r.slug = 'readonly';

-- ----------------------------------------------------------------------------
-- Super Admin de la plataforma (ajustar email/password)
-- Password = 'admin12345'
-- ----------------------------------------------------------------------------
INSERT INTO `users` (`uuid`, `tenant_id`, `first_name`, `last_name`, `email`, `password`,
                     `is_super_admin`, `is_active`, `email_verified_at`, `language`)
VALUES (
    UUID(), NULL, 'Super', 'Admin', 'admin@kyrosrd.com',
    '$2y$10$lWQbVkjsuQDPyVldsfVLz.k0tVrSw6iKPlKVDp6XfXQkDx2tSo3o.', -- admin12345
    1, 1, NOW(), 'es'
);

-- Asignar rol super_admin
INSERT INTO `user_roles` (`user_id`, `role_id`, `tenant_id`)
SELECT u.id, r.id, NULL FROM users u, roles r
WHERE u.email = 'admin@kyrosrd.com' AND r.slug = 'super_admin';

-- ----------------------------------------------------------------------------
-- Tenant demo + usuario demo (opcional, util para pruebas)
-- Password del usuario demo = 'demo12345'
-- ----------------------------------------------------------------------------
INSERT INTO `tenants` (`uuid`, `slug`, `name`, `email`, `country`, `currency`, `timezone`, `language`,
                       `plan_id`, `status`, `trial_ends_at`)
VALUES (
    UUID(), 'kyros-demo', 'Kyros Demo', 'demo@kyrosrd.com', 'DO', 'USD', 'America/Santo_Domingo', 'es',
    (SELECT id FROM plans WHERE slug = 'professional'),
    'trial', DATE_ADD(NOW(), INTERVAL 14 DAY)
);

SET @demo_tenant_id = LAST_INSERT_ID();

INSERT INTO `users` (`uuid`, `tenant_id`, `first_name`, `last_name`, `email`, `password`,
                     `is_super_admin`, `is_active`, `email_verified_at`, `language`)
VALUES (
    UUID(), @demo_tenant_id, 'Demo', 'Owner', 'owner@kyrosrd.com',
    '$2y$10$fTMvUF4jTkLIJuzP5flgPOM99ndvIXbuCC46UiRjymi1faEL.0RLO', -- demo12345
    0, 1, NOW(), 'es'
);

INSERT INTO `user_roles` (`user_id`, `role_id`, `tenant_id`)
SELECT u.id, r.id, @demo_tenant_id FROM users u, roles r
WHERE u.email = 'owner@kyrosrd.com' AND r.slug = 'owner';

-- Etapas del pipeline para el tenant demo
INSERT INTO `pipeline_stages` (`tenant_id`, `name`, `slug`, `color`, `probability`, `is_won`, `is_lost`, `sort_order`) VALUES
(@demo_tenant_id, 'Nuevo lead',         'nuevo',       '#06B6D4', 10,  0, 0, 1),
(@demo_tenant_id, 'Contactado',         'contactado',  '#3B82F6', 25,  0, 0, 2),
(@demo_tenant_id, 'Interesado',         'interesado',  '#7C3AED', 50,  0, 0, 3),
(@demo_tenant_id, 'Cotizacion enviada', 'cotizacion',  '#A855F7', 70,  0, 0, 4),
(@demo_tenant_id, 'Negociacion',        'negociacion', '#F59E0B', 85,  0, 0, 5),
(@demo_tenant_id, 'Ganado',             'ganado',      '#22C55E', 100, 1, 0, 6),
(@demo_tenant_id, 'Perdido',            'perdido',     '#EF4444', 0,   0, 1, 7);

-- Etiquetas de muestra
INSERT INTO `tags` (`tenant_id`, `name`, `color`) VALUES
(@demo_tenant_id, 'VIP',         '#F59E0B'),
(@demo_tenant_id, 'Nuevo',       '#06B6D4'),
(@demo_tenant_id, 'Recurrente',  '#22C55E'),
(@demo_tenant_id, 'Riesgo',      '#EF4444');

-- Respuesta rapida de muestra
INSERT INTO `quick_replies` (`tenant_id`, `shortcut`, `title`, `body`) VALUES
(@demo_tenant_id, '/saludo', 'Saludo inicial', 'Hola! Gracias por contactarnos. En que podemos ayudarte hoy?'),
(@demo_tenant_id, '/horario','Horario',        'Nuestro horario es de lunes a viernes de 9am a 6pm.'),
(@demo_tenant_id, '/gracias','Despedida',      'Gracias por tu mensaje. Que tengas un excelente dia!');

-- Base de conocimiento
INSERT INTO `knowledge_base` (`tenant_id`, `category`, `title`, `content`, `is_active`, `sort_order`) VALUES
(@demo_tenant_id, 'empresa',   'Sobre Kyros Demo',     'Kyros Demo es una empresa ficticia usada para mostrar las capacidades de Kyros Pulse.', 1, 1),
(@demo_tenant_id, 'horario',   'Horario de atencion',  'Atendemos de lunes a viernes 9am-6pm hora local.', 1, 2),
(@demo_tenant_id, 'productos', 'Servicios principales','Ofrecemos consultoria, soporte y desarrollo a medida.', 1, 3);
