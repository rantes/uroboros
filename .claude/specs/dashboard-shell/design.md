# Diseño técnico — Shell del Dashboard (Operational Cockpit)

## Decisiones de diseño

1. **Un solo `layout.phtml`, extendido con una bandera condicional.**
   Confirmado por el usuario: no se crean layouts adicionales. Pero
   `layout.phtml` es compartido con secciones no operativas (ej.
   contador — ver `css-conventions.md`, "admin y contador usan layout
   por defecto"). La solución es envolver el sidebar/topbar/footer
   nuevos en una condición sobre una propiedad que el controlador
   decide (`$this->operationalShell = true;`), mismo patrón ya usado
   en el proyecto para variar el layout desde el controlador
   (`$this->layout = false;`, `$this->render = ['layout' => false]`).
   Controladores que no la seteen mantienen el comportamiento actual
   sin cambios.

   > **No implementar a ciegas:** el `layout.phtml` real no está en
   > este contexto. El agente debe `view` el archivo actual antes de
   > editarlo — esto es una extensión quirúrgica (agregar la
   > condicional + los `require_once` de los 3 partials nuevos), no
   > una reescritura.

2. **Sidebar reutiliza `dmb-panel.dmb-menu` existente, fijado abierto**
   — no es un componente nuevo. `dmb-panel` ya soporta el atributo
   `open`; renderizándolo siempre con `open` y sin
   `dmb-close-panel` dentro, se comporta como un sidebar persistente
   en vez de un drawer que se abre/cierra. Es exactamente el caso de
   uso que `views.md` ya documenta (`dmb-panel id="general-menu"
   class="dmb-panel dmb-menu left"`), solo que anclado.

3. **Topbar y footer sí son componentes DumboJS nuevos**, no vistas
   sueltas — `css-conventions.md` es explícito: *"Si se necesita
   encapsular estilos para una sección específica, se deben crear
   componentes... ES TOTALMENTE PROHIBIDO usar los archivos
   `_base.{seccion}.scss`"*. Como no hay componente existente que
   cubra command palette visual + iconos, ni la barra de métricas,
   van en sus propios componentes generados con el generador
   (`php ./uibuilder.php generate component`), nunca a mano.

4. **Command Palette sin JS funcional en v1** (Requisito 4) — el
   componente `dmb-operational-topbar` incluye el input visualmente,
   pero sin lógica de búsqueda. Se genera igual con el generador
   porque encapsula estilo propio, aunque su `directive.js` quede casi
   vacío por ahora.

5. **Estados vacíos de widgets reutilizan `dmb-card` existente** — no
   se crea un componente nuevo para esto. `dmb-card` ya es genérico y
   extensible; el estado "Próximamente" se resuelve con una clase
   modificadora + contenido condicional en PHP, no con JS ni con un
   nuevo Custom Element.

## Estructura de archivos nueva

```text
app/views/
├── _sidebar-operational.phtml   # incluye dmb-panel.dmb-menu con nav
├── _widget-empty-state.phtml    # partial parametrizado (ver abajo)

ui-components/components/
├── dmb-operational-topbar/
│   ├── dmb-operational-topbar.directive.js
│   ├── dmb-operational-topbar.html
│   ├── dmb-operational-topbar.scss
│   └── dmb-operational-topbar.test.js
└── dmb-oem-metrics-footer/
    ├── dmb-oem-metrics-footer.directive.js
    ├── dmb-oem-metrics-footer.html
    ├── dmb-oem-metrics-footer.scss
    └── dmb-oem-metrics-footer.test.js

app/helpers/
└── OperationalShell_Helper.php  # arma los datos que layout.phtml necesita
```

## `OperationalShell_Helper`

Se carga solo en controladores que activan el shell
(`$this->helper = ['OperationalShell'];`). Responsabilidades:

```php
// Sidebar: qué item está activo según el controlador actual
public function activeNavItem(): string { /* ... */ }

// Footer: métricas agregadas de metricas-oem (ver ese spec)
public function footerMetrics(int $hoursWindow = 6): array {
    // SUM(count) GROUP BY metric_type vía OemMetric, más
    // COUNT(id) directo sobre Event para "Events (Nh)"
}

// Footer: ¿la última escritura a events fue reciente?
public function oemStatus(): string {
    // MAX(created_at) de Event vs. umbral — 'healthy' | 'stale' | 'unknown'
}
```

> Nota: `footerMetrics()` depende de que `.claude/specs/metricas-oem/`
> esté implementado. Si no lo está aún, debe degradar con gracia —
> mostrar `Events (Nh)` (sí disponible hoy vía `Event` directo) y
> ocultar o poner "—" en Commands/Reactions, nunca un error o un cero
> falso que parezca dato real.

## Sidebar — `_sidebar-operational.phtml`

```php
<dmb-panel id="operational-sidebar" class="dmb-panel dmb-menu left" open>
    <nav>
        <div class="nav-group">
            <span class="nav-group-title">Explorar</span>
            <a href="/admin/proyectos" class="<?= $this->activeNavItem() === 'proyectos' ? 'active' : ''; ?>">
                Proyectos
            </a>
            <span class="nav-item disabled" title="Requiere Workflow Execution">Operaciones</span>
            <span class="nav-item disabled" title="Requiere Workflow Execution">Eventos</span>
            <span class="nav-item disabled" title="Requiere Health Management">Recomendaciones</span>
        </div>
        <div class="nav-group">
            <span class="nav-group-title">Analizar</span>
            <span class="nav-item disabled" title="Requiere Health Management">Salud</span>
            <span class="nav-item disabled" title="Requiere Dependency Management">Riesgos</span>
            <span class="nav-item disabled" title="Requiere Health Management">Métricas</span>
            <span class="nav-item disabled" title="Requiere Event Management">Trazabilidad</span>
        </div>
        <div class="nav-group">
            <span class="nav-group-title">Configurar</span>
            <span class="nav-item disabled" title="Próximamente">Integraciones</span>
            <span class="nav-item disabled" title="Próximamente">Políticas</span>
            <span class="nav-item disabled" title="Próximamente">Ajustes</span>
        </div>
    </nav>
</dmb-panel>
```

> Nota aparte, no decidida aquí: "Eventos" (explorador crudo de la
> tabla `events`) es, a diferencia de los demás ítems marcados
> "próximamente", técnicamente construible *hoy* — el Event Store ya
> existe. Lo dejo como `disabled` porque no estaba en el alcance
> confirmado de este spec, no porque falte infraestructura. Si lo
> quieres promover a real, es un ajuste chico, no un spec nuevo.

## Topbar — `dmb-operational-topbar`

Contenido (vía `template` del componente, siguiendo
`dumbojs-components.md`): input de command palette (placeholder
`"Command Palette... (ej: deploy production)"`, sin `dmb-name` ni
`validate` — no es un form real), e iconos de ayuda y usuario. **Sin
icono de notificaciones** — Requisito de `dashboard-shell` lo excluye
explícitamente (no hay dominio que genere notificaciones todavía); no
se muestra un ícono con badge falso.

Avatar de usuario: placeholder con iniciales genéricas hasta confirmar
qué campos expone `AppUser` — no asumido, ver `tasks.md`.

## Footer — `dmb-oem-metrics-footer`

Recibe los datos ya calculados por `OperationalShell_Helper` como
atributos `data-*` (mismo patrón que `dmb-donut-chart` con
`data-percent`), el componente solo se encarga de presentación:

```php
<dmb-oem-metrics-footer
    data-events="<?= $this->footerMetrics()['events']; ?>"
    data-commands-dispatched="<?= $this->footerMetrics()['command_dispatched'] ?? '—'; ?>"
    data-commands-failed="<?= $this->footerMetrics()['command_failed'] ?? '—'; ?>"
    data-reactions-executed="<?= $this->footerMetrics()['reaction_executed'] ?? '—'; ?>"
    data-reactions-failed="<?= $this->footerMetrics()['reaction_failed'] ?? '—'; ?>"
    data-oem-status="<?= $this->oemStatus(); ?>">
</dmb-oem-metrics-footer>
```

## Grid de widgets — `_widget-empty-state.phtml`

Partial parametrizado por convención de scope de PHP (variables
seteadas antes del `include`, sin mecanismo especial):

```php
<!-- Uso en cualquier vista del grid: -->
<? $widgetTitle = 'Operational Health'; $widgetReason = 'Requiere Health Management'; ?>
<? include(INST_PATH.'app/views/_widget-empty-state.phtml'); ?>
```

```php
<!-- _widget-empty-state.phtml -->
<dmb-card class="widget-empty">
    <h3><?= $widgetTitle; ?></h3>
    <p class="widget-empty-reason">Próximamente — <?= $widgetReason; ?></p>
</dmb-card>
```

Satisface Requisito 5.2 (nunca datos simulados) sin componente nuevo.

## `layout.phtml` — extensión condicional

```php
<? if ($this->operationalShell ?? false): ?>
    <? require_once(INST_PATH.'app/views/_sidebar-operational.phtml'); ?>
<? endif; ?>
<dmb-view class="dmb-view" id="page">
    <? if ($this->operationalShell ?? false): ?>
        <dmb-operational-topbar></dmb-operational-topbar>
    <? endif; ?>
    <dmb-content id="page-content">
        <?=$this->yield;?>
    </dmb-content>
    <? if ($this->operationalShell ?? false): ?>
        <dmb-oem-metrics-footer ...></dmb-oem-metrics-footer>
    <? endif; ?>
</dmb-view>
```

Estructura ilustrativa — el agente debe adaptarla a la estructura real
existente del archivo, no reemplazar el archivo completo.

## CSS

Todo estilo de sidebar/topbar/footer en sus propios `.scss` de
componente (`dmb-operational-topbar.scss`, `dmb-oem-metrics-footer.scss`,
y overrides de `dmb-menu`/`dmb-panel` si el ancho fijo del sidebar
persistente lo requiere — en el `.scss` de esos componentes, nunca en
`_base.*.scss`). Usar variables de `main.css` para toda paleta y
tipografía, unidades `em` para dimensiones (ver `css-conventions.md`).

## Fuera de alcance de este documento

Ver "Fuera de alcance" en `requirements.md`.