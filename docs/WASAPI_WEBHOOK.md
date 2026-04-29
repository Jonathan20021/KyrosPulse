# Webhook Wasapi

Este documento describe como configurar y consumir el webhook publico de Kyros Pulse para recibir mensajes y eventos de WhatsApp via [Wasapi](https://wasapi.io).

## URL del webhook

```
POST https://tudominio.com/webhooks/wasapi/{tenant_uuid}
```

- `{tenant_uuid}` es el UUID de la empresa (campo `tenants.uuid`).
- Cada empresa tiene su propio endpoint identificable por su UUID — esto evita pasar el `tenant_id` en headers o body.

Encontraras tu UUID en **Configuracion -> Integracion WhatsApp** dentro del panel.

---

## Cabeceras

| Header | Obligatorio | Notas |
|--------|-------------|-------|
| `Content-Type` | si | `application/json` |
| `X-Wasapi-Signature` | recomendado | HMAC-SHA256 del body crudo con el `WASAPI_WEBHOOK_SECRET`. Si esta configurado, las firmas invalidas se rechazan con 401. |

### Calculo de la firma (lado Wasapi)

```pseudo
signature = HMAC_SHA256(secret, raw_body_string)  // hex lowercase
```

Kyros valida con `hash_equals` (timing-safe).

---

## Tipos de eventos soportados

### 1. Mensaje entrante

```json
{
  "type": "message",
  "from": "+18091234567",
  "contact": {
    "name": "Maria Lopez"
  },
  "message": {
    "text": "Hola, quiero mas informacion",
    "type": "text",
    "external_id": "wamid.HBgMNT..."
  }
}
```

**Que hace Kyros Pulse:**
1. Busca el contacto por telefono (`+phone` normalizado).
2. Si no existe, lo crea con `source = 'whatsapp'`.
3. Busca una conversacion abierta o crea una nueva.
4. Guarda el mensaje en `messages` con `direction = 'inbound'`.
5. Actualiza `last_interaction` del contacto y `last_message_at` de la conversacion.
6. Devuelve `{ "success": true, "contact_id": ..., "conversation_id": ... }`.

### 2. Tipos de mensaje soportados

`text`, `image`, `document`, `audio`, `video`, `location`, `contact`, `sticker`, `template`.

Para multimedia incluye `media_url`:

```json
{
  "from": "+18091234567",
  "message": {
    "type": "image",
    "media_url": "https://cdn.wasapi.io/file/abc.jpg",
    "text": "Mira esto",
    "external_id": "wamid..."
  }
}
```

### 3. Actualizacion de estado de mensaje saliente

```json
{
  "type": "status",
  "status": {
    "external_id": "wamid.HBgMNT...",
    "status": "delivered"
  }
}
```

Estados aceptados: `queued`, `sent`, `delivered`, `read`, `failed`, `received`.

Kyros Pulse:
- Busca el mensaje por `external_id` y `tenant_id`.
- Actualiza el estado y los timestamps correspondientes (`sent_at`, `delivered_at`, `read_at`).

---

## Respuestas

| Codigo | Significado |
|--------|-------------|
| `200` | Procesado correctamente. |
| `401` | Firma invalida. |
| `404` | UUID de tenant no encontrado. |
| `429` | Rate limit excedido (60 req/min por IP/path por defecto). |
| `500` | Error interno (revisa `whatsapp_logs`). |

---

## Logs

Toda peticion al webhook se registra en `whatsapp_logs`:

```sql
SELECT * FROM whatsapp_logs
WHERE tenant_id = ? AND direction = 'webhook'
ORDER BY created_at DESC LIMIT 50;
```

---

## Pruebas locales con curl

```bash
curl -X POST "http://localhost/KyrosPulse/public/webhooks/wasapi/UUID-DEL-TENANT" \
  -H "Content-Type: application/json" \
  -H "X-Wasapi-Signature: $(echo -n '{...}' | openssl dgst -sha256 -hmac 'local-dev-secret' | awk '{print $2}')" \
  -d '{
    "type": "message",
    "from": "+18091234567",
    "contact": { "name": "Test User" },
    "message": { "text": "Hola Kyros", "type": "text", "external_id": "test-123" }
  }'
```

---

## Configuracion en Wasapi

1. Ingresa al panel de Wasapi -> **Webhooks**.
2. URL: `https://tudominio.com/webhooks/wasapi/{tenant_uuid}`.
3. Eventos a suscribir: **Message Received**, **Message Status**, **Contact Updated** (opcional).
4. Secret: el mismo valor que pongas en `WASAPI_WEBHOOK_SECRET` del `.env`.

---

## Buenas practicas

- Activa siempre la firma HMAC en produccion.
- Usa un `WASAPI_WEBHOOK_SECRET` largo y aleatorio (`openssl rand -hex 32`).
- Monitorea `whatsapp_logs` para detectar payloads inesperados.
- Implementa reintentos del lado Wasapi: respondemos `200` rapidamente y procesamos en linea, pero si por algo falla, Wasapi reintenta.
