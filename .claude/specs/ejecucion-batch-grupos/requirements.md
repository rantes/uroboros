# Ejecución Batch por Grupos

## Introducción

Depende de `duplicar-proyecto` para tener sentido real — una vez que
Komodo tiene varios Proyectos clonados (uno por cliente), este spec
permite disparar el mismo tipo de operación (ej. un despliegue) en
todos los Proyectos de un Grupo de una sola vez, en lugar de entrar a
cada uno manualmente.

## Decisión de alcance confirmada

**Sin "proyecto primordial"** — se descartó la idea original de
validar un Proyecto primero y recién después desplegar al resto. El
mecanismo es agnóstico: "Ejecutar grupo" simplemente dispara, para
cada Proyecto miembro que tenga uno configurado, su Workflow asignado
para ejecución batch.

**Asignación explícita, no por nombre**: dado que los Workflows
duplicados reciben nombres distintos entre sí
(`"{original} (copia)"`), no hay forma confiable de encontrar "el
mismo" Workflow por nombre entre Proyectos de un Grupo. En su lugar,
la relación Proyecto↔Grupo (tabla pivote ya existente) gana un campo
nuevo: qué `WorkflowDefinition` específico de ese Proyecto corre
cuando se ejecuta el Grupo — configurado una vez, reutilizado en cada
disparo futuro.

**Miembros sin Workflow asignado**: se omiten de la ejecución batch
(no bloquean al resto), marcados claramente en el resultado.

## Requisitos

### Requisito 1 — Asignar el Workflow de ejecución batch por Proyecto

**Historia de usuario:** Como administrador, quiero decidir qué
Workflow de cada Proyecto corre cuando ejecuto su Grupo, para que el
disparo batch sepa exactamente qué hacer en cada uno.

#### Criterios de aceptación

1. DADO un Proyecto vinculado a un Grupo, CUANDO se edita esa
   vinculación, ENTONCES se puede elegir, de los Workflows propios de
   ese Proyecto, cuál es el que corre en una ejecución batch de ese
   Grupo (o dejarlo sin asignar).
2. DADO que esa asignación ya existe, CUANDO se reutiliza en un
   disparo batch futuro, ENTONCES no hay que volver a elegirla —
   queda guardada hasta que alguien la cambie explícitamente.

### Requisito 2 — Ejecutar un Grupo completo

**Historia de usuario:** Como administrador, quiero disparar la misma
operación en todos los Proyectos de un Grupo de una sola vez.

#### Criterios de aceptación

1. DADO un Grupo con Proyectos miembros, CUANDO se dispara "Ejecutar
   grupo", ENTONCES se dispara un `WorkflowExecution` nuevo
   (`trigger_type = 'batch'`) para cada Proyecto miembro que tenga un
   Workflow asignado (Requisito 1).
2. DADO un Proyecto miembro sin Workflow asignado, CUANDO se ejecuta
   el Grupo, ENTONCES ese Proyecto se omite de la ejecución — el
   resto del Grupo continúa sin verse afectado.
3. DADO el resultado de disparar el Grupo, CUANDO se muestra al
   usuario, ENTONCES queda claro cuáles Proyectos se dispararon y
   cuáles se omitieron (y por qué).

## Fuera de alcance

- Cualquier noción de "validar primero, desplegar después" a nivel de
  Grupo — descartado explícitamente. Si algún Proyecto necesita
  pasos de validación antes de desplegar, eso vive dentro del propio
  Workflow de ese Proyecto (orden de sus pasos), no en este mecanismo.
- Ejecución batch condicionada al resultado de otros miembros del
  mismo Grupo — cada disparo es independiente de los demás.
- Reintentar automáticamente los Proyectos omitidos — se omiten y ya,
  el usuario decide qué hacer con ellos.