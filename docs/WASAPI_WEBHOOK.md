# Webhook Wasapi

Este documento describe como configurar y consumir el webhook publico de Kyros Pulse para recibir mensajes y eventos de WhatsApp via [Wasapi](https://wasapi.io).

## URL del webhook

```http
POST https://tudominio.com/webhooks/wasapi/{tenant_uuid}
```

- `{tenant_uuid}` es el UUID de la empresa (campo `tenants.uuid`).
- Cada empresa tiene su propio endpoint identificable por su UUID. Esto evita pasar el `tenant_id` en headers o body.

Encontraras tu UUID en **Configuracion -> Integracion WhatsApp** dentro del panel.

## Cabeceras

| Header | Obligatorio | Notas |
|--------|-------------|-------|
| `Content-Type` | si | `application/json` |
| `X-Wasapi-Signature` | opcional | HMAC-SHA256 del body crudo con `WASAPI_WEBHOOK_SECRET`. Si esta configurado, firmas invalidas se rechazan con 401. |

### Calculo de la firma

```pseudo
signature = HMAC_SHA256(secret, raw_body_string)
```

Kyros valida con `hash_equals`.

## Eventos soportados

### 1. Mensaje entrante

Wasapi envia mensajes entrantes con `event = receive_message` y los datos reales dentro de `data`.

```json
{
  "event": "receive_message",
  "data": {
    "from_id": 21482,
    "message": "Hola, quiero mas informacion",
    "type": "in",
    "message_type": "text",
    "wa_id": "18091234567",
    "wam_id": "wamid.HBgMNT...",
    "status": "sent"
  }
}
```

Kyros Pulse:

1. Busca el contacto por telefono normalizado.
2. Si no existe, lo crea con `source = 'whatsapp'`.
3. Busca una conversacion abierta o crea una nueva.
4. Guarda el mensaje con `direction = 'inbound'`.
5. Actualiza `last_interaction`, `last_message_at` y `unread_count`.
6. Devuelve `{ "success": true, "contact_id": ..., "conversation_id": ..., "message_id": ... }`.

### 2. Multimedia e interactivos

Tipos soportados: `text`, `image`, `document`, `audio`, `video`, `location`, `contact`, `sticker`, `template`, `interactive`.

```json
{
  "event": "receive_message",
  "data": {
    "type": "in",
    "message_type": "image",
    "wa_id": "18091234567",
    "message": "Mira esto",
    "wam_id": "wamid...",
    "data": "https://cdn.wasapi.io/file/abc.jpg"
  }
}
```

### 3. Estado o mensaje saliente

Wasapi envia actualizaciones salientes con `event = status_message`. Si el mensaje no existe en Kyros, se registra como `direction = 'outbound'`; si ya existe, solo se actualiza el estado.

```json
{
  "event": "status_message",
  "data": {
    "from_id": 21482,
    "message": "Respuesta enviada",
    "type": "out",
    "message_type": "text",
    "wa_id": "18091234567",
    "wam_id": "wamid.HBgMNT...",
    "status": "delivered"
  }
}
```

Estados aceptados: `queued`, `sent`, `delivered`, `read`, `failed`, `received`.

## API saliente

Kyros usa la API actual de Wasapi:

- `GET /whatsapp-numbers` para resolver automaticamente el `from_id` desde `tenants.wasapi_phone`.
- `POST /whatsapp-messages` para texto (`message`, `wa_id`, `from_id`).
- `POST /whatsapp-messages/attachment` para multimedia.
- `POST /whatsapp-messages/send-template` para plantillas.
- `GET /whatsapp-templates` para consultar plantillas.

## Respuestas

| Codigo | Significado |
|--------|-------------|
| `200` | Procesado correctamente. |
| `401` | Firma invalida. |
| `404` | UUID de tenant no encontrado. |
| `429` | Rate limit excedido. |
| `500` | Error interno. |

## Logs

Toda peticion al webhook se registra en `whatsapp_logs` con el body original, respuesta de procesamiento, `success` y `error_message`.

```sql
SELECT * FROM whatsapp_logs
WHERE tenant_id = ? AND direction = 'webhook'
ORDER BY created_at DESC LIMIT 50;
```

## Prueba local

```bash
curl -X POST "http://localhost/KyrosPulse/webhooks/wasapi/UUID-DEL-TENANT" \
  -H "Content-Type: application/json" \
  -d '{
    "event": "receive_message",
    "data": {
      "from_id": 21482,
      "message": "Hola Kyros",
      "type": "in",
      "message_type": "text",
      "wa_id": "18091234567",
      "wam_id": "test-123"
    }
  }'
```

## Configuracion en Wasapi

1. Ingresa al panel de Wasapi -> **Desarrollador** -> **Webhooks**.
2. Crea un webhook activo.
3. URL: `https://tudominio.com/webhooks/wasapi/{tenant_uuid}`.
4. Eventos recomendados: mensajes recibidos y estado de mensajes.
5. Si usas secret, debe coincidir con `WASAPI_WEBHOOK_SECRET`.
