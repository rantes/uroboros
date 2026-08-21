# Encadenamiento de Workflows

## Introducción

Extensión puntual de `ejecucion-workflows`, no un dominio nuevo —
reutiliza el Event `WorkflowCompleted` (ya existe, hoy sin Reaction
asociada), el patrón de Reactions, y `ExecuteWorkflowCommand` (ya
existe). Nace de un caso de negocio real: un proyecto base con
instancias de cliente que deben desplegarse en cascada cuando el
proyecto base termina bien — pero se generaliza a cualquier
encadenamiento Workflow→Workflow, no solo jerarquías de proyecto
padre-hijo.

**Principio central:** cualquier `WorkflowDefinition` puede
declarar que se dispare automáticamente cuando *otro* Workflow
específico (de cualquier proyecto, no necesariamente relacionado)
termina en `completed`. No existe un concepto separado de "proyecto
padre" — es un encadenamiento a nivel de Workflow individual, del
cual el caso de proyecto-base/proyecto-cliente es solo una
aplicación particular.

## Decisiones de alcance ya confirmadas

- **El campo vive en `WorkflowDefinition`**, no en `Project` — un
  `WorkflowDefinition` puede encadenarse a cualquier otro Workflow
  existente, seleccionado explícitamente (no una jerarquía implícita
  de proyectos).
- **Solo dispara en éxito** (`completed`) — nunca en fallo. Un
  Workflow fallido no encadena nada.
- **Rollback nunca se dispara automáticamente**, bajo ninguna
  circunstancia — confirmado explícitamente por el usuario. Sigue
  siendo, y seguirá siendo, una acción 100% discrecional del devops.
  Esto no es parte del alcance de este spec en absoluto, se aclara
  aquí solo para que quede documentado que se consideró y se
  descartó a propósito.
- **Riesgo de ciclos aceptado, no resuelto en v1**: si alguien
  configura A→B→A, el sistema no lo detecta ni lo previene — quedaría
  encolando indefinidamente. Documentado como riesgo operativo
  conocido, no bloqueante para v1.
- **Hallazgo relacionado, fuera de alcance de este spec**: los
  Workflows de tipo `rollback` que terminan en `completed` hoy cuentan
  como éxito en el cálculo de Deployment Success Rate de
  `salud-operativa` — un falso positivo real (un rollback exitoso
  significa que algo falló antes, no que el sistema está sano).
  Anotado para revisar `salud-operativa` en una vuelta futura, no se
  toca en este spec.

## Requisitos

### Requisito 1 — Configurar el encadenamiento

**Historia de usuario:** Como administrador, quiero elegir que un
Workflow se dispare automáticamente cuando otro Workflow específico
termine bien, para no tener que configurar manualmente un paso de
`curl` al webhook cada vez.

#### Criterios de aceptación

1. DADO el formulario de un `WorkflowDefinition`, CUANDO se edita,
   ENTONCES incluye un selector con todos los Workflows existentes
   (de cualquier proyecto) para elegir cuál, si alguno, dispara a
   este al completarse. Vacío/ninguno por defecto (comportamiento
   actual, sin cambios, si no se configura nada).
2. DADO un Workflow ya configurado con un encadenamiento, CUANDO se
   edita de nuevo, ENTONCES se puede cambiar o quitar la selección.

### Requisito 2 — Disparo automático al completarse el Workflow origen

**Historia de usuario:** Como plataforma, quiero que cuando un
Workflow termine en `completed`, cualquier Workflow que lo tenga
configurado como origen se dispare automáticamente, para que las
cascadas de despliegue no dependan de que alguien las dispare a mano.

#### Criterios de aceptación

1. DADO un Workflow que termina en `completed` (Event
   `WorkflowCompleted` ya existente), CUANDO hay uno o más
   `WorkflowDefinition` configurados para dispararse a partir de él,
   ENTONCES cada uno despacha `ExecuteWorkflowCommand` con un
   `trigger_type` nuevo (`'cascade'`), igual que el disparo manual o
   por webhook.
2. DADO un Workflow que termina en `failed`, CUANDO ocurre, ENTONCES
   **no dispara ningún encadenamiento** — solo `completed` cuenta.
3. DADO que un Workflow no tiene ningún otro Workflow configurado
   para dispararse a partir de él, CUANDO termina, ENTONCES no pasa
   nada adicional (comportamiento actual, sin cambios).

### Requisito 3 — Visibilidad del origen de un disparo en cascada

**Historia de usuario:** Como administrador, quiero poder distinguir
en el historial si una ejecución se disparó manualmente, por webhook,
o en cascada desde otro Workflow, para poder rastrear una cadena
completa de despliegues encadenados.

#### Criterios de aceptación

1. DADO un `WorkflowExecution` disparado en cascada, CUANDO se
   consulta (listado de solo lectura, ya existente), ENTONCES su
   `trigger_type` muestra `'cascade'`, distinguible de `'manual'`/
   `'webhook'`.

## Fuera de alcance

- Rollback automático — descartado explícitamente, ver "Decisiones
  de alcance".
- Detección/prevención de ciclos de encadenamiento — riesgo aceptado.
- Corrección del cálculo de Deployment Success Rate para excluir
  Workflows de tipo `rollback` — hallazgo anotado para
  `salud-operativa`, no resuelto aquí.
- Límite de profundidad de cascada (cuántos saltos como máximo) — sin
  límite artificial en v1, más allá del riesgo de ciclos ya aceptado.
- Cancelar o pausar una cadena de cascada ya en curso.