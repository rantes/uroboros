// INCORRECTO — viola CSP
<button onclick="document.querySelector('#panel').open()">Abrir</button>

// CORRECTO — siempre dmb-button-action
<dmb-button-action behavior="open-panel" panel="#panel" url="/ruta">
    Abrir
</dmb-button-action>
`````````````````````text

`dmb-simple-form` maneja automáticamente las respuestas del servidor
via `dmb-dialog` — no agregar lógica JS adicional para esto. Si el componente que necesitas existe en DumboJS, úsalo.

---

## Ubicación y nomenclatura

Las vistas viven en `app/views/` organizadas por controlador:

````````````````````text
app/views/
├── layout.phtml                    # Layout principal

├── _header-contents.phtml          # Parcial: head HTML

├── _footer-contents.phtml          # Parcial: scripts al pie

├── _ui_components-templates.phtml  # Parcial: templates de componentes

├── admin/
│   ├── user_list.phtml
│   ├── user_addedit.phtml
│   └── ...
├── guarda/
├── user/
├── contabilidad/
└── index/
```````````````````text

Convención de nombre: `{modelo}_{accion}.phtml` (ej: `user_list.phtml`, `property_addedit.phtml`).

## Sintaxis PHP en vistas

Siempre usar la sintaxis alternativa con `:` y `endif/endforeach/endswitch`. **Nunca usar llaves `{}`** en bloques de control dentro de vistas.

``````````````````php
<?php if ($condicion): ?>
    <p>Contenido</p>
<?php elseif ($otra): ?>
    <p>Otro</p>
<?php else: ?>
    <p>Default</p>
<?php endif; ?>

<?php foreach ($items as $item): ?>
    <li><?= $item->nombre; ?></li>
<?php endforeach; ?>

<?php switch ($valor):
    case 'a': ?>
        <span>A</span>
    <?php break;
    case 'b': ?>
        <span>B</span>
    <?php break;
endswitch; ?>
`````````````````text

Usar `<?` (short open tag) y `<?=` para echo. Las vistas del proyecto usan `<?` sin `php`.

## Variables disponibles en vistas

Las propiedades públicas asignadas en el controlador están disponibles directamente como `$this->propiedad` dentro de la vista, o simplemente como variables locales según el mecanismo de renderizado del framework.

````````````````php
// En el controlador:
$this->sectionTitle = 'Usuarios';
$this->data = $this->User->Find_by_status(1);

// En la vista:
<h2><?= $this->sectionTitle; ?></h2>
<?php foreach ($this->data as $user): ?>
    <p><?= $user->firstname; ?></p>
<?php endforeach; ?>
```````````````text

## Layout principal (`layout.phtml`)

El layout usa Web Components de DumboJS. Estructura base:

``````````````html
<!DOCTYPE html>
<html lang="en">
<head>
    <? require_once(INST_PATH.'app/views/_header-contents.phtml'); ?>
</head>
<body>
<dmb-view class="dmb-view" id="page">
    <header>
        <h1 class="site-logotype">Uroboros - <?=$this->sectionTitle;?></h1>
    </header>
    <dmb-content id="page-content">
        <?=$this->yield;?>
    </dmb-content>
</dmb-view>
<? require_once(INST_PATH.'app/views/_footer-contents.phtml'); ?>
<? require_once(INST_PATH.'app/views/_ui_components-templates.phtml'); ?>
</body>
</html>
`````````````text

`$this->yield` es donde se inyecta el contenido de la vista de la acción.

## Controlar el renderizado desde el controlador

````````````php
// Vista específica con layout
$this->render = ['file' => 'admin/user_list.phtml'];

// Vista específica sin layout (panel/modal)
$this->render = ['file' => 'admin/user_addedit.phtml', 'layout' => false];

// Sin layout
$this->layout = false;

// Texto directo (sin archivo de vista)
$this->render = ['text' => 'contenido'];

// Sin template (acción que solo responde JSON)
$this->noTemplate[] = 'miAccion';
```````````text

## Componentes DumboJS disponibles en vistas

Los componentes se usan como custom HTML elements. Los más comunes en vistas:

``````````html
<!-- Contenedor de página -->
<dmb-view class="dmb-view" id="page"></dmb-view>

<!-- Tarjeta -->
<dmb-card class="full">...</dmb-card>

<!-- Panel lateral (modal/drawer) -->
<dmb-panel id="mi-panel" class="dmb-panel"></dmb-panel>

<!-- Formulario -->
<dmb-form id="mi-form" name="modelo" action="/controller/action" method="post">
    <dmb-input label="Nombre" dmb-name="modelo[nombre]" validate></dmb-input>
    <dmb-select label="Tipo" dmb-name="modelo[tipo_id]" validate></dmb-select>
    <dmb-textarea label="Descripción" dmb-name="modelo[descripcion]"></dmb-textarea>
    <dmb-footer>
        <dmb-button type="reset">Cancelar</dmb-button>
        <dmb-button type="submit">Guardar</dmb-button>
    </dmb-footer>
</dmb-form>

<!-- Tabla -->
<dmb-table>
    <table>
        <thead><tr><th>Nombre</th></tr></thead>
        <tbody>
            <? foreach ($this->data as $row): ?>
            <tr><td><?= $row->nombre; ?></td></tr>
            <? endforeach; ?>
        </tbody>
    </table>
</dmb-table>

<!-- Paginación -->
<dmb-pagination filter-form="#form-search">
    Página: <?= $this->data->WillPaginate($this->fullUrl()); ?>
    de <?= $this->data->PaginateTotalPages; ?>
</dmb-pagination>

<!-- Dock de acciones flotantes -->
<dmb-dock id="admin-dock-options">
    <div class="item" data-title="Agregar">
        <dmb-button-action action="new" panel="#mi-panel"
            url="/admin/entidad/add" behavior="open-panel">
        </dmb-button-action>
    </div>
</dmb-dock>

<!-- Menú lateral -->
<dmb-menu-button menu="#general-menu" legend="Menú Principal"></dmb-menu-button>
<dmb-panel id="general-menu" class="dmb-panel dmb-menu left">...</dmb-panel>

<!-- Diálogo -->
<dmb-dialog></dmb-dialog>

<!-- Botón de acción con comportamientos -->
<dmb-button-action
    action="search"
    panel="#panel-search"
    class="primary"
    behavior="open-panel">
</dmb-button-action>

<dmb-button-action
    icon="delete"
    url="/admin/entidad/1"
    class="danger"
    behavior="launch-url">
    Eliminar
</dmb-button-action>
`````````text

## Grid CSS

El proyecto usa un sistema de grid propio basado en clases `col`:

````````html
<section class="section group">
    <div class="col col6 col6-md col6-sm"><!-- mitad --></div>
    <div class="col col6 col6-md col6-sm"><!-- mitad --></div>
</section>

<section class="section group">
    <div class="col col12"><!-- ancho completo --></div>
</section>

<section class="section group">
    <div class="col col4"><!-- un tercio --></div>
    <div class="col col4"><!-- un tercio --></div>
    <div class="col col4"><!-- un tercio --></div>
</section>
```````text

## Patrón de vista de lista (admin)

``````php
<!-- admin/entidad_list.phtml -->
<dmb-table>
    <table>
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <? foreach ($this->data as $row): ?>
            <tr>
                <td><?= $row->nombre; ?></td>
                <td><?= $this->statuses[$row->status]; ?></td>
                <td>
                    <dmb-more-options>
                        <dmb-button-action
                            action="edit"
                            panel="#panel-form-add-edit-reg"
                            url="/admin/entidades/edit/<?= $row->id; ?>"
                            behavior="open-panel">
                        </dmb-button-action>
                        <dmb-button-action
                            action="delete"
                            url="/admin/entidades/<?= $row->id; ?>"
                            behavior="delete-reg">
                        </dmb-button-action>
                    </dmb-more-options>
                </td>
            </tr>
            <? endforeach; ?>
        </tbody>
    </table>
</dmb-table>
`````text

## Patrón de vista de formulario add/edit (admin)

````php
<!-- admin/entidad_addedit.phtml -->
<dmb-simple-form>
    <dmb-form
        id="form-entidad"
        name="entidad"
        action="/admin/entidades"
        method="post"
        async>
        <header>
            <dmb-close-panel orientation="right"></dmb-close-panel>
            <h3><?= $this->title; ?></h3>
        </header>
        <dmb-content>
            <div class="section group">
                <div class="col col12">
                    <dmb-input
                        label="Nombre"
                        dmb-name="entidad[nombre]"
                        dmb-value="<?= $this->data->nombre; ?>"
                        validate>
                    </dmb-input>
                </div>
            </div>
        </dmb-content>
        <dmb-footer class="section group">
            <div class="col col6">
                <dmb-button type="reset">Cancelar</dmb-button>
            </div>
            <div class="col col6">
                <dmb-button type="submit">Guardar</dmb-button>
            </div>
        </dmb-footer>
    </dmb-form>
</dmb-simple-form>
```text