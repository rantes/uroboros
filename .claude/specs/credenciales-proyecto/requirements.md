# Credenciales de Proyecto

## Introducción

Nace de una pregunta operativa real: hoy `WorkflowStepDefinition.command`
es texto libre sin cifrar — un `git clone` de un repositorio privado
necesitaría el token embebido directamente en el comando, expuesto en
texto plano en la base de datos y potencialmente filtrado en el
`output` capturado de la ejecución.

Investigación de mercado (Jenkins, GitHub Actions, Azure DevOps)
confirma un patrón consistente: la credencial vive separada del
comando, referenciada por nombre, inyectada solo en el momento de
ejecutar, y enmascarada automáticamente en cualquier log/salida
capturada. Este spec aplica ese mismo patrón a Uroboros.

**No es lo mismo que `ProjectConfigFile`** (`configuracion-proyecto`)
— esa entidad es para archivos que se sincronizan *a disco*
(`.env`, `config.yaml`, etc.). Una credencial de este spec **nunca se
escribe a disco como archivo propio** — solo se sustituye en memoria,
dentro del comando, en el instante de ejecutarlo.

## Decisiones de alcance

- **Entidad nueva**, `ProjectCredential` — no reutiliza
  `ProjectConfigFile` (semántica distinta: una es "archivo a
  sincronizar", la otra es "secreto a inyectar en memoria, nunca a
  disco").
- **Sintaxis de referencia en el comando**: `{{credential:nombre}}` —
  ej. `git clone https://{{credential:github_token}}@github.com/user/repo.git .`.
  Sustitución ocurre en `RunStepCommandHandler`, en memoria, justo
  antes de `exec()` — el `command` almacenado nunca cambia, siempre
  contiene el placeholder, nunca el valor real.
- **Enmascarado automático en `output`**: antes de guardar
  `StepExecution.output`, cualquier aparición literal del valor real
  de **cualquier** credencial del proyecto (no solo la referenciada
  en ese step específico) se reemplaza por `***` — mismo criterio que
  GitHub Actions.
- **Sin botón "Mostrar"** — a diferencia de `ProjectConfigFile.is_secret`
  (que sí permite revelar el valor), una `ProjectCredential` es
  **write-only desde la UI**: se guarda, nunca se vuelve a mostrar en
  claro. Si hace falta cambiarla, se sobrescribe: no se "recupera".
  Coherente con el patrón real de las herramientas investigadas
  (Jenkins/GitHub Actions tampoco permiten ver una credencial ya
  guardada).
- **Cifrado**: mismo mecanismo ya construido y probado
  (`AES-256-GCM`, `CONFIG_FILES_ENCRYPTION_KEY` — reutilizada, no una
  clave nueva; es la misma categoría de dato, cifrado con el mismo
  propósito).

## Requisitos

### Requisito 1 — Guardar una credencial por Proyecto

**Historia de usuario:** Como administrador, quiero guardar un token
de acceso a un repositorio privado asociado a un Proyecto, para que
los Workflows de ese proyecto puedan clonarlo sin exponer el token en
ningún lado visible.

#### Criterios de aceptación

1. DADO un `Project`, CUANDO se crea una `ProjectCredential`, ENTONCES
   se especifica un `name` (identificador corto, usado en el
   placeholder — ej. `github_token`) y un `value` (el secreto real).
2. DADO que se guarda, CUANDO se persiste, ENTONCES `value` se cifra
   con el mismo mecanismo ya usado para `ProjectConfigFile.content` —
   nunca texto plano en la base de datos.
3. DADO una `ProjectCredential` ya guardada, CUANDO se visualiza en
   cualquier listado o formulario, ENTONCES el valor real **nunca**
   se muestra — ni siquiera parcialmente, ni siquiera al
   administrador que lo guardó.

### Requisito 2 — Referenciar la credencial en un comando

**Historia de usuario:** Como administrador, quiero escribir un
placeholder en el comando de un Workflow Step, para que el token real
se inyecte solo al ejecutar, sin quedar guardado en el propio comando.

#### Criterios de aceptación

1. DADO un `command` que contiene `{{credential:nombre}}`, CUANDO se
   ejecuta el step, ENTONCES el placeholder se sustituye por el valor
   real descifrado, únicamente en memoria, justo antes de correr el
   comando.
2. DADO un placeholder que referencia un `name` sin `ProjectCredential`
   correspondiente para ese proyecto, CUANDO se ejecuta, ENTONCES el
   step falla limpio (mismo criterio que `working_directory` vacío —
   `exit_code = 1`, mensaje claro, nunca corre el comando con el
   placeholder literal sin sustituir).
3. DADO el `command` ya guardado en la base de datos, CUANDO se
   consulta en cualquier momento (edición, listado, Event Store),
   ENTONCES sigue conteniendo el placeholder, nunca el valor real
   sustituido.

### Requisito 3 — Enmascarar el valor en la salida capturada

**Historia de usuario:** Como administrador, quiero que si un
comando accidentalmente imprime el valor de una credencial (ej. Git
ecoando la URL completa en un mensaje de error), ese valor no quede
expuesto en el historial de ejecuciones.

#### Criterios de aceptación

1. DADO el `output` real capturado de un step, CUANDO se guarda en
   `StepExecution.output`, ENTONCES cualquier aparición literal del
   valor descifrado de **cualquier** `ProjectCredential` del proyecto
   se reemplaza por `***` antes de persistir.
2. DADO que el enmascarado ya ocurrió, CUANDO se visualiza el
   `output` en `/admin/step_executions`, ENTONCES el valor
   enmascarado es lo único que existe — el enmascarado ocurre antes
   de guardar, no es un filtro aplicado solo en la vista.

## Fuera de alcance

- Rotación automática de credenciales.
- Múltiples tipos de credencial con comportamiento distinto (SSH key
  vs. token HTTPS vs. usuario/contraseña) — v1 es un valor de texto
  genérico, el formato exacto (token, o `usuario:token`, etc.) es
  responsabilidad de cómo se escribe el `command`, no algo que
  Uroboros valide o entienda.
- Compartir una credencial entre múltiples Proyectos — cada
  `ProjectCredential` pertenece a un único Proyecto, sin excepción.
- Autenticación federada/tokens de corta duración (OIDC) — mencionado
  en la investigación de mercado como tendencia reciente, pero fuera
  de alcance por complejidad, no aplicable a la escala actual.
- Validar que el `value` guardado sea sintácticamente un token válido
  de algún proveedor específico (GitHub, GitLab, etc.) — se guarda
  como texto libre, sin validación de formato.