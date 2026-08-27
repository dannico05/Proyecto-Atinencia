# Diario de decisiones técnicas e IA

Este documento debe continuar actualizándose durante las pruebas y la defensa. Las entradas siguientes corresponden a la implementación inicial del módulo.

## 22 de agosto de 2026 — Alcance y arquitectura

**Consulta realizada:** se solicitó analizar la plantilla SIGA, el enunciado, el manual CRUD, el SQL, los Excel y el Manual de Atinencias para completar DO-01, DO-02, DO-02a, DO-02b y DO-02d.

**Aceptado:** conservar el patrón DDD/hexagonal de la plantilla y crear tres módulos hermanos (`Teacher`, `Catalog`, `Verification`) dentro de `TeachingEligibility`. Esto separa los agregados sin mezclar dominio con Eloquent o Livewire.

**Rechazado:** crear una aplicación nueva o reemplazar los componentes de autenticación, permisos, exportación y accesibilidad. Ya estaban probados en la plantilla y reconstruirlos aumentaba el riesgo sin aportar al alcance.

**Aprendizaje:** un bounded context agrupa lenguaje y reglas relacionadas; no significa que todas las entidades deban compartir un repositorio o una sola clase.

## 22 de agosto de 2026 — Esquema de datos

**Problema detectado:** el SQL de referencia usa tablas y columnas en español, pero la consigna exige el modelo y código en inglés.

**Aceptado:** conservar las relaciones y reglas útiles del SQL (versiones, asignaciones, verificaciones, notas técnicas y auditoría) y expresarlas mediante migraciones Laravel con nombres en inglés.

**Rechazado:** importar directamente `sistema_gestion_academica_utn.sql`; habría creado dos convenciones incompatibles y consultas en español dentro del código nuevo.

**Corrección aplicada:** las migraciones Laravel son ahora la fuente canónica y el README lo deja explícito.

## 22 de agosto de 2026 — Selección de versión

**Consulta realizada:** cómo interpretar el caso donde no existe una entrada vigente para la fecha de inicio del cuatrimestre.

**Aceptado:** buscar primero un intervalo que contenga la fecha; si no existe, aplicar la versión más reciente disponible y marcar el resultado como provisional.

**Rechazado:** usar siempre la versión con mayor número. Esa solución ignora la fecha destino y falla el criterio de aceptación de verificaciones históricas.

**Prueba agregada:** el motor conserva la marca provisional y cada verificación guarda el `eligibility_catalog_id` aplicado.

## 22 de agosto de 2026 — Coincidencia de atestados

**Problema detectado en la propuesta inicial de IA:** una comparación literal sensible a mayúsculas y tildes podría declarar no atinente a “Ingenieria del Software” frente a “Ingeniería del Software”.

**Corrección aplicada:** normalización determinista de mayúsculas, tildes, puntuación y espacios antes de comparar; no se usa coincidencia difusa porque podría producir falsos positivos peligrosos.

**Aprendizaje:** en reglas de habilitación, tolerar variaciones ortográficas controladas es útil, pero aproximar términos diferentes sin revisión humana debilita la trazabilidad.

## 22 de agosto de 2026 — Requisitos transversales

**Aceptado:** implementar JWT HMAC-SHA256 sin agregar paquetes, una API REST externa configurable para la hora oficial de Costa Rica y TypeScript para anuncios accesibles.

**Rechazado:** introducir una dependencia JWT externa o un framework frontend adicional. La plantilla ya contiene las herramientas necesarias y la consigna limita nuevas librerías.

**Controles aplicados:** secreto mínimo de 32 bytes, expiración, validación de firma con `hash_equals`, límite de intentos al emitir tokens, timeout de tres segundos para la API externa y fallback local para continuidad operativa.

## Pendientes del equipo

- Registrar aquí los resultados de las pruebas manuales en navegador.
- Documentar cualquier dato adicional cargado desde el Manual de Atinencias.
- Anotar cambios solicitados durante las revisiones de semanas 10, 12 y 14.
- Registrar qué integrante validó cada regla y qué aprendió al explicarla.

## 23 de agosto de 2026 — Cierre de seguridad y calidad

**Problemas detectados:** una ruta de descarga recibida por Livewire debía validarse más allá de su prefijo; la API JWT autenticaba al usuario, pero la consulta individual también debía respetar RBAC; y eliminar un docente referenciado podía romper la trazabilidad histórica.

**Correcciones aplicadas:** se restringieron las descargas a nombres PDF normalizados del directorio privado, se añadió autorización de lectura al endpoint JWT y se bloqueó de forma controlada la eliminación de docentes con asignaciones. El vencimiento de una nota técnica ahora actualiza también la asignación y genera auditoría.

**Verificación:** la suite final ejecuta 50 pruebas con 124 aserciones, PHPStan nivel 7 informa cero errores y Vite genera los recursos de producción correctamente.

## 27 de agosto de 2026 — Resolución de la Nota técnica

**Fuente revisada:** el enunciado exige que la Nota técnica permanezca pendiente hasta una resolución o hasta vencer su plazo. El SQL de referencia completa la máquina de estados con `Ratificación pendiente`, `Ratificada`, `Vencida` y `Rechazada`, y concede `nota_tecnica.aprobar` únicamente al rol Administrador.

**Decisión aplicada:** la Coordinadora de Docencia conserva la facultad de iniciar la vía excepcional y adjuntar el criterio firmado. El Administrador registra posteriormente la decisión oficial del Consejo como ratificada o rechazada, junto con su referencia o motivo. La decisión actualiza la asignación y queda registrada en auditoría con usuario y fecha. Una nota pendiente que supera la fecha límite continúa pasando automáticamente a vencida.

**Límite conservado:** no se exige un segundo PDF para la resolución porque el enunciado y la relación de `notas_tecnicas` del SQL solo obligan el PDF del criterio técnico inicial. La referencia del acuerdo o el motivo de rechazo se almacena como texto trazable.
