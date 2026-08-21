# Archivos de Configuración de Proyecto

## Introducción

Originalmente planteado como "Environment Management" de la visión
original de Uroboros. Durante el diseño se descubrió que **no hace
falta ninguna entidad nueva para "Ambiente"** — el patrón
Proyecto+Grupo ya construido en `gestion-proyectos` lo resuelve
completo: un grupo (`ProyectoWeb`) agrupa sus variantes por ambiente
(`ProyectoWebDev`/`ProyectoWebUAT`/`ProyectoWebProd`), cada una como
su propio `Project` con su propio `working_directory` (ya
implementado en `ejecucion-workflows`). Esto aplica igual al caso de
negocio SaaS (un proyecto base, instancias por cliente, cada una con
sus propios ambientes) — es el mismo patrón, solo con otra
convención de nombres/agrupación, no un modelo de datos distinto.

Lo genuinamente nuevo de este spec es más acotado: **gestión de
archivos de configuración arbitrarios por Proyecto** — no solo
`.env`/`.env.secrets`, cualquier archivo que el proyecto necesite
(YAML, JSON, INI), tantos como hagan falta, con su contenido cifrado
en la base de datos.

## Decisiones de alcance ya confirmadas

- **Sin entidad "Environment" nueva** — el archivo de configuración
  pertenece directamente a un `Project` ya existente.
- **Tres formatos soportados en v1**: INI, JSON, YAML.
  - INI vía `parse_ini_string()` nativo de PHP.
  - JSON vía `json_decode()` nativo de PHP.
  - YAML vía la **extensión PECL `yaml`** — el usuario la instala él
    mismo en el servidor (decisión explícita, rechazando un parser
    YAML propio hecho a mano por robustez). Construir un parser YAML
    nativo propio queda anotado como **deuda técnica de largo plazo**
    del proyecto, no de este spec.
- **Cifrado uniforme**: el contenido de **todos** los archivos se
  cifra en la base de datos, sin excepción — no solo los marcados
  como secretos. `is_secret` es una bandera puramente de UI (oculta
  el valor en el listado hasta hacer click en "Mostrar"), no afecta
  qué se cifra.
- **Mecanismo de cifrado**: `openssl_encrypt()`/`openssl_decrypt()`
  con AES-256-GCM (cifrado autenticado). Clave nueva,
  `CONFIG_FILES_ENCRYPTION_KEY`, generada una sola vez por el usuario
  (`openssl rand -base64 32`), en `.env.secrets` **de Uroboros**
  (nunca en la configuración de ningún proyecto gestionado, nunca en
  git) — completamente separada de `SALT` (propósito distinto: `SALT`
  es para hashing interno de la propia app, esta clave es para
  cifrado reversible de datos ajenos).
- **v1 no se conecta automáticamente al disparo de Workflows** — es
  un CRUD con un botón manual de "Sincronizar al disco" que escribe
  los archivos de un Proyecto a su `working_directory`. La conexión
  automática (que un `WorkflowExecution` sincronice solo antes de
  correr) es una extensión futura, no de este spec.

## Requisitos

### Requisito 1 — Crear y gestionar archivos de configuración

**Historia de usuario:** Como administrador, quiero crear tantos
archivos de configuración como necesite para un Proyecto, con
cualquier nombre y formato, para reflejar la estructura real que ese
proyecto espera en disco.

#### Criterios de aceptación

1. DADO un `Project` existente, CUANDO se crea un archivo de
   configuración, ENTONCES se especifica: nombre/ruta relativa (ej.
   `.env`, `.env.secrets`, `config/database.yaml`), formato (INI/
   JSON/YAML), contenido, y si es secreto (`is_secret`).
2. DADO un Proyecto, CUANDO se listan sus archivos, ENTONCES se
   pueden crear cuantos hagan falta — sin límite artificial impuesto
   por el diseño.
3. DADO un archivo marcado `is_secret`, CUANDO se lista, ENTONCES su
   contenido aparece oculto por defecto, con una acción explícita
   ("Mostrar") para revelarlo — mismo criterio para archivos no
   secretos: siempre visible sin pasos extra.

### Requisito 2 — Validar el contenido contra el formato declarado

**Historia de usuario:** Como administrador, quiero que el sistema
rechace un archivo mal formado antes de guardarlo, para no descubrir
un YAML/JSON/INI roto solo cuando falla un despliegue real.

#### Criterios de aceptación

1. DADO un formato `INI`, CUANDO se guarda el contenido, ENTONCES se
   valida con `parse_ini_string()` — si falla, error claro, no se
   guarda.
2. DADO un formato `JSON`, CUANDO se guarda, ENTONCES se valida con
   `json_decode()` + `json_last_error()` — mismo criterio de rechazo.
3. DADO un formato `YAML`, CUANDO se guarda, ENTONCES se valida con la
   extensión PECL `yaml` — si la extensión no está disponible en el
   servidor, error claro indicando que falta instalarla (nunca un
   fallo silencioso ni un intento de parseo alternativo).

### Requisito 3 — Cifrado en reposo

**Historia de usuario:** Como administrador, quiero que el contenido
de estos archivos esté cifrado en la base de datos, para que un
acceso directo a la BD (sin pasar por la aplicación) no exponga
credenciales de mis clientes en texto plano.

#### Criterios de aceptación

1. DADO cualquier archivo de configuración, CUANDO se guarda en la
   base de datos, ENTONCES su contenido se cifra con AES-256-GCM
   antes de persistir — nunca texto plano en la tabla.
2. DADO un archivo ya guardado, CUANDO se necesita su contenido real
   (mostrarlo en la UI, sincronizarlo a disco), ENTONCES se descifra
   en memoria, nunca se expone la clave de cifrado a la vista ni al
   cliente.
3. DADO que `CONFIG_FILES_ENCRYPTION_KEY` no está configurada o es
   inválida, CUANDO se intenta cifrar o descifrar, ENTONCES falla con
   un error claro — nunca degrada a texto plano silenciosamente.

### Requisito 4 — Sincronizar al disco (manual)

**Historia de usuario:** Como administrador, quiero un botón que
escriba los archivos de configuración de un Proyecto a su directorio
real, para no tener que copiarlos a mano cada vez que cambian.

#### Criterios de aceptación

1. DADO un `Project` con `working_directory` configurado y archivos
   de configuración asociados, CUANDO se dispara "Sincronizar al
   disco", ENTONCES cada archivo se descifra y se escribe en
   `working_directory/{nombre_relativo}`, creando subdirectorios
   intermedios si el nombre incluye ruta (ej. `config/database.yaml`).
2. DADO un `Project` sin `working_directory` configurado, CUANDO se
   intenta sincronizar, ENTONCES falla con un mensaje claro — mismo
   criterio de error limpio ya establecido en `ejecucion-workflows`
   para este mismo campo.
3. DADO un nombre de archivo que intente escapar del
   `working_directory` (ej. `../../etc/passwd`), CUANDO se sincroniza,
   ENTONCES se rechaza — nunca se escribe fuera del directorio del
   proyecto.

## Fuera de alcance

- Conexión automática entre disparo de Workflow y sincronización de
  archivos — v1 es manual, un botón aparte.
- Rotación de la clave de cifrado.
- Formatos adicionales a INI/JSON/YAML.
- Parser YAML propio (sin PECL) — deuda técnica anotada, no resuelta
  aquí.
- Versionado/historial de cambios de un archivo de configuración
  (solo se guarda el valor actual, no un log de versiones anteriores).
- Validación semántica del contenido más allá de "el formato parsea
  correctamente" (ej. no se valida que un YAML de Docker Compose
  tenga la estructura que Docker Compose espera — solo que sea YAML
  válido).