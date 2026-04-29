# Guia de despliegue - Kyros Pulse

## Despliegue en cPanel

1. **Subir archivos** via File Manager o SFTP.
   - Subir TODO el proyecto a una carpeta fuera de `public_html`, por ejemplo `~/kyros-app/`.
   - Crear un enlace simbolico de `public_html/` -> `~/kyros-app/public/`, o copiar el contenido de `public/` dentro de `public_html/` y ajustar `index.php`:
     ```php
     require __DIR__ . '/../kyros-app/app/Core/Application.php';
     ```

2. **Crear base de datos** en MySQL Databases (cPanel):
   - DB: `kyros_pulse_prod`
   - User: con todos los privilegios sobre esa BD.

3. **Importar SQL** via phpMyAdmin:
   - Importar `database/migrations/001_initial_schema.sql`.
   - Importar `database/seeders/001_basic_data.sql`.

4. **Configurar `.env`**:
   ```env
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://tudominio.com
   DB_HOST=localhost
   DB_NAME=usuario_kyros_pulse_prod
   DB_USER=usuario_kyros_user
   DB_PASS=clave_fuerte
   SESSION_SECURE=true
   ```

5. **Permisos**:
   ```
   storage/        => 0775
   storage/logs    => 0775
   storage/cache   => 0775
   storage/uploads => 0775
   .env            => 0640
   ```

6. **Verificar** que `mod_rewrite` este activo (suele estar habilitado en cPanel).

7. **Cambiar contrasenas** del super admin y demo.

---

## Despliegue en VPS (Ubuntu + Nginx + PHP-FPM)

### Instalacion de paquetes

```bash
sudo apt update
sudo apt install nginx mysql-server php8.2-fpm php8.2-mysql php8.2-curl php8.2-mbstring php8.2-intl php8.2-xml git
```

### Configuracion de Nginx

```nginx
server {
    listen 80;
    server_name tudominio.com;
    root /var/www/kyros-pulse/public;
    index index.php;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header Referrer-Policy "strict-origin-when-cross-origin";

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }

    location ~ /\.(env|git|htaccess) {
        deny all;
    }

    location ~ ^/(storage|database|app|config|routes)/ {
        deny all;
    }
}
```

### HTTPS con Let's Encrypt

```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d tudominio.com
```

### Permisos

```bash
sudo chown -R www-data:www-data /var/www/kyros-pulse
sudo chmod -R 755 /var/www/kyros-pulse
sudo chmod -R 775 /var/www/kyros-pulse/storage
sudo chmod 640 /var/www/kyros-pulse/.env
```

---

## Cron jobs

Edita el cron del usuario `www-data`:

```bash
sudo crontab -u www-data -e
```

Y agrega:

```cron
# Limpieza de rate limits expirados (cada 5 min)
*/5 * * * * /usr/bin/php /var/www/kyros-pulse/cron/cleanup_rate_limits.php >> /dev/null 2>&1

# Procesar campanas programadas (cada minuto)
* * * * * /usr/bin/php /var/www/kyros-pulse/cron/process_campaigns.php >> /dev/null 2>&1

# Procesar automatizaciones programadas (cada minuto)
* * * * * /usr/bin/php /var/www/kyros-pulse/cron/process_automations.php >> /dev/null 2>&1

# Limpieza de tokens expirados (1 vez al dia)
0 3 * * * /usr/bin/php /var/www/kyros-pulse/cron/cleanup_tokens.php >> /dev/null 2>&1

# Reportes diarios por email (8am)
0 8 * * * /usr/bin/php /var/www/kyros-pulse/cron/daily_reports.php >> /dev/null 2>&1
```

> Los scripts de cron pueden crearse en `/cron` siguiendo el patron de `install.php`. Estan listos para implementarse en la siguiente iteracion.

En cPanel puedes configurarlos desde **Cron Jobs** apuntando a las mismas rutas.

---

## Checklist pre-produccion

- [ ] `APP_ENV=production` y `APP_DEBUG=false`
- [ ] HTTPS forzado (descomentar bloque en `public/.htaccess`)
- [ ] `SESSION_SECURE=true`
- [ ] Contrasenas de super admin y demo cambiadas (o usuarios demo eliminados)
- [ ] `WASAPI_WEBHOOK_SECRET` configurado y compartido con Wasapi
- [ ] Backup automatico de BD configurado
- [ ] Logs rotando (`logrotate` o equivalente)
- [ ] Monitoreo (UptimeRobot, Sentry, etc.)
- [ ] Cabeceras de seguridad activas
- [ ] Archivo `.env` fuera del directorio web publico

---

## Backup

```bash
# Backup BD
mysqldump -u user -p kyros_pulse_prod | gzip > kyros_$(date +%F).sql.gz

# Backup uploads
tar czf uploads_$(date +%F).tar.gz storage/uploads/
```
