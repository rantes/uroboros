# Uroboros

**Operational Event Mesh** — una plataforma de operaciones de software
construida sobre Commands, Events y Reactions, no sobre pipelines.

Uroboros no es una alternativa a Jenkins/GitHub Actions/GitLab CI en el
sentido de "otro runner de pipelines". Esas herramientas responden
"¿pasó el build?". Uroboros busca responder tres preguntas distintas,
en segundos: **¿qué está pasando?**, **¿por qué está pasando?**, y
**¿qué debería hacer?** — visibilidad operativa, trazabilidad de
eventos y recomendaciones accionables, no solo ejecución de tareas.

Ver `Vision_y_arquitectura_fundacional.md` para la visión completa del
producto (ajusta el link si la ruta real en tu repo es distinta —no
asumida aquí).

## Principios core: Command → Event → Reaction

```text
Command (intención)
    ↓
Command Handler (único autorizado a mutar estado y crear Events)
    ↓
Event (hecho inmutable, persistido en el Event Store)
    ↓
Event Bus (distribuye el Event a las Reactions suscritas)
    ↓
Reaction (interpreta el hecho, despacha nuevas Commands — nunca crea Events)
    ↓
...el ciclo continúa
```

- **Commands** son DTOs planos, nunca se persisten ("Not persistent
  history").
- **Events** son inmutables, se guardan en un Event Store append-only
  (tabla `events`), y son la única fuente de verdad histórica.
- **Reactions** nunca crean Events directamente — solo despachan
  Commands. Esto mantiene el principio "todo empieza con una
  intención" de forma literal en todo el sistema.
- El Event Bus es **síncrono, in-process, sin broker externo** —
  decisión deliberada, no una limitación temporal. Ver
  `.claude/specs/nucleo-oem/design.md`.

Este ciclo es el núcleo de la plataforma, pero no todo en Uroboros pasa
por él — la gestión de datos de referencia (ej. Proyectos) es CRUD
administrativo simple, sin OEM de por medio. El núcleo se reserva para
dominios de ejecución/operación real. Ver
`.claude/specs/gestion-proyectos/requirements.md` para el razonamiento
completo de esa distinción.

## Stack tecnológico

| Capa | Tecnología |
| --- | --- |
| Backend | DumboPHP — framework MVC propio, ActiveRecord, PHP 8.1+ |
| Frontend | DumboJS — Web Components nativos, sin frameworks externos |
| Base de datos | MySQL vía PDO |
| Tests | Timothy (test runner propio de DumboPHP) |
| Cobertura | Clover (`coverage.xml`) consumido por SonarQube como quality gate |

Ambos frameworks (DumboPHP y DumboJS) son desarrollo propio de la
empresa, batalla-probados en otros productos en producción — no son
experimentales. Ver "Principios de gobierno" más abajo para el porqué
de esta decisión.

## Estado actual

| Spec | Estado | Qué cubre |
| --- | --- | --- |
| `nucleo-oem` | ✅ Cerrado | Command Bus, Event Bus, Event Store, arnés de regresión Ping/Complete |
| `metricas-oem` | 🔧 En corrección | Contadores agregados (Commands/Reactions despachados y fallidos) para el dashboard |
| `gestion-proyectos` | 🔧 En progreso | Modelo de datos de Proyectos y Grupos (muchos-a-muchos); CRUD vía mecanismo genérico propio |
| `dashboard-shell` | ✅ Implementado | Sidebar, topbar, footer de métricas — layout persistente del panel operativo |

Ningún dominio de ejecución real (Workflow Execution, Health
Management, Dependency Management) existe todavía — son parte de la
visión de producto, sin spec ni implementación. El dashboard actual
refleja esto honestamente: los widgets sin dominio detrás muestran un
estado "Próximamente" explícito, nunca datos simulados.

## Estructura del repositorio

```text
.claude/
├── specs/{feature}/          # requirements.md, design.md, tasks.md por feature
│   └── PROMPT-*.md            # prompts de implementación/verificación para el agente local
└── rules/                     # propuestas de adición a CLAUDE.md/convenciones (no permanentes)

app/
├── models/                    # ActiveRecord — un archivo por entidad
├── controllers/                # MVC estándar
├── commands/                  # DTOs de intención (Command)
├── command_handlers/          # Únicos autorizados a crear Events
├── reactions/                 # Escuchan Events, despachan Commands
├── buses/                     # CommandBus, EventBus — clases concretas
├── helpers/                   # Funciones globales por archivo (convención DumboPHP)
└── views/                     # .phtml, sintaxis alternativa PHP

config/                        # Configuración de runtime (host.php, db_settings.php,
                                # reactions_map.php) — no participa del autoload por namespace

migrations/                    # Una clase por tabla
tests/                         # Timothy — un archivo por modelo/controlador
tests/fixtures/                # Fixtures de test, namespace separado del código de dominio

ui-components/                 # Componentes DumboJS (Web Components nativos)
```

## Primeros pasos

```bash
# Desde la raíz del proyecto (donde está config/host.php)

# Crear todas las tablas
dumbo migration up all

# Correr toda la suite de tests
dumboTest all

# Generar un scaffold nuevo (modelo + migración + controlador + vistas CRUD)
dumbo generate scaffold nombre_tabla campo:string precio:float

# Generar un componente DumboJS nuevo
php ./uibuilder.php generate component dmb-nombre-componente
```

Ver [`cli.md`](.claude/rules/cli.md) para la referencia completa del
CLI.

## Flujo de desarrollo — Specification-Driven Development (SDD)

Toda feature nueva pasa por tres documentos antes de tocar código:

```text
.claude/specs/{feature}/requirements.md   # Qué — historias de usuario, criterios de aceptación
.claude/specs/{feature}/design.md         # Cómo — arquitectura, decisiones, código de referencia
.claude/specs/{feature}/tasks.md          # Plan de implementación, paso a paso
```

La IA asistente (Claude) participa **únicamente** en la fase de
requirements/design/tasks y en la redacción de prompts de
implementación — nunca escribe código de producción directamente. Un
agente de código local ejecuta la implementación siguiendo esos
prompts; el dueño del proyecto revisa, corrige y aprueba cada PR
manualmente. Ver [`sdd-workflow.md`](.claude/rules/sdd-workflow.md).

## Principios de gobierno arquitectónico

Codificados en `CLAUDE.md` a raíz de decisiones tomadas durante el
desarrollo del núcleo OEM:

- **Lo propio prima sobre lo importado** — un patrón de otro
  ecosistema (DDD, Java, mensajería externa) que choca con una
  convención ya establecida se descarta por completo, nunca se
  pospone "para evaluar después".
- **Aprovechamiento de lo existente** — antes de abstraer o traer una
  dependencia nueva, verificar si el SO, PHP nativo, o el stack ya
  presente resuelve el mismo problema. Esto no es licencia para usar
  esas herramientas sin disciplina (ver "ejecución secuencial" abajo).
- **Ejecución por lotes es secuencial**, nunca paralela a nivel de
  proceso del sistema operativo, salvo diseño explícito de
  coordinación.
- **Cobertura de código nunca por debajo del 98%** — aplicado por
  SonarQube como quality gate, no por `dumboTest` en local.
- **Sin dependencias Composer en runtime**, sin brokers de mensajería
  externos, sin ACL/multiusuario (mientras no exista un requisito real
  que lo justifique).

## Licencia

MIT Licence