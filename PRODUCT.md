# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

- **Personal interno de TotalGas y sus empresas** (entre ellas DGA, GASOMEX, SMA y TSA): solicitantes que levantan requisiciones, compradores que cotizan y emiten órdenes de compra, contabilidad que valida facturas y provisiones, jefes de departamento, autorizadores y dirección general que aprueban gasto, y responsables de catálogos y presupuesto.
- **Proveedores externos**: se registran, mantienen su expediente documental al día, responden cotizaciones, suben evidencia de entrega y facturas (CFDI).

El uso ocurre por igual en escritorio y en celular: la captura pesada suele hacerse en computadora de oficina, pero aprobaciones, consultas y seguimiento se hacen con frecuencia desde el móvil.

## Product Purpose

Portal de abastecimiento y control presupuestal: cubre el ciclo requisición → cotización → orden de compra (regular o directa) → recepción → factura, junto con el expediente y cumplimiento de proveedores.

Lo que el portal debe garantizar por encima de todo es el **control presupuestal**: ningún gasto se compromete sin presupuesto disponible y sin pasar por la ruta de aprobación correcta (por monto, centro de costo y jerarquía). El éxito es que cada peso comprometido tenga presupuesto y autorización trazable.

## Operating Context

- Presupuesto anual por empresa, distribuido por centro de costo, subcuenta y mes; movimientos presupuestales con su propio flujo de aprobación.
- Catálogo de empleados sincronizado diariamente desde TRESS; los empleados pueden promoverse a usuarios del sistema.
- Controles satelitales: lista EFOS (art. 69-B SAT), REPSE/SIROC, validación de CFDI, tipo de cambio.
- Notificaciones por correo y en sistema para aprobaciones, vencimientos de contratos, cotizaciones y documentos.
- Roles y permisos granulares (Spatie Permission); auditoría de cambios (Activity Log).

## Capabilities and Constraints

- Aplicación Laravel 12 con Blade, Livewire 3 y Alpine; tema administrativo Zircos sobre Bootstrap 5.3; tablas con DataTables (exportación a Excel/PDF).
- SQL Server en operación, SQLite en memoria para pruebas.
- Idioma de la interfaz: español (México). Montos en MXN y moneda extranjera con tipo de cambio.
- Todas las pantallas deben funcionar en celular, no solo en escritorio, porque el uso móvil es tan frecuente como el de escritorio.

## Brand Commitments

- Nombre del producto: Portal de Proveedores (TotalGas).

## Evidence on Hand

- Documentación técnica y funcional en `docs/` (`documento-tecnico-portal-proveedores.md`, `FRD_Portal_Proveedores.md`, matriz de pruebas, ERD).
- Presupuestos anuales 2026 reales por empresa en `docs/`.
- No hay testimonios, métricas de uso ni casos publicados; no deben inventarse.

## Product Principles

1. **El presupuesto manda.** Toda pantalla que compromete gasto muestra el presupuesto disponible y bloquea o advierte antes de comprometerlo, no después.
2. **La ruta de aprobación siempre es visible.** Quien solicita, compra o aprueba debe saber en qué paso está un documento y quién sigue.
3. **Estado legible de un vistazo.** En listados operativos, el estado relevante (activo, con usuario, aprobado, vencido) se identifica sin abrir el registro.
4. **Móvil es de primera clase.** Aprobar, consultar y dar seguimiento debe ser tan cómodo en celular como en escritorio.
5. **Trazabilidad sin fricción.** Cada acción queda registrada, sin pedir al usuario pasos extra para lograrlo.
