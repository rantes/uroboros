# Tareas de implementación — Migración del Dashboard a `IndexController`

## Verificación previa

- [ ] 1. Confirmar que `get_class($controller)` devuelve el string
      exacto esperado para `IndexController` en el contexto real de
      invocación desde la vista — no asumido.

## Implementación

- [ ] 2. Mover `indexAction()` y `healthmetricsAction()` de
      `AdminController` a `IndexController` — código real movido, sin
      duplicar, eliminado de `AdminController`.
- [ ] 3. `IndexController`: activar `operationalShell` en el
      constructor, mismo mecanismo que `AdminController`.
- [ ] 4. Corregir `activeNavItem()` según `design.md` — sin el type
      hint estricto a `AdminController`, con el fallback a
      `'dashboard'` para `IndexController`.
- [ ] 5. Actualizar la comparación del ítem "Cockpit" en
      `_sidebar-operational.phtml`.
- [ ] 6. `MainController::loginAction()`: `$this->loginRedirect` de
      vuelta a `index/index`.
- [ ] 7. Barrida completa de `admin/index`/`admin/healthmetrics` en
      vistas/JS — actualizar cada aparición real.

## Verificación con DumboChromeDriver

- [ ] 8. Login real → confirma que aterriza en `index/index`, con el
      Cockpit real (Operational Health, Active Operations, footer)
      funcionando igual que antes de moverlo.
- [ ] 9. Confirma que "Cockpit" se resalta como activo en el sidebar
      únicamente en `index/index`, y que el resto de ítems (Proyectos,
      Operaciones, Ejecuciones, Eventos) siguen resaltando
      correctamente en sus propias páginas — sin regresión sobre el
      fix de `activeNavItem()` de rondas anteriores.
- [ ] 10. Confirma que `/admin/index` ya no sirve el dashboard (debe
       dar 404 o redirigir, según cómo quede tras remover la acción de
       `AdminController` — confirma cuál de las dos pasa realmente, no
       asumas).
- [ ] 11. Cambiar la ventana del widget de salud (7d/30d/90d) —
       confirma que sigue funcionando desde la nueva URL
       (`index/healthmetrics`).

## Regresión

- [ ] 12. `dumboTest all` — conteo del nodo raíz de `test-result.xml`,
       cero regresión sobre la línea base actual.