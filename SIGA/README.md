# SIGA-UTN — Gestión docente y verificación de atinencias

Aplicación Laravel/Livewire basada en la plantilla SIGA-UTN del curso. Implementa DO-01, DO-02, DO-02a, DO-02b y DO-02d sin sustituir la autenticación, roles/permisos, exportadores ni diseño incluidos por el profesor.

## Funcionalidad implementada

- Registro de docentes y atestados académicos con auditoría de altas, cambios y eliminaciones.
- Catálogo de atinencias por carrera/curso. Cada guardado crea una versión nueva e inmutable con acuerdo, La Gaceta y fechas de vigencia obligatorios.
- Selección de la versión vigente usando la fecha de inicio del cuatrimestre destino.
- Respaldo con la última versión disponible y marca `provisional` cuando no existe una versión vigente para esa fecha.
- Motor de cuatro resultados: `eligible`, `not_eligible`, `technical_note` y `no_catalog`.
- Bloqueo de asignaciones no atinentes.
- Nota técnica con PDF firmado obligatorio, fecha límite de ratificación y detección automática de vencimiento.
- Aprobación o rechazo manual para asignaciones sin catálogo, con auditoría.
- Exportación visible a PDF y Excel de docentes/atestados, catálogo versionado e historial de verificaciones, protegida por permisos.
- API JSON protegida con JWT HMAC-SHA256.
- Consumo de una API REST externa configurable para obtener la hora de Costa Rica usada al evaluar vencimientos del SLA.
- TypeScript para anunciar resultados a lectores de pantalla.
- Interfaz completamente en español mediante `lang/es.json`; clases, métodos, tablas, columnas y variables en inglés.
- Control de accesibilidad A/AA/AAA y modo oscuro conservados de la plantilla.

## Arquitectura

El bounded context `src/TeachingEligibility` está dividido en tres módulos:

```text
TeachingEligibility/
├── Teacher/       # Docentes y atestados (DO-01)
├── Catalog/       # Catálogo versionado (DO-02)
└── Verification/  # Motor, asignaciones, nota técnica y aprobación manual
```

Cada módulo conserva las capas `Domain`, `Application`, `Infrastructure` y `Presentation`. `Domain` contiene PHP puro y no importa Laravel, Livewire ni Eloquent. Los modelos ORM permanecen en `app/Models`, siguiendo la convención de la plantilla.

Las migraciones Laravel son la fuente canónica del esquema. El SQL entregado se utilizó como referencia funcional, pero sus nombres en español no se importan directamente porque el proyecto exige tablas y columnas en inglés.

## Requisitos

- PHP 8.3 o superior.
- Composer 2.
- Node.js 20 o superior y npm.
- Google Chrome, Chromium o Microsoft Edge para generar las exportaciones PDF. Las ubicaciones comunes se detectan automáticamente.
- SQLite para desarrollo rápido, o MySQL 8.

## Instalación con SQLite

Desde la carpeta `SIGA`:

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Copie el último valor generado en `.env`:

```dotenv
DB_CONNECTION=sqlite
JWT_SECRET=valor_generado_de_64_caracteres
APP_LOCALE=es
```

Después ejecute:

```bash
php artisan migrate:fresh --seed
npm install
npm run build
php artisan serve
```

Si el navegador está instalado en una ubicación no estándar, configure su ejecutable en `.env`:

```dotenv
PDF_CHROME_PATH=/ruta/completa/al/ejecutable/de/chrome
```

Abra `http://127.0.0.1:8000`.

Usuario administrador de demostración:

- Administrador: `admin@gmail.com` / `12345678`
- Coordinadora de Docencia: `coordinadora@gmail.com` / `12345678`
- Consulta: `consulta@gmail.com` / `12345678`

Cambie estas contraseñas antes de desplegar el sistema fuera de un entorno académico local.

## Instalación con MySQL

Cree una base vacía con codificación `utf8mb4` y configure:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gestion_academica_utn
DB_USERNAME=usuario
DB_PASSWORD=contraseña
```

Luego use las mismas órdenes `php artisan migrate:fresh --seed`, `npm install` y `npm run build`.

## Rutas de la interfaz

- `/teachers`: docentes y atestados.
- `/eligibility-catalogs`: versiones del catálogo.
- `/eligibility-checks`: verificación, nota técnica y aprobación manual.

Todas requieren sesión autenticada, correo verificado y permisos RBAC.

## API JWT

Solicitar un token:

```bash
curl -X POST http://127.0.0.1:8000/api/auth/token \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@gmail.com","password":"12345678"}'
```

Usar el token:

```bash
curl http://127.0.0.1:8000/api/me \
  -H 'Authorization: Bearer TOKEN'

curl http://127.0.0.1:8000/api/eligibility-checks/1 \
  -H 'Authorization: Bearer TOKEN'
```

La emisión está limitada a diez intentos por minuto. El secreto JWT debe tener al menos 32 bytes.

## Datos de demostración

`TeachingEligibilitySeeder` carga:

- Las 14 carreras en alcance indicadas por el SQL de referencia.
- II Cuatrimestre de 2026.
- Los 25 grupos y 15 cursos de ITI incluidos en `Oferta Académica III C-ITI-2025 - copia.xlsx`, cargados como opciones de contexto para la verificación, más IGA-101 sin catálogo para probar DO-02d.
- Un docente atinente y uno no atinente.
- Catálogos de muestra para ITI-321, ITI-323 e ITI-621 con las 53 denominaciones atinentes del bloque correspondiente del Manual oficial (páginas 299-300).
- El acuerdo y La Gaceta usan identificadores explícitamente marcados `DEMO`; el material entregado no contiene los números oficiales ni su período de vigencia, por lo que deben sustituirse con una nueva versión antes de usar datos reales.
- El acuerdo debe tomarse de la resolución o acta oficial del Consejo Universitario que aprobó el catálogo y el número de La Gaceta debe tomarse de su publicación oficial. El sistema no inventa ni genera esos identificadores.
- IGA-101 deliberadamente sin catálogo para probar DO-02d.

El “criterio técnico firmado” de DO-02b no es el Manual de Atinencias ni el título del docente. Es un oficio o criterio institucional preparado para el docente y grupo concretos, firmado por la Coordinación de Docencia, que fundamenta la asignación provisional por experiencia comprobada. Ese documento debe existir fuera del sistema antes de iniciar la Nota técnica.

El catálogo completo del manual se registra desde la pantalla de versiones; no se sobrescriben versiones históricas.

## Pruebas y control de calidad

```bash
php artisan test
composer run types:check
npm run typecheck
npm run build
```

El código agregado se valida con Pint sobre `app/Http/Controllers/Api`, `app/Http/Middleware`, `app/Security`, `src/Shared/OfficialTime`, `src/TeachingEligibility` y sus pruebas. No se reformatearon masivamente los archivos heredados para evitar cambios ajenos a la plantilla.

Casos automatizados incluidos:

- Coincidencia normalizada de especialidades.
- Resultados atinente, no atinente y sin catálogo.
- Persistencia de la versión aplicada.
- Flujo de nota técnica.
- Aprobación manual auditada.
- Vencimiento automático del SLA.
- Emisión y validación JWT.
- Autorización RBAC de las consultas JWT.
- Renderizado completo de las tres páginas en español.
- Bloqueo de recorridos de ruta en descargas privadas.
- Consumo simulado de la API REST externa.

## Automatización del vencimiento

El dashboard y el historial actualizan las notas técnicas vencidas al consultarse. Para que el vencimiento también se procese sin interacción de un usuario, el proyecto programa la tarea `teaching-eligibility:expire-technical-notes` diariamente a las 00:05. En producción, configure el programador de Laravel:

```cron
* * * * * cd /ruta/a/SIGA && php artisan schedule:run >> /dev/null 2>&1
```

## Archivos y seguridad

- Las consultas usan Eloquent/prepared statements; no se concatena entrada del usuario en SQL.
- Blade escapa los valores con `{{ }}`.
- Los PDF firmados se guardan en el disco privado `local` y se descargan mediante una acción autorizada; no son públicos.
- La descarga acepta únicamente nombres PDF normalizados dentro de `technical-notes`; rechaza recorridos como `../`.
- Las acciones Livewire se autorizan nuevamente en el servidor, aunque el botón no se muestre al usuario.
- Un docente con historial de asignaciones no puede eliminarse; puede marcarse inactivo sin destruir la trazabilidad.
- Los cambios sensibles quedan en `audit_logs` con usuario, fecha, evento y valores anteriores/nuevos cuando aplica.
- Si se actualiza una instalación ya creada, ejecute `php artisan db:seed --class=PermissionSeeder --force` y `php artisan db:seed --class=RoleSeeder --force` para sincronizar los permisos con las acciones que realmente existen en la interfaz.
