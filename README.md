# Intranet Corporativa Modular

Intranet Corporativa modular desarrollada con PHP nativo siguiendo una arquitectura ligera inspirada en Laravel. Incluye autenticación con roles, panel personalizable y módulos CRUD para gestionar la comunicación interna.

## Características

- 🔐 **Autenticación segura** con hashing Bcrypt y protección CSRF.
- 👥 **Control de roles** (Administrador Principal, Publicador, Usuario Final).
- 📅 **Calendario de eventos** estilo Google Calendar.
- 📇 **Directorio corporativo** con importación CSV.
- 📰 **Tablón de anuncios** con soporte de imágenes y texto enriquecido (negritas).
- 🌳 **Organigrama jerárquico** con relaciones jefe-colaborador y fotos.
- 🔗 **Botonera de enlaces rápidos** con iconografía y targets personalizables.
- 🌐 **Sitios embebidos** configurables (grid, tamaño, tarjetas).
- 📁 **Repositorio documental** con permisos por rol/usuario y árbol de carpetas.
- ⚙️ **Administración** de usuarios y personalización de marca (logo, colores, nombre).
- 🧱 **Dashboard personalizable** por cada usuario.
- 🎨 Diseño moderno con estilo neumórfico y soporte de colores dinámicos.

## Requisitos previos

- PHP 8.1 o superior con extensiones `pdo_mysql`, `mbstring`, `openssl`, `json` y `fileinfo` habilitadas.
- Servidor web Apache (se recomienda habilitar `mod_rewrite`).
- MySQL 8.x o MariaDB 10.5+.
- Composer (opcional, no se usa en este proyecto pero facilita dependencias adicionales).

## Instalación

1. **Clonar el repositorio**
   ```bash
   git clone https://github.com/tu-organizacion/IntranetClick.git
   cd IntranetClick
   ```

2. **Crear la base de datos**
   ```bash
   mysql -u root -p < database.sql
   ```

   El script crea la base `intranet`, tablas y un usuario administrador inicial:
   - Usuario: `admin@example.com`
   - Contraseña: `Admin123!`

3. **Configurar la conexión a la base de datos**

   Copia el archivo de ejemplo y ajusta tus credenciales locales:
   ```bash
   cp config/database.local.php.example config/database.local.php
   ```

   Edita `config/database.local.php` definiendo las claves que necesites sobrescribir:
   ```php
   <?php
   return [
       'host' => '127.0.0.1',
       'port' => '3306',
       'database' => 'intranet',
       'username' => 'root',
       'password' => 'secret',
       // Descomenta la siguiente línea si tu MySQL usa autenticación por socket (Debian/Ubuntu)
       // 'socket' => '/var/run/mysqld/mysqld.sock',
   ];
   ```

   > Si no creas el archivo, se usarán los valores por defecto definidos en `config/database.php`.

   Si recibes `Access denied for user 'root'@'localhost'` con la contraseña correcta, es probable que tu servidor MySQL tenga habilitado el plugin `auth_socket`. En ese caso habilita la opción `socket` (o especifica un usuario diferente) para que la conexión se realice mediante el archivo UNIX y sin contraseña.

4. **Configurar Apache**

   - Apunta el DocumentRoot a la carpeta `public/`.
  - Asegúrate de que PHP tenga permisos de escritura sobre `public/uploads/`.

   Ejemplo de VirtualHost:
   ```apache
   <VirtualHost *:80>
       ServerName intranet.local
       DocumentRoot /var/www/IntranetClick/public

       <Directory /var/www/IntranetClick/public>
           AllowOverride All
           Require all granted
       </Directory>
   </VirtualHost>
   ```

5. **Probar el acceso**

   - Visita `http://intranet.local/login.php` y autentícate con el usuario inicial.
   - Cambia la contraseña desde el módulo de administración después del primer acceso.

## Estructura del proyecto

```
app/                Clases de soporte (Auth, Database) y helpers globales
config/             Configuración base de la aplicación y base de datos
modules/            Módulos funcionales cargados dinámicamente
public/             Punto de entrada web, activos y vistas públicas
public/uploads/     Almacenamiento público de documentos, anuncios, branding y avatares
vendor/             Autoload PSR-4 ligero (sin Composer obligatorio)
```

## Personalización

- **Colores y logotipo**: disponibles en el módulo Administración → Personalización.
- **Dashboard**: cada usuario puede definir el layout y módulos visibles desde el menú de perfil → "Personalizar dashboard".
- **Permisos del repositorio**: las carpetas admiten roles y usuarios específicos (por correo).

## Scripts y utilidades

- `database.sql`: crea toda la estructura y datos iniciales.
- `public/uploads/`: incluye subcarpetas con `.gitkeep` para mantener el árbol y debe ser escribible.

## Seguridad

- Token CSRF en todos los formularios POST.
- Contraseñas cifradas con `password_hash` (Bcrypt).
- Validación de roles/usuarios antes de ejecutar acciones CRUD.
- Sanitización de campos críticos (anuncios, CSV del directorio, documentos).

## Contribución

1. Crea una rama feature: `git checkout -b feature/nueva-funcionalidad`.
2. Implementa y añade pruebas si aplica.
3. Realiza un commit descriptivo y abre un Pull Request.

## Licencia

Proyecto interno para despliegue en intranet corporativa. Ajusta la licencia según la política de tu organización.
