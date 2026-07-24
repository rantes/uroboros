# Uroboros — Contexto del proyecto

Operational Event Mesh construido sobre **DumboPHP** (MVC PHP 8.1+)
y **DumboJS** (Web Components nativos). MySQL vía PDO..

## Principios no negociables

- **DRY / KISS** — abstraer si se repite, solución más simple que funcione
- **Lazy Model Load** — nunca `new NombreModelo()` en controladores ni tests;
  siempre `$this->NombreModelo`
- **`_init_()` en lugar de `__construct()`** — en modelos, controladores y tests
- **Tipos de propiedades de modelo reflejan el tipo real de BD** — `?string`
  para texto, `?int` para enteros/IDs/timestamps/flags, `?float` para
  decimales. El cast explícito es una capa de seguridad contra datos inválidos
  recibidos por POST. Nunca `?bool` — usar `?int` con valores 0/1
- **`id`, `created_at`, `updated_at` son automáticos** — no declararlos en el modelo
- **Routing por convención** — `/{controller}/{action}/{params}`; sin archivos de rutas
- **Sin dependencias Composer en runtime** — verificar si existe en el framework
  o PHP nativo primero
- **Sin motor de plantillas externo** — PHP puro con `<?` y `<?=` en vistas `.phtml`
- **Bloques de control con `:` siempre** — `if/elseif/else/endif`,
  `foreach/endforeach`, nunca llaves `{}`
- **Promesas JS con `.then()/.catch()`** — no `async/await`
- **Sin scripts externos** — nunca Python ni herramientas fuera del stack
  del proyecto para cualquier tarea
- **Nunca `<script>` ni `onclick` en vistas `.phtml`** — viola CSP;
  usar `dmb-button-action` con `behavior`, `panel` y `url`
- **`dmb-simple-form` maneja respuestas automáticamente** — via `dmb-dialog`;
  no agregar JS adicional para esto
- **Campos nuevos en tablas existentes** — modificar la migración directamente
  y ejecutar `dumbo migration reset [tabla]`; nunca `Add_Column`
- # Propuesta de adición a CLAUDE.md — Principios no negociables

Agregar esta entrada a la lista de "Principios no negociables" en
`CLAUDE.md`, junto a las demás (DRY/KISS, Lazy Model Load, etc.):

---

- **Gobierno de arquitectura — lo propio prima sobre lo importado.**
  Toda propuesta de patrón, capa o abstracción que provenga de otro
  ecosistema (DDD, Java, frameworks de mensajería externos, etc.) se
  evalúa contra las convenciones ya establecidas del proyecto. Si entra
  en conflicto real con alguna de ellas (no una preferencia de estilo,
  sino una incompatibilidad técnica o de principios), **se descarta por
  completo — nunca se pospone "para evaluar después"**. No existen
  abstracciones "archivadas" esperando el momento de retomarse: si en
  el futuro aparece un requisito genuino que las justifique, se diseña
  una solución propia desde cero, evaluada contra las convenciones
  vigentes en ese momento, no se recicla la propuesta rechazada. Ante
  cualquier duda entre una solución nativa del proyecto (ActiveRecord,
  PHP plano, convención DumboPHP existente) y un patrón de terceros que
  resuelve lo mismo, se prioriza siempre la solución propia.

  Caso aplicado: la abstracción `Message` (base común de Command/Event)
  propuesta en la revisión de arquitectura del núcleo OEM fue
  rechazada — no diferida — por chocar con "Event extiende
  `ActiveRecord`" + "PHP sin herencia múltiple". Ver
  `.claude/specs/nucleo-oem/design.md`, Decisión 3.

---

- **Aprovechamiento de lo existente.** Antes de construir una
  abstracción propia o traer una dependencia externa, verificar si el
  sistema operativo, PHP nativo, o una herramienta ya presente en el
  stack del proyecto resuelve el mismo problema. No se abstrae ni se
  reemplaza algo que el entorno ya sabe hacer bien. Ejemplos ya
  aplicados en el proyecto:
  - Ejecución diferida / scheduling → `cron` + `dumbo run`
    (controladores de background), no un scheduler propio ni un
    broker de mensajería externo.
  - Internacionalización → mecanismos nativos del SO/PHP
    (`gettext`/`Intl`), no una librería de i18n de terceros ni una
    solución hecha a mano.
  Esta regla es un caso particular de la anterior ("lo propio prima
  sobre lo importado"): aquí "lo propio" no es código del proyecto
  sino la plataforma sobre la que corre. Construir una abstracción para
  algo que `cron`, el sistema de archivos, o una extensión nativa de
  PHP ya resuelven es complejidad accidental, no esencial — va en
  contra de DRY/KISS tanto como reinventar código propio ya existente.

  Caso aplicado: se descartaron permanentemente los brokers de
  mensajería externos (Redis, Kafka, RabbitMQ, SQS) como evolución del
  Event Bus del núcleo OEM — no solo por chocar con "sin dependencias
  Composer en runtime", sino porque el patrón nativo ya disponible
  (cola en BD + controlador de background + `cron`) resuelve la
  ejecución asíncrona sin abstraer nada nuevo. Ver
  `.claude/specs/nucleo-oem/design.md`, Decisión 7.

  **Aclaración de disciplina — aprovechar no es usar sin reglas.**
  Que el SO/la plataforma ya resuelva algo no significa que cualquier
  forma de usarlo sea aceptable. Ejemplo aplicado: `cron` permite una
  tarea por línea — nada impide poner 1000 líneas para procesar 1000
  proyectos, pero eso crea 1000 procesos del SO ejecutándose en
  paralelo sobre el mismo recurso, exactamente el tipo de explosión de
  concurrencia sin control que ya se rechazó con los brokers externos,
  solo que a nivel de sistema operativo en vez de a nivel de
  aplicación. El SO tiene que planificar y proteger esos procesos —
  "existe la capacidad" no es lo mismo que "es correcto usarla así".

- **Ejecución por lotes es secuencial, nunca paralela a nivel de
  proceso del SO**, salvo que un spec futuro diseñe explícitamente
  locking/coordinación para permitirlo. Un controlador de background
  que procesa una cola o un conjunto de registros (ej. "actualizar
  1000 proyectos") recorre los pendientes en un `for`/`foreach` dentro
  de un solo proceso — nunca se invoca N veces en simultáneo sobre el
  mismo recurso compartido (ni como N líneas de `cron`, ni como N
  procesos lanzados en background, ni como fan-out paralelo de
  ninguna otra forma). Esto no es una limitación técnica — es lo que
  permite que operaciones sobre recursos compartidos (ej. contadores
  agregados) puedan usar `Find()`+`Save()` simple sin condición de
  carrera, sin necesitar locking explícito ni SQL crudo. Ver caso
  aplicado en `.claude/specs/metricas-oem/design.md`
  (`OemMetric::Increment()`).

---

- **Cobertura de código nunca por debajo del 98%.** Ningún cambio —
  feature nueva, fix, refactor — se considera completo si hace bajar
  la cobertura general del proyecto de ese umbral. No es una meta
  aspiracional, es un criterio de aceptación como cualquier otro.
  Mecanismo: `dumboTest` solo genera `coverage.xml` (reporte Clover,
  requiere XDebug — ver `testing.md`) — no bloquea nada por sí mismo.
  El umbral del 98% se aplica y se hace cumplir en **SonarQube**, que
  consume ese reporte como quality gate. Es decir: correr los tests en
  local no es garantía de cumplir la regla — el gate real está en
  SonarQube, no en la máquina de cada desarrollador.

---

## Reglas PHP — imports y use

Siempre declarar `use` al inicio del archivo para cualquier clase referenciada.
Nunca usar rutas absolutas con `\` en el cuerpo del código.

Orden obligatorio al inicio de cada archivo PHP:

1. `namespace`
2. `use` de clases del framework (`DumboPHP\...`)
3. `use` de clases del proyecto (`App\...`, `Migrations\...`)
4. Definición de la clase

```php
// CORRECTO
use App\Helpers\StorageHandler;

$handler = new StorageHandler('');

// INCORRECTO — nunca rutas absolutas en el cuerpo
$handler = new \App\Helpers\StorageHandler('');
```

## Generación de archivos — usar siempre el CLI primero

Antes de escribir cualquier modelo, migración, controlador o vista,
ejecutar `dumbo generate`. Ahorra tokens y garantiza la estructura correcta:

```bash
dumbo generate scaffold nombre_tabla campo:string precio:float stock:integer:default{0}
dumbo generate model nombre_tabla campo:string otro:text:null
dumbo generate controller nombre accion1 accion2
```

Tipos: `primary`, `integer`, `biginteger`, `string`, `text`, `float`, `decimal`

Modificadores: `campo:string{100}` · `campo:text:null` · `campo:integer:default{0}`

## Tests de controladores — patrón de aserción

`_runAction()` retorna el objeto controlador. Usar siempre `$result->_code`
para verificar el código HTTP. Nunca `http_response_code()` — `ob_get_clean()`
lo limpia y retorna `false`.

```php
// CORRECTO
$result = $this->_runAction('/controller/action');
$this->assertEquals(HTTP_401, (int) $result->_code);

// INCORRECTO
$code = http_response_code();
```

## Flujo SDD para nuevas features

@.claude/rules/sdd-workflow.md

## Referencias de desarrollo

- Controladores: @.claude/rules/dumbophp-controllers.md
- Modelos y Active Record: @.claude/rules/dumbophp-models.md
- CLI completo: @.claude/rules/cli.md
- Convenciones y anti-patrones: @.claude/rules/code-conventions.md
- Núcleo OEM (Commands/Events/Reactions): @.claude/specs/nucleo-oem/design.md

<!-- Las siguientes reglas cargan automáticamente por paths:
     - Vistas (.phtml)      → .claude/rules/views.md
     - Frontend (DumboJS)   → .claude/rules/dumbojs-components.md
     - Migraciones          → .claude/rules/migrations.md
     - Tests                → .claude/rules/testing.md
-->

## Archivos temporales de test

Cuando crees archivos de test temporales (testTmp*.php o similar) para
verificaciones empíricas, elimínalos automáticamente al terminar la
verificación sin pedir autorización adicional.
