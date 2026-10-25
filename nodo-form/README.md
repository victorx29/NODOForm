# Formulario Nodo CRM (despliegue en EasyPanel)
1. Sube esta carpeta a un repositorio Git privado (GitHub/GitLab).
2. EasyPanel > Project > **+ Service > App**. Source: tu repo. Build: **Dockerfile** (ruta `Dockerfile`).
3. **Environment**: copia las variables de `.env.example` con tus valores (SMTP, MAIL_TO, ADMIN_KEY).
4. **Mounts > Volume**: nombre `nodo-data`, ruta de montaje `/var/www/data` (aquí quedan fotos y datos; sin esto se pierden al redesplegar).
5. **Domains**: dominio o subdominio, puerto **80**, HTTPS activado.
6. Deploy. Formulario: `https://tu-dominio/`. Panel interno: `https://tu-dominio/admin.php` (clave = ADMIN_KEY).
Prueba: llena un envío de ejemplo y confirma que llega el correo. Si no llega, revisa `mail-errors.log` en el volumen.
SMTP: puerto 587 con `SMTP_SECURE=tls`, o 465 con `ssl`.
