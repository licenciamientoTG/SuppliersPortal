# Respaldos manuales de base de datos — Diseño

Fecha: 2026-09-23

## Objetivo

Permitir que usuarios autorizados generen manualmente, desde el portal, un respaldo
nativo (`.bak`) de la base SQL Server, lo almacenen dentro del proyecto, lo consulten
con sus detalles y lo descarguen. Se conservan como máximo 3 respaldos.

## Requisitos (dichos por el usuario)

- Botón para generar el respaldo manualmente.
- El respaldo se guarda en el mismo proyecto.
- Máximo 3 copias; al crear una nueva se elimina la más vieja.
- Solo `aldo.ochoa@totalgas.com` y `daniel.ramirez@totalgas.com` pueden generarlos, verlos y descargarlos.
- Listado con detalles (peso, fecha de creación, etc.) y descarga.
- El acceso vive en el menú de opciones del perfil de usuario (dropdown del navbar), **no** en el sidebar.

## Decisiones

- **Método:** `BACKUP DATABASE` nativo de SQL Server hacia una carpeta compartida.
- **Ubicación:** la carpeta `storage/app/private/backups` del servidor de la app se comparte por SMB
  (p. ej. `\\SERVIDOR-APP\portal-backups`). SQL Server escribe vía UNC; la app lee/borra localmente.
- **Autorización:** lista de correos configurable, con los dos correos anteriores como default.

## Configuración — `config/db_backups.php`

| Clave | Env | Default | Uso |
|---|---|---|---|
| `allowed_emails` | `DB_BACKUP_ALLOWED_EMAILS` (CSV) | los dos correos | Quién puede usar el módulo |
| `sql_path` | `DB_BACKUP_SQL_PATH` | `null` | Carpeta destino tal como la ve SQL Server (UNC) |
| `local_path` | `DB_BACKUP_LOCAL_PATH` | `storage_path('app/private/backups')` | La misma carpeta vista desde la app |
| `keep` | `DB_BACKUP_KEEP` | `3` | Copias exitosas a conservar |
| `timeout` | `DB_BACKUP_TIMEOUT` | `1800` | Segundos máx. de ejecución |

Si `sql_path` no está configurado, el botón se deshabilita y la vista muestra un aviso de configuración.
Las variables se documentan en `.env.example`.

## Autorización

- Gate `manage-db-backups`: compara `strtolower($user->email)` contra `allowed_emails` normalizados.
- El `Gate::before` de `superadmin` en `AppServiceProvider` se modifica para **no** aplicar a
  `manage-db-backups` (un superadmin fuera de la lista recibe 403).
- Rutas bajo middleware `auth`, `lock`, `can:manage-db-backups`, prefijo `/admin/db-backups`:
  - `GET  /` → `db-backups.index`
  - `POST /` → `db-backups.store`
  - `GET  /{databaseBackup}/download` → `db-backups.download`

## Modelo — tabla `database_backups`

| Columna | Tipo |
|---|---|
| `id` | bigint PK |
| `filename` | string, único |
| `database_name` | string |
| `size_bytes` | unsigned bigInteger, nullable |
| `status` | string (`running`, `completed`, `failed`) |
| `error_message` | text, nullable |
| `created_by` | FK `users.id`, nullable, `nullOnDelete` |
| `started_at` / `finished_at` | timestamp, nullable |
| `timestamps` | |

Modelo `DatabaseBackup` con accesors `human_size` y `duration_seconds`, y relación `creator()`.

## Servicio — `App\Services\DatabaseBackupService`

`create(User $user): DatabaseBackup`

1. Toma `Cache::lock('db-backup', timeout)`; si ya está tomado lanza una excepción de dominio
   ("Ya hay un respaldo en curso").
2. Valida `sql_path` configurado y que `local_path` exista (lo crea si no).
3. Nombre: `{database}_{Ymd_His}.bak`; crea registro `running`.
4. Ejecuta vía `DB::connection()->statement()`:
   `BACKUP DATABASE [<db>] TO DISK = N'<sql_path>\<file>' WITH COPY_ONLY, INIT, CHECKSUM, NAME = N'Portal manual backup'`
   (nombre de BD entre corchetes con `]` escapado; ruta con `'` escapado). `set_time_limit(timeout)`.
   La ejecución del SQL se aísla en un método protegido `runBackupStatement()` para poder simularla en pruebas.
5. Verifica que el archivo exista en `local_path`; guarda `size_bytes`, `finished_at`, `status=completed`.
6. Rotación: los `completed` más allá de los `keep` más recientes → borra archivo y registro.
   Los registros `failed` más viejos que el respaldo recién completado también se eliminan.
7. En error: borra archivo parcial si existe, marca `failed` con mensaje, relanza.
8. Registra en activitylog (creado / eliminado por rotación / fallido).

Un fallo nunca elimina respaldos exitosos previos.

## Controlador — `DatabaseBackupController`

- `index`: lista respaldos (más recientes primero) + estado de configuración.
- `store`: llama al servicio; redirige con flash de éxito o error.
- `download`: solo `completed`; resuelve la ruta con `local_path` + `filename` del registro
  (nunca desde el request); `basename()` como defensa extra; 404 si el archivo no existe.

## UI

- `navbar.blade.php`, dropdown de perfil: entrada "Respaldos de BD" (`ti ti-database-export`)
  envuelta en `@can('manage-db-backups')`, junto a las opciones existentes.
- Vista `resources/views/db-backups/index.blade.php` con el layout del portal:
  botón "Generar respaldo" (con confirmación y estado "Generando…" deshabilitado al enviar),
  aviso de que se conservan 3 copias, tabla con archivo, BD, peso, fecha de creación,
  generado por, duración, estado y botón Descargar.

## Pruebas — `tests/Feature/DatabaseBackupTest.php`

Servicio con `runBackupStatement()` simulado (escribe un archivo falso) y `local_path` en un
directorio temporal.

- Usuario no listado → 403 en index, store y download; enlace no visible en el navbar.
- Superadmin no listado → 403.
- Usuario listado ve el índice y genera un respaldo (registro `completed`, archivo existe, peso correcto).
- Al generar el 4.º respaldo quedan 3 y se elimina el más viejo (archivo y registro).
- Fallo del BACKUP → registro `failed`, respaldos previos intactos.
- Descarga devuelve el archivo; 404 si el archivo desapareció.

## Requisitos de infraestructura (para el PR)

- `cguser` necesita permiso `BACKUP DATABASE` (rol `db_backupoperator`).
- La cuenta de servicio de SQL Server necesita escritura sobre el share del servidor de la app;
  la cuenta de la app (IIS/PHP) necesita lectura/borrado en la carpeta local.
- `storage/app/private/backups` se agrega a `.gitignore`.

## Fuera de alcance

Restauración desde el portal, respaldos programados, cola/segundo plano, borrado manual.
