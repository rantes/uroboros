# Duplicar Proyecto

## Introducción

Caso de uso real confirmado por el usuario: Komodo (su SaaS de
gestión de propiedades) despliega esencialmente la misma aplicación
una y otra vez para distintos clientes — cada conjunto es una réplica
del anterior. Configurar un Proyecto nuevo desde cero (credenciales,
archivos de configuración, Workflows con sus pasos) cada vez que
aparece un cliente nuevo es trabajo repetitivo evitable.

## Decisiones de alcance confirmadas

- **Se copian**: `Project` (nombre nuevo pedido al usuario, `type`,
  `repository_url` tal cual), todas sus `ProjectCredential`
  (descifradas y re-cifradas bajo el nuevo Proyecto), todos sus
  `ProjectConfigFile` (mismo criterio de descifrar/re-cifrar), y
  todos sus `WorkflowDefinition` + `WorkflowStepDefinition`.
- **Nunca se copia** `working_directory` — queda vacío en el
  Proyecto nuevo, forzando al usuario a configurar una ruta real
  antes de poder ejecutar nada (ya validado por
  `gestion-proyectos`/la ronda de permisos).
- **Nombres de Workflows duplicados**: cada uno recibe un nombre
  sugerido automático (`"{nombre original} (copia)"`), sin bloquear
  la duplicación con un formulario de múltiples campos — editable
  después por el flujo normal de edición si el nombre sugerido no
  sirve.
- **Referencias de encadenamiento internas** (`encadenamiento-workflows`
  — un `WorkflowDefinition.workflow_definition_id` apuntando a otro
  Workflow del *mismo* Proyecto origen) se remapean a los IDs nuevos
  de la copia — nunca quedan apuntando al Proyecto viejo.

## Requisitos

### Requisito 1 — Duplicar un Proyecto completo

**Historia de usuario:** Como administrador, quiero duplicar un
Proyecto existente con todo lo que ya tiene configurado, para no
repetir manualmente la configuración de cada cliente nuevo de Komodo.

#### Criterios de aceptación

1. DADO un Proyecto existente, CUANDO se dispara "Duplicar", ENTONCES
   se pide un nombre nuevo para el Proyecto antes de proceder.
2. DADO el nombre nuevo confirmado, CUANDO se ejecuta la duplicación,
   ENTONCES se crea un `Project` nuevo con ese nombre, mismo `type`,
   mismo `repository_url`, y `working_directory` vacío.
3. DADO que el Proyecto origen tiene `ProjectCredential`, CUANDO se
   duplica, ENTONCES cada una se recrea bajo el Proyecto nuevo con el
   mismo `name`, con su valor real descifrado del origen y vuelto a
   cifrar bajo el nuevo registro — nunca copiando el blob cifrado tal
   cual (el cifrado no es portable sin pasar por el ciclo
   descifrar/cifrar).
4. DADO que el Proyecto origen tiene `ProjectConfigFile`, CUANDO se
   duplica, ENTONCES mismo criterio que el punto 3 — descifrar y
   re-cifrar, nunca copiar el blob cifrado directamente.
5. DADO que el Proyecto origen tiene uno o más `WorkflowDefinition`,
   CUANDO se duplica, ENTONCES cada uno se recrea bajo el Proyecto
   nuevo con nombre sugerido `"{nombre original} (copia)"`, junto con
   todos sus `WorkflowStepDefinition` (mismo `name`, `type`,
   `command`, `step_order`).
6. DADO que un `WorkflowDefinition` del Proyecto origen tiene
   `workflow_definition_id` apuntando a otro Workflow del mismo
   Proyecto origen, CUANDO se duplica, ENTONCES la copia de ese campo
   apunta al **Workflow nuevo correspondiente** de la duplicación, no
   al Workflow del Proyecto viejo.

## Fuera de alcance

- Duplicar solo una parte del Proyecto (ej. "solo las credenciales")
  — v1 es una copia completa o ninguna.
- Duplicar entre instalaciones distintas de Uroboros — todo ocurre
  dentro de la misma base de datos.
- Programar duplicaciones futuras/automáticas — es una acción manual,
  disparada explícitamente.