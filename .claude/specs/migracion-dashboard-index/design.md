# Diseño técnico — Migración del Dashboard a `IndexController`

## `activeNavItem()` — el punto técnico real de este spec

`function activeNavItem(AdminController $controller): string` está
tipada específicamente a `AdminController` — si se invoca desde una
vista renderizada por `IndexController`, esto rompe con un error de
tipo antes de llegar a ejecutar nada.

```php
function activeNavItem($controller): string {
    $active = '';

    method_exists($controller, 'GetActiveModel')
        and ($active = $controller->GetActiveModel());

    empty($active) and get_class($controller) === 'App\Controllers\IndexController'
        and ($active = 'dashboard');

    return $active;
}
```

- Si el controlador tiene `GetActiveModel()` (caso `AdminController`,
  ya construido en `AdminBaseTrait`), se usa tal cual — sin cambios
  de comportamiento para nada que ya funcione.
- Si no lo tiene y es `IndexController`, se asume que la única
  "página" real de ese controlador es el Cockpit — valor fijo
  `'dashboard'`.

Comparación en `_sidebar-operational.phtml` para el ítem "Cockpit":
`activeNavItem($this) === 'dashboard'`.

> **Verificar antes de implementar:** confirma que `get_class()`
> devuelve el string exacto `'App\Controllers\IndexController'`
> (namespace completo) — no asumido, depende de cómo se invoque
> `activeNavItem()` desde la vista (con qué objeto real).

## Mover la lógica — sin duplicar

`IndexController::indexAction()`/`healthmetricsAction()` deben ser el
código real que hoy vive en `AdminController`, movido — no una copia
paralela que alguien tenga que mantener sincronizada. Elimina las
versiones de `AdminController` al mover, no las dejes ahí "por si
acaso".

## Constructor de `IndexController`

Debe activar `operationalShell` igual que `AdminController` —
confirma el mecanismo exacto (`$this->operationalShell = true;` +
`$this->helper[] = 'OperationalShell';`, según lo ya establecido en
`dashboard-shell`) y replícalo ahí.

## Redirect de login

En `MainController::loginAction()`, el valor de `$this->loginRedirect`
— cambia de `/admin/index` a `/index/index` (revierte el cambio hecho
en la ronda de "página de aterrizaje post-login").

## Barrida de URLs

```bash
grep -rn "admin/index\|admin/healthmetrics" app/views/ ui-components/
```

Actualiza cada aparición real a `index/index`/`index/healthmetrics` —
no asumas que solo está en un lugar.

## Fuera de alcance de este documento

Ver "Fuera de alcance" en `requirements.md`.