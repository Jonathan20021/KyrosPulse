# Guia de instalacion - Kyros Pulse

## Requisitos

- PHP 8.2+
- MySQL 8+ (o MariaDB 10.6+)
- Apache con `mod_rewrite` habilitado
- Extensiones PHP: `pdo_mysql`, `mbstring`, `curl`, `openssl`, `json`, `intl`, `iconv`, `fileinfo`

---

## Instalacion en XAMPP (Windows)

1. **Copia el proyecto** a `C:/xampp/htdocs/KyrosPulse`.

2. **Verifica `mod_rewrite`** en `C:/xampp/apache/conf/httpd.conf`:
   ```
   LoadModule rewrite_module modules/mod_rewrite.so
   ```
   Y en el `<Directory>` correspondiente:
   ```
   AllowOverride All
   ```

3. **Crea el `.env`** copiando `.env.example` y ajustando credenciales.

4. **Ejecuta el instalador**:
   ```bash
   C:\xampp\php\php.exe install.php
   ```

5. **Accede** a `http://localhost/KyrosPulse/public/`.

---

## Instalacion manual (sin instalador)

```bash
mysql -u root -p < database/migrations/001_initial_schema.sql
mysql -u root -p kyros_pulse < database/seeders/001_basic_data.sql
```

---

## Permisos de carpetas (Linux/cPanel)

```bash
chmod -R 775 storage/
chown -R www-data:www-data storage/
```

En cPanel:
- `storage/` y subcarpetas: **0775**.
- `.env`: **0640** (no debe ser publico).
- `public/`: **0755**.

---

## Configuracion de Virtual Host (Apache)

```apache
<VirtualHost *:80>
    ServerName kyros.local
    DocumentRoot "C:/xampp/htdocs/KyrosPulse/public"

    <Directory "C:/xampp/htdocs/KyrosPulse/public">
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog "logs/kyros-error.log"
    CustomLog "logs/kyros-access.log" common
</VirtualHost>
```

Y en `C:/Windows/System32/drivers/etc/hosts`:
```
127.0.0.1 kyros.local
```

Despues, en `.env`:
```env
APP_URL=http://kyros.local
```

---

## Verificacion

Accede a `http://localhost/KyrosPulse/public/`. Deberias ver:
- Landing page con animaciones
- Botones "Iniciar sesion" y "Empezar gratis"

Login con `admin@kyrosrd.com` / `admin12345` te lleva al panel `/admin`.
Login con `owner@kyrosrd.com` / `demo12345` te lleva al `/dashboard`.

---

## Solucion de problemas

| Problema | Causa probable | Solucion |
|----------|----------------|----------|
| `404 Not Found` en todas las rutas | `mod_rewrite` desactivado | Habilitarlo y reiniciar Apache. |
| `SQLSTATE[HY000] [2002]` | MySQL no esta corriendo | Iniciar MySQL en XAMPP. |
| `Tabla no existe` | No se ejecutaron migraciones | `php install.php`. |
| `Token CSRF invalido` | Sesion expirada o cookies bloqueadas | Recargar y reintentar. |
| Pantalla en blanco | Error PHP silenciado | Ver `storage/logs/kyros-YYYY-MM-DD.log` o setear `APP_DEBUG=true`. |
