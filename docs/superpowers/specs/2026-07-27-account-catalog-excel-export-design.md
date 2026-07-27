# Exportar catálogo de cuentas y subcuentas a Excel

## Objetivo
Agregar en `resources/views/account_catalog/index.blade.php` un botón que descargue un Excel con todas las cuentas y subcuentas.

## Diseño
- **Ruta**: `GET /accounts/export` con nombre `accounts.export`, dentro del grupo existente protegido por `can:catalogo_cuentas.ver`.
- **Controlador**: método `export()` en `AccountCatalogController`. Consulta `Account` con `subaccounts` ordenadas por nombre, cuentas ordenadas por código (sin cargar productos ni contadores).
- **Archivo**: generado con `phpoffice/phpspreadsheet` (ya instalado). Una hoja "Cuentas y subcuentas" con columnas: No. Cuenta, Cuenta, No. Subcuenta, Subcuenta, Activo fijo (Sí/No), Activa (Sí/No). Una fila por subcuenta; las cuentas sin subcuentas aparecen con las columnas de subcuenta vacías. Encabezado en negritas, anchos de columna automáticos.
- **Descarga**: respuesta streameada `.xlsx` con nombre `cuentas_subcuentas_YYYY-MM-DD.xlsx`.
- **Botón**: en el header de la card del listado, enlace verde con ícono y texto "Descargar Excel" hacia `route('accounts.export')`.

## Fuera de alcance
Contadores (productos/perfiles/departamentos/usuarios), filtros, y exportación de productos asociados.
