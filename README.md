# Kyros Pulse

> SaaS multi-tenant de **CRM + WhatsApp + IA** construido con PHP 8.2 puro, MySQL 8, Tailwind CSS y Alpine.js.

Plataforma profesional para que empresas gestionen clientes, leads, conversaciones de WhatsApp (via Wasapi), tickets, campanas, automatizaciones y reportes — todo potenciado por **Claude Sonnet 6**.

---

## Stack tecnico

| Capa | Tecnologia |
|------|------------|
| Backend | PHP 8.2+ puro (sin Laravel ni frameworks pesados) |
| Base de datos | MySQL 8+ / InnoDB / utf8mb4 |
| Frontend | Tailwind CSS + Alpine.js + Chart.js |
| WhatsApp | [Wasapi API](https://wasapi.io) |
| Email | [Resend](https://resend.com) |
| IA | [Claude Sonnet 6 (Anthropic)](https://anthropic.com) |
| Patron | MVC personalizado con front controller |
| Autoload | PSR-4 nativo (sin Composer requerido para correr) |

---

## Estructura del proyecto

```
/kyros-pulse
├── app/
│   ├── Controllers/      # Controladores HTTP
│   ├── Core/             # Framework MVC (Router, Database, Auth, Csrf, ...)
│   ├── Helpers/          # Funciones globales (env, url, csrf_field, ...)
│   ├── Middlewares/      # Auth, Guest, Tenant, Csrf, Role, RateLimit
│   ├── Models/           # Modelos PDO con scope multi-tenant
│   ├── Services/         # WasapiService, ResendService, ClaudeService, HttpClient
│   └── Views/            # Templates PHP + layouts (landing/app/auth/error)
├── config/
│   ├── app.php           # Config general (timezone, paths, sesion, rate limit)
│   ├── database.php      # Conexion MySQL
│   └── services.php      # Wasapi / Resend / Claude
├── database/
│   ├── migrations/
│   │   └── 001_initial_schema.sql
│   └── seeders/
│       └── 001_basic_data.sql
├── public/
│   ├── index.php         # Front controller
│   ├── .htaccess         # URL rewrite + headers seguridad
│   └── assets/
├── routes/
│   ├── web.php           # Rutas con sesion
│   └── api.php           # Webhooks publicos
├── storage/
│   ├── logs/             # kyros-YYYY-MM-DD.log
│   ├── cache/
│   └── uploads/
├── docs/
│   ├── INSTALL.md
│   ├── DEPLOYMENT.md
│   └── WASAPI_WEBHOOK.md
├── .env                  # Variables de entorno (NO commitear)
├── .env.example
├── .htaccess             # Redirige todo a /public
├── composer.json
├── install.php           # Instalador CLI (crea BD + ejecuta migraciones)
└── README.md
```

---

## Instalacion rapida (XAMPP / desarrollo local)

1. **Clonar/copiar** el proyecto en `C:/xampp/htdocs/KyrosPulse`.

2. **Configurar `.env`** (ya viene con valores por defecto para XAMPP):
   ```env
   DB_HOST=127.0.0.1
   DB_NAME=kyros_pulse
   DB_USER=root
   DB_PASS=
   APP_URL=http://localhost/KyrosPulse/public
   ```

3. **Iniciar Apache + MySQL** desde el panel XAMPP.

4. **Ejecutar el instalador**:
   ```bash
   cd C:/xampp/htdocs/KyrosPulse
   C:/xampp/php/php.exe install.php
   ```
   Esto crea la BD `kyros_pulse`, ejecuta las migraciones y siembra datos basicos.

5. **Abrir en navegador**:
   - Landing:  http://localhost/KyrosPulse/public/
   - Login:    http://localhost/KyrosPulse/public/login

### Cuentas de prueba sembradas

| Rol | Email | Password |
|-----|-------|----------|
| Super Admin | `admin@kyrosrd.com` | `admin12345` |
| Owner Demo | `owner@kyrosrd.com` | `demo12345` |

> Cambia ambas en produccion.

---

## Configuracion de integraciones

Edita `.env` con tus credenciales:

```env
# Wasapi (WhatsApp)
WASAPI_BASE_URL=https://api-ws.wasapi.io/api/v1
WASAPI_API_KEY=tu_api_key_global_o_por_tenant
WASAPI_WEBHOOK_SECRET=secret_para_validar_firma

# Resend (correo)
RESEND_API_KEY=re_xxxxxxxxxxxx
RESEND_FROM_EMAIL="Kyros Pulse <no-reply@kyrosrd.com>"

# Claude (IA)
CLAUDE_API_KEY=sk-ant-xxx
CLAUDE_MODEL=claude-sonnet-6
```

Cada **tenant** tambien puede sobrescribir sus propias `wasapi_api_key`, `resend_api_key`, `claude_api_key`, `claude_model` desde su panel (campos en la tabla `tenants`). Los servicios buscan primero la del tenant, luego caen al global del `.env`.

---

## Modulos implementados

| Modulo | Estado | Notas |
|--------|--------|-------|
| Landing page premium | Listo | Hero animado, beneficios, planes, FAQ, integraciones, CTA. |
| Autenticacion | Listo | Login, registro empresa, recuperacion, verificacion email. |
| Multi-tenant | Listo | `tenant_id` en todas las tablas + middleware de aislamiento. |
| Roles & permisos | Listo | 6 roles, ~25 permisos sembrados. |
| Dashboard con metricas | Listo | KPIs, grafico Chart.js, alertas, top agentes. |
| Servicio Wasapi | Listo | sendText/Media/Template con `from_id` automatico + processWebhook + validateWebhook. |
| Servicio Resend | Listo | sendEmail + plantillas HTML (verify, reset, invite). |
| Servicio Claude | Listo | summarize, intent, suggestReply, scoreLead, autoReply, etc. |
| Webhook Wasapi | Listo | `POST /webhooks/wasapi/{tenant_uuid}`. |
| Esquema BD completo | Listo | 30+ tablas con FK, indices y JSON metadata. |
| CRM contactos | Esqueleto | Modelo + tabla + busqueda; UI placeholder. |
| Pipeline leads | Esqueleto | Modelo + sembrado de etapas; UI placeholder. |
| Bandeja inbox | Esqueleto | Modelo `Conversation`/`Message`; UI placeholder. |
| Campanas | Esqueleto | Tablas + estado; UI pendiente. |
| Automatizaciones | Esqueleto | Tablas + JSON conditions/actions; runner pendiente. |
| Tickets | Esqueleto | Tabla + comentarios; UI pendiente. |
| Reportes | Esqueleto | Datos disponibles; UI pendiente. |
| Super Admin | Esqueleto | Acceso restringido + middleware; vistas pendientes. |

> Los modulos "esqueleto" tienen su esquema, modelos y rutas placeholder protegidas. La capa de UI/CRUD queda lista para extender.

---

## Seguridad

- Hash **bcrypt** para contrasenas (`PASSWORD_BCRYPT`).
- **CSRF** en todos los formularios POST (token unico por sesion + `Csrf::field()`).
- **Prepared statements** en todo el acceso a BD via PDO.
- **Rate limiting** por IP en login/registro y webhooks (`rate_limits` table).
- **Sesiones** con cookies `HttpOnly`, `SameSite=Lax`, `use_strict_mode`, regeneracion de ID en login.
- Cabeceras de seguridad en `public/.htaccess` (X-Frame-Options, X-Content-Type-Options, Referrer-Policy, ...).
- **TenantMiddleware** evita acceso cruzado entre empresas.
- **Auditoria** de acciones en `audit_logs`.
- Variables sensibles **fuera del repo** (`.env` + `.gitignore`).
- Validacion server-side via `App\Core\Validator`.
- HMAC-SHA256 opcional para validar firma del webhook Wasapi.

---

## Endpoints clave

### Web (HTML, requieren sesion en su mayoria)

| Metodo | Ruta | Notas |
|--------|------|-------|
| GET    | `/`                       | Landing page publica. |
| GET    | `/login`                  | Form de login (guest). |
| POST   | `/login`                  | Auth + rate limit. |
| GET    | `/register`               | Form de registro (guest). |
| POST   | `/register`               | Crea tenant + owner. |
| GET    | `/forgot-password`        | Form recuperacion. |
| POST   | `/forgot-password`        | Envia email Resend. |
| GET    | `/reset-password`         | Form nuevo password. |
| POST   | `/reset-password`         | Aplica cambio. |
| GET    | `/email/verify`           | Verifica token. |
| POST   | `/logout`                 | Cierra sesion. |
| GET    | `/dashboard`              | KPIs + actividad. |
| GET    | `/contacts`, `/leads`, `/inbox`, `/tickets`, `/tasks`, `/campaigns`, `/automations`, `/reports`, `/settings` | Placeholders protegidos. |
| GET    | `/admin`                  | Solo super admin. |

### API / Webhooks

| Metodo | Ruta | Notas |
|--------|------|-------|
| POST   | `/webhooks/wasapi/{tenant_uuid}` | Endpoint Wasapi. |

Ver [docs/WASAPI_WEBHOOK.md](docs/WASAPI_WEBHOOK.md) para payload y firma.

---

## Comandos utiles

```bash
# Reinstalar BD desde cero (CUIDADO: borra todo)
C:/xampp/php/php.exe install.php

# Ver logs en vivo (Linux/Mac)
tail -f storage/logs/kyros-$(date +%Y-%m-%d).log

# Generar password hash
C:/xampp/php/php.exe -r "echo password_hash('miclave', PASSWORD_BCRYPT) . PHP_EOL;"
```

---

## Variables de entorno completas

Ver [.env.example](.env.example).

---

## Licencia

Proyecto propietario de Kyros Pulse. Todos los derechos reservados.
