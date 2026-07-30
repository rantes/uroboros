# Commit y push — checkpoint general

## Contexto

El usuario ha estado trabajando en paralelo sobre el mismo repo
mientras se ejecutaban los prompts anteriores (`metricas-oem`,
`dashboard-shell`, `gestion-proyectos`). Este commit **no** se limita
a una lista curada de archivos esperados — a diferencia del commit
anterior del núcleo OEM, aquí se espera que haya cambios que tú no
hiciste. No los trates como anomalías ni te detengas a cuestionarlos
— revísalos para entender qué son, pero el objetivo es commitear el
estado completo actual del working tree, no solo lo tuyo.

## Qué NO hacer (restricciones duras — sin excepción)

- **Nunca** crear un Pull Request, ni con `gh pr create` ni por ningún
  otro medio.
- **Nunca** hacer merge de nada.
- **Nunca** commitear ni pushear directo a `master`/`main`.
- **Nunca** usar `git push --force` ni `git push --force-with-lease`.
- **Nunca** commitear `.env`, `.env.secrets`, `tmp/`, `vendor/` ni
  `app/webroot/dist/` (ver `code-conventions.md`, sección Git) — si
  `git status` los muestra como no ignorados, avísame antes de
  agregarlos, no los incluyas por defecto.
- **Nunca** usar `git commit --no-verify` ni ninguna forma de saltar
  el hook de pre-commit. El proyecto tiene un hook activo que corre
  las pruebas unitarias antes de cada commit — si el hook falla, el
  commit falla, y eso es correcto: significa que hay algo roto que no
  debería commitearse. No lo bypasees para "que pase igual". Repórtame
  el fallo exacto del hook y detente ahí — no soy yo quien decide si
  se ignora un fallo de tests, es una señal real que hay que atender.

## Paso 1 — Ver el estado real, completo

```bash
git status
git branch --show-current
git diff --stat
git diff --stat --staged
```

Repórtame el resultado completo antes de continuar — quiero ver todo
lo que hay, tocado por mí o por ti.

## Paso 2 — Rama

```bash
git branch --show-current
```

- Si ya estás en una rama de feature (no `master`/`main`), continúa
  ahí — no crees una nueva sin necesidad.
- Si estás en `master`/`main`, crea una rama nueva. Dado que este
  checkpoint cubre trabajo de varios specs a la vez (correcciones de
  `metricas-oem`, `dashboard-shell` activado en `AdminController`,
  diseño de `gestion-proyectos`, `README.md`), usa un nombre
  descriptivo del conjunto, por ejemplo
  `feature/metricas-dashboard-checkpoint` — o propone uno mejor si el
  contenido real de los cambios sugiere otro nombre más preciso.
  Repórtame cuál usaste.

## Paso 3 — Revisar antes de agregar

```bash
git status
```

Confirma que no aparece nada de la lista prohibida (`.env`,
`tmp/`, `vendor/`, `app/webroot/dist/`) como cambio no ignorado. Si
aparece algo de eso, detente y pregúntame — no lo agregues ni lo
excluyas por tu cuenta sin confirmar.

## Paso 4 — Commit

```bash
git add -A
git status   # confirma qué quedó staged antes de commitear
```

El `git commit` va a disparar el hook de pre-commit (corre
`dumboTest`) — puede tardar más de lo habitual, es esperado. Si el
hook pasa, el commit se completa normal. Si falla, el commit se
cancela solo — no reintentes con `--no-verify`, repórtame el fallo tal
cual salió.

Commitea con un mensaje descriptivo del conjunto real de cambios —
ajusta el siguiente mensaje a lo que `git status`/`git diff --stat`
te mostraron, no lo uses literal si no coincide:

```bash
git commit -m "checkpoint: métricas OEM, dashboard shell, diseño de gestión de proyectos

- metricas-oem: Buses extienden Controller, OemMetric::Increment()
  sin SQL crudo, _incrementMetric() extraído a OemMetricsTrait
- dashboard-shell: sidebar/topbar/footer, activado en AdminController
- gestion-proyectos: diseño de datos (migraciones, relación
  muchos-a-muchos vía project_groups)
- README.md del proyecto
- Cambios en paralelo del usuario incluidos en este checkpoint"
```

## Paso 5 — Push

```bash
git push -u origin <nombre-de-la-rama-del-paso-2>
```

## Paso 6 — Reportar

```bash
git log --oneline -5
git status
git remote -v
```

Confirma la rama exacta pusheada y la URL del remoto, para que el
usuario abra el PR manualmente cuando esté listo. No hagas nada más
después del push.