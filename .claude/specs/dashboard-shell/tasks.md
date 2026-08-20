# Tareas de implementación — Shell del Dashboard

## Verificaciones previas (no asumidas)

- [ ] 1. `view` el `app/views/layout.phtml` real antes de tocarlo —
      la extensión propuesta en `design.md` es ilustrativa, no un
      reemplazo literal
- [ ] 2. Confirmar qué campos expone el modelo `AppUser` (para el
      avatar del topbar — iniciales, nombre, o placeholder genérico
      si no hay campo de nombre)
- [ ] 3. Confirmar si `.claude/specs/metricas-oem/` ya está
      implementado. Si no, `OperationalShell_Helper::footerMetrics()`
      debe degradar con gracia (Events real, Commands/Reactions en
      `—`) — no bloquear este spec esperando al otro

## Componentes DumboJS nuevos

- [ ] 4. Generar `dmb-operational-topbar` con
      `php ./uibuilder.php generate component dmb-operational-topbar`
      — nunca crear los archivos a mano
- [ ] 5. Generar `dmb-oem-metrics-footer` con el mismo generador
- [ ] 6. Implementar el template/SCSS de ambos según `design.md` —
      `dmb-operational-topbar` sin lógica de búsqueda real (Requisito
      4), solo estructura visual
- [ ] 7. Registrar los imports de ambos componentes en el archivo de
      acción correspondiente (`ui-components/actions/`), sin lógica de
      vista ahí (convención ya existente)

## Vistas y helper

- [ ] 8. Crear `app/views/_sidebar-operational.phtml` según
      `design.md` — reutiliza `dmb-panel.dmb-menu` fijado con `open`,
      sin `dmb-close-panel`
- [ ] 9. Crear `app/views/_widget-empty-state.phtml`
- [ ] 10. Crear `app/helpers/OperationalShell_Helper.php` con
       `activeNavItem()`, `footerMetrics()`, `oemStatus()`
- [ ] 11. Extender `app/views/layout.phtml` con la condicional
       `$this->operationalShell` (ver Paso 1 — solo después de verlo)

## Integración

- [ ] 12. En el/los controlador(es) operativos (los que expone el
       mecanismo genérico de CRUD del usuario para Proyectos, y
       cualquier futuro controlador del dashboard), setear
       `$this->operationalShell = true;` y
       `$this->helper = ['OperationalShell'];` — **coordinar con el
       usuario**, dado que esos controladores no los genera este spec
- [ ] 13. Confirmar visualmente que controladores que NO setean
       `operationalShell` (ej. si existe una sección de contador)
       renderizan exactamente igual que antes — cero regresión visual
       fuera de la sección operativa

## Cierre

- [x] 14. **Confirmado: sí se promueve.** "Eventos" pasa a link real
       en el sidebar — nuevo spec chico `.claude/specs/explorador-eventos/`
       para el listado de solo lectura sobre `Event` (ya existe el
       modelo, es el núcleo OEM el que le da datos).
- [x] 15. **Confirmado: no cerrado del todo.** El Workspace switcher
       descartado queda fuera por ahora, pero es revisitable si
       aparece una razón real de multi-tenant — no una reactivación
       silenciosa, sí un spec nuevo explícito cuando/si llegue ese
       momento. Sin cambios al `Requisito 2.3` de `requirements.md`,
       que ya estaba redactado en ese sentido.

## Hallazgos del recorrido con DumboChromeDriver

- [x] 16. **Resuelto.** CSS del sidebar agregado en
       `dmb-operational-topbar.scss` (decisión: no un componente
       nuevo — consistente con la Decisión 2 de `design.md` y con un
       comentario precedente que ya estaba en el archivo). Usa
       tokens reales de `main.css`. Encontrado de paso (sin corregir,
       fuera de alcance): `dmb-menu.scss` referencia variables CSS
       que no existen en `main.css` (`--primary-contrast`,
       `--secondary`, `--information`).
- [x] 17. **Resuelto — necesitó 3 propiedades, no 1**, descubiertas
       iterando con verificación real, no anticipadas:
       `display: block` (lo pedido) + `position: relative` (sin esto,
       `dmb-content` con `position:absolute; left:0` ignoraba el
       `margin-left` por completo) + `min-height: 100vh` (al volverse
       `#page` contenedor de posicionamiento, también se volvió el
       ancla del `dmb-footer` con `position:absolute; bottom:0`, que
       colapsaba a la altura del topbar sin esto). Verificado con
       `getComputedStyle()` real, no solo visualmente — la celda que
       exponía el bug original pasó de `left:13` (detrás del sidebar)
       a `left:203` (correctamente después). Confirmado que
       `/index/login` (página sin `operationalShell`) sigue sin
       afectarse.
- [ ] 18. Ruta absoluta del servidor expuesta en warnings de PHP —
       recordatorio pre-despliegue, no bloqueante ahora.
- [x] 19. **Resuelto.** "Operaciones" → `/admin/workflow_definitions`,
       "Ejecuciones" → `/admin/workflow_executions`, ambos como links
       reales. `step_executions` sin link propio, según lo decidido.
       Verificado con clicks reales — llegan a las páginas correctas,
       botón "Agregar" visible en Operaciones, ausente en Ejecuciones
       (solo lectura, coherente con el guard). Sin regresión (50/182).

## ✅ Hallazgo resuelto — `activeNavItem()` ya distingue entre páginas

Confirmado empíricamente que `$controller->_model` fallaba igual que
`params` (propiedad `protected` declarada, inaccesible desde función
externa). Resuelto con `AdminBaseTrait::GetActiveModel()` (getter
público nuevo) — `activeNavItem()` ahora recibe `AdminController`
tipado explícitamente y devuelve `GetActiveModel()`. Las 4
comparaciones del sidebar actualizadas. Verificado con
`getComputedStyle()` real en las 4 páginas + `/admin/index` (correctamente
sin ninguna activa). Sin regresión (50/182).

**`dumboTest all`:** 34 tests, 148 assertions — sin cambios, confirma
que fue un cambio puramente de CSS.