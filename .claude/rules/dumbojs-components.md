<!-- INCORRECTO — viola CSP -->
<button onclick="document.querySelector('#panel').open()">Abrir</button>
<script>document.addEventListener('click', ...);</script>
```````````````````````````````````````````````````````````````````````````````````````````````````````text

**Correcto — usar siempre `dmb-button-action`:**

``````````````````````````````````````````````````````````````````````````````````````````````````````html
<!-- CORRECTO -->
<dmb-button-action behavior="open-panel" panel="#panel" url="/ruta">
    Abrir
</dmb-button-action>
`````````````````````````````````````````````````````````````````````````````````````````````````````text

### Nunca `<button>` nativo con `onclick`

Usar el componente correcto según el caso de uso — consultar esta documentación:

- `dmb-button` — dentro de `dmb-form` con `type="submit"` o `type="reset"`
- `dmb-button-action` — acciones con `behavior` (open-panel, launch-url, ajax)

Lo prohibido es `<button onclick="...">` nativo — viola CSP y rompe
la arquitectura de componentes.

### División de responsabilidades

- **DumboPHP** maneja el flujo del servidor — controladores, modelos, rutas

- **DumboJS** gestiona el comportamiento de los componentes internamente

- **`dmb-simple-form`** maneja el flujo del formulario y muestra respuestas
  del servidor via `dmb-dialog` automáticamente — no agregar lógica JS extra

- Los archivos de acción (`guarda.js`, `admin.js`, etc.) solo registran
  imports de componentes — no contienen lógica de vista

### CSS — encapsulamiento por componente

Los estilos de un componente van en el SCSS del propio componente.
Nunca poner estilos de un componente en `_base.guard.scss` u otros
archivos globales. Los archivos `_base.*.scss` son para estilos de
layout y estructura de sección, no para estilos de componentes.

### `dmb-select` — solo `<option>` estándar con PHP

`dmb-select` recibe únicamente `<option>` generados con PHP con `value` y texto.
No agregar atributos no documentados al tag `dmb-select` ni a sus `<option>`.
Consultar la documentación del componente para los atributos soportados.

````````````````````````````````````````````````````````````````````````````````````````````````````html
<!-- CORRECTO -->
<dmb-select label="Unidad" dmb-name="reception[property_id]" validate="required">
    <? foreach($this->properties as $p): ?>
    <option value="<?=$p->id;?>"><?=$p->name;?></option>
    <? endforeach; ?>
</dmb-select>
```````````````````````````````````````````````````````````````````````````````````````````````````text

### Componentes nuevos — solo cuando es necesario

Crear un componente nuevo solo cuando encapsula comportamiento JS
reutilizable que no puede resolverse con componentes existentes.
No crear componentes para lógica que `dmb-simple-form` o
`dmb-living-division-resident-select` ya manejan.

Crear con el generador siempre:

``````````````````````````````````````````````````````````````````````````````````````````````````bash
php ./uibuilder.php generate component dmb-nombre-componente
`````````````````````````````````````````````````````````````````````````````````````````````````text

---

## Arquitectura

DumboJS es un motor de Web Components nativos. La clase base es `DumboDirective` (de `ui-components/libs/dumbojs/dumbo.min.js`). Cada componente es un Custom Element registrado con un selector HTML.

## Estructura de un componente

````````````````````````````````````````````````````````````````````````````````````````````````text
ui-components/components/dmb-mi-componente/
├── dmb-mi-componente.directive.js   # Lógica del componente

├── dmb-mi-componente.test.js        # Tests unitarios (Jasmine)

├── dmb-mi-componente.html           # Template HTML (si usa templateUrl)

└── dmb-mi-componente.scss           # Estilos

```````````````````````````````````````````````````````````````````````````````````````````````text

## Clase base de un componente nuevo

``````````````````````````````````````````````````````````````````````````````````````````````js
import { DumboDirective } from "../../libs/dumbojs/dumbo.min.js";

export class DmbMiComponente extends DumboDirective {
    static selector = 'dmb-mi-componente';
    static template = '<div transclude></div>';

    static get observedAttributes() {
        return ['mi-atributo'];
    }

    #_estado = null;

    init() {
        this.#_estado = this.getAttribute('mi-atributo');
    }

    attributeChangedCallback(attr, oldValue, newValue) {
        switch (attr) {
            case 'mi-atributo':
                if (oldValue) this.#_estado = newValue;
                break;
        }
    }
}
`````````````````````````````````````````````````````````````````````````````````````````````text

**Reglas del patrón:**

- `static selector` define el tag HTML

- `init()` es el punto de entrada (equivale a `connectedCallback`)

- Campos privados con `#` para estado interno

- `static get observedAttributes()` para atributos reactivos

- `transclude` en el template indica dónde se proyecta el contenido hijo

---

## Referencia completa de componentes

### dmb-view

Contenedor estructural genérico, equivalente a un `div` semántico. Sin lógica propia.

````````````````````````````````````````````````````````````````````````````````````````````html
<dmb-view class="dmb-view" id="page">
    <!-- contenido -->
</dmb-view>
```````````````````````````````````````````````````````````````````````````````````````````text

---

### dmb-content

Área de contenido semántica. Sin lógica propia.

``````````````````````````````````````````````````````````````````````````````````````````html
<dmb-content id="page-content">
    <?=$this->yield;?>
</dmb-content>
`````````````````````````````````````````````````````````````````````````````````````````text

---

### dmb-header

Cabecera semántica. Sin lógica propia.

````````````````````````````````````````````````````````````````````````````````````````html
<dmb-header>
    <h1>Título</h1>
</dmb-header>
```````````````````````````````````````````````````````````````````````````````````````text

---

### dmb-footer

Pie de página semántico. Sin lógica propia.

``````````````````````````````````````````````````````````````````````````````````````html
<dmb-footer>
    <!-- botones de acción del formulario -->
</dmb-footer>
`````````````````````````````````````````````````````````````````````````````````````text

---

### dmb-card

Tarjeta contenedora con comportamiento de clic. Extiende `dmb-button-action`, soporta los mismos atributos `behavior`, `url`, `panel`.

**Clases modificadoras**: `full` (ancho completo), `with-icon`, `with-button`.

````````````````````````````````````````````````````````````````````````````````````html
<dmb-card class="full" behavior="launch-url" url="/admin/index">
    <h3>Título</h3>
    <p>Descripción</p>
</dmb-card>

<!-- Tarjeta navegable -->
<dmb-card behavior="open-panel" panel="#mi-panel" url="/admin/entidad/edit/1">
    <span>Contenido</span>
</dmb-card>
```````````````````````````````````````````````````````````````````````````````````text

---

### dmb-panel

Panel lateral (drawer) que puede cargarse desde una URL externa. Se abre/cierra programáticamente.

**Atributos:**

- `source` — URL para cargar contenido remoto vía fetch (reactivo)

- `open` — presencia del atributo indica que está abierto

- `class="right|left"` — posición (default: `right`)

- `class="small|large"` — tamaño (default: `small`)

**Métodos JS:**

- `panel.open()` — abre el panel y carga `source` si está definido

- `panel.close(value?)` — cierra y emite evento `panelClose`

- `panel.onClose(fn)` — callback al cerrar

- `panel.loadExternalSource()` — recarga el contenido remoto

**Eventos emitidos:** `panelClose`, `panelOpened`, `panelClosed`, `dialogClose`

``````````````````````````````````````````````````````````````````````````````````html
<dmb-panel id="panel-form" class="dmb-panel right small">
    <!-- contenido estático o se carga por source -->
</dmb-panel>

<!-- Abrir programáticamente desde JS -->
<script>
document.querySelector('#panel-form').setAttribute('source', '/admin/entidad/edit/1');
document.querySelector('#panel-form').open();
</script>
`````````````````````````````````````````````````````````````````````````````````text

---

### dmb-close-panel

Botón de cierre de panel. Debe estar dentro de un `dmb-panel`.

**Atributos:**

- `orientation` — `right` (default) o `left`

````````````````````````````````````````````````````````````````````````````````html
<dmb-close-panel orientation="right"></dmb-close-panel>
```````````````````````````````````````````````````````````````````````````````text

---

### dmb-dialog

Diálogo/modal. Similar a `dmb-panel` pero se comporta como overlay.

**Atributos:**

- `open` — presencia indica abierto (reactivo)

- `delay` — segundos antes de abrirse automáticamente (default: 0)

- `no-close` — omite el botón de cierre automático

- `no-auto-open` — evita que se abra solo al montar

- `class="loader"` — variante sin botón de cierre (para loaders)

**Métodos JS:**

- `dialog.open()` — abre

- `dialog.close(value?, remove?)` — cierra; si `remove=true` se elimina del DOM

- `dialog.showModal()` — abre con soporte de botones `[type="modal-answer"]`

- `dialog.error(msg)` — muestra como diálogo de error

- `dialog.info(msg)` — muestra como diálogo informativo

- `dialog.onClose(fn)` — callback al cerrar

**Eventos emitidos:** `close`, `close-dialog`, evento de `DmbEvents.dialogOpen`

``````````````````````````````````````````````````````````````````````````````html
<dmb-dialog id="mi-dialog" no-auto-open>
    <div class="wrapper">
        <p>Mensaje de confirmación</p>
        <button type="modal-answer" value="ok">Aceptar</button>
        <button type="modal-answer" value="cancel">Cancelar</button>
    </div>
</dmb-dialog>
`````````````````````````````````````````````````````````````````````````````text

---

### dmb-page-loader

Overlay de carga de página. Se activa automáticamente en `beforeunload` y se cierra en `load`. Usar `#page-loader` como id convencional.

**Métodos JS:**

- `pageLoader.open()` — muestra el loader

- `pageLoader.close()` — oculta el loader

````````````````````````````````````````````````````````````````````````````html
<dmb-page-loader id="page-loader"></dmb-page-loader>
```````````````````````````````````````````````````````````````````````````text

---

### dmb-notification

Notificación descartable. Incluye botón de cierre integrado.

``````````````````````````````````````````````````````````````````````````html
<dmb-notification>
    <p>Operación completada con éxito.</p>
</dmb-notification>
`````````````````````````````````````````````````````````````````````````text

---

### dmb-menu

Menú de navegación. Puede usarse como panel lateral con clase `.dmb-menu`.

````````````````````````````````````````````````````````````````````````html
<dmb-panel id="general-menu" class="dmb-panel dmb-menu left">
    <nav>
        <a href="/admin/index">Dashboard</a>
        <a href="/admin/users">Usuarios</a>
    </nav>
</dmb-panel>
```````````````````````````````````````````````````````````````````````text

---

### dmb-menu-button

Botón que abre un `dmb-panel` como menú al hacer clic.

**Atributos:**

- `menu` — selector CSS del panel a abrir (requerido)

- `legend` — texto descriptivo

``````````````````````````````````````````````````````````````````````html
<dmb-menu-button menu="#general-menu" legend="Menú Principal"></dmb-menu-button>
`````````````````````````````````````````````````````````````````````text

---

### dmb-dock

Contenedor flotante de acciones. Los `.item` dentro tienen efecto visual al hacer clic.

````````````````````````````````````````````````````````````````````html
<dmb-dock id="admin-dock-options">
    <div class="item" data-title="Agregar">
        <dmb-button-action
            action="new"
            panel="#panel-form-add-edit-reg"
            url="/admin/entidad/add"
            behavior="open-panel">
        </dmb-button-action>
    </div>
    <div class="item" data-title="Buscar">
        <dmb-button-action
            action="search"
            panel="#panel-search"
            behavior="open-panel">
        </dmb-button-action>
    </div>
</dmb-dock>
```````````````````````````````````````````````````````````````````text

---

### dmb-button

Botón de formulario. Delega `submit` y `reset` al `dmb-form` contenedor.

**Atributos:**

- `type` — `submit` (ejecuta validación y envío) o `reset` (resetea el formulario)

**Método JS:**

- `button.click(fn)` — reemplaza el handler de clic por defecto

``````````````````````````````````````````````````````````````````html
<dmb-button type="submit" class="primary">Guardar</dmb-button>
<dmb-button type="reset">Cancelar</dmb-button>
`````````````````````````````````````````````````````````````````text

---

### dmb-button-action

Botón con comportamiento declarativo. Icono automático según `action`.

**Atributos:**

- `action` — `edit`, `delete`, `new`, `search`, `execute`, `upload` (determina el icono automático)

- `icon` — icono manual (material icon name), sobreescribe el de `action`

- `behavior` — comportamiento al hacer clic (requerido):
  - `open-panel` — abre el panel indicado en `panel`, opcionalmente cargando `url`
  - `launch-url` — navega a `url`
  - `ajax` — hace fetch a `url` (DELETE si `action=delete`) y recarga la página
  - `exec-form` — envía el formulario indicado en `form`
  - `download-file` — abre `url` en nueva pestaña

- `url` — URL destino según el `behavior`

- `panel` — selector CSS del panel (para `behavior=open-panel`)

- `form` — selector CSS del formulario (para `behavior=exec-form`)

- `target` — atributo target para `exec-form`

````````````````````````````````````````````````````````````````html
<!-- Abrir panel de edición cargando contenido remoto -->
<dmb-button-action
    action="edit"
    panel="#panel-form-add-edit-reg"
    url="/admin/entidad/edit/<?= $row->id; ?>"
    behavior="open-panel">
</dmb-button-action>

<!-- Eliminar con confirmación automática -->
<dmb-button-action
    action="delete"
    url="/admin/entidad/<?= $row->id; ?>"
    behavior="ajax">
</dmb-button-action>

<!-- Navegar a URL -->
<dmb-button-action
    icon="download"
    url="/admin/entidad/export/<?= $row->id; ?>"
    behavior="download-file">
    Exportar
</dmb-button-action>
```````````````````````````````````````````````````````````````text

---

### dmb-form

Formulario con validación integrada. Wrapper semántico sobre `<form>`.

**Atributos:**

- `action` — URL del endpoint (reactivo vía `attributeChangedCallback`)

- `method` — `GET` o `POST` (default: `POST`)

- `dmb-name` — name del form HTML interno

- `dmb-id` — id del form HTML interno

- `target` — target del form

- `enctype` — enctype (default: `application/x-www-form-urlencoded`)

- `autocomplete` — `on`/`off`

- `async` — presencia del atributo activa modo asíncrono (usa `callback` en lugar de submit nativo)

**Métodos JS:**

- `form.submit()` — valida y envía (o llama `callback` si es async)

- `form.reset()` — limpia el formulario

- `form.validateForm()` — valida todos los campos con `validate`

- `form.getFormData()` — retorna `FormData`

- `form.setFormData(data)` — llena campos desde un objeto

- `form.callback = fn` — función a llamar en submit async (recibe el form)

**Eventos emitidos:** `formBeforeValidate`, `formAfterValidate`, `formSubmit`, `submit`

**Validación:** `dmb-form` valida automáticamente todos los `dmb-input[validate]`, `dmb-select[validate]` y `dmb-textarea[validate]` al hacer submit.

``````````````````````````````````````````````````````````````html
<dmb-form
    id="form-entidad"
    name="entidad"
    action="/admin/entidades"
    method="post"
    async>
    <header>
        <dmb-close-panel orientation="right"></dmb-close-panel>
        <h3>Agregar entidad</h3>
    </header>
    <dmb-content>
        <dmb-input label="Nombre" dmb-name="entidad[nombre]" validate></dmb-input>
    </dmb-content>
    <dmb-footer>
        <dmb-button type="reset">Cancelar</dmb-button>
        <dmb-button type="submit">Guardar</dmb-button>
    </dmb-footer>
</dmb-form>
`````````````````````````````````````````````````````````````text

---

### dmb-simple-form

Wrapper de `dmb-form` que conecta automáticamente con `appModel` para CRUD.

**Atributos:**

- `update` — presencia indica que es actualización (usa `updateData`); sin él, crea (usa `createData`)

- `redirect` — URL de redirección tras guardar

- `close-panel` — selector CSS del panel a cerrar tras guardar

El `action` del `dmb-form` interno es la URL del endpoint.

````````````````````````````````````````````````````````````html
<!-- Crear registro -->
<dmb-simple-form redirect="/admin/entidades">
    <dmb-form action="/admin/entidades" method="post" async>
        <dmb-input label="Nombre" dmb-name="entidad[nombre]" validate></dmb-input>
        <dmb-footer>
            <dmb-button type="reset">Cancelar</dmb-button>
            <dmb-button type="submit">Guardar</dmb-button>
        </dmb-footer>
    </dmb-form>
</dmb-simple-form>

<!-- Actualizar registro -->
<dmb-simple-form update redirect="/admin/entidades" close-panel="#panel-form">
    <dmb-form action="/admin/entidades" method="post" async>
        <dmb-input type="hidden" dmb-name="entidad[id]" dmb-value="<?= $this->data->id; ?>"></dmb-input>
        <dmb-input label="Nombre" dmb-name="entidad[nombre]" dmb-value="<?= $this->data->nombre; ?>" validate></dmb-input>
        <dmb-footer>
            <dmb-button type="reset">Cancelar</dmb-button>
            <dmb-button type="submit">Guardar</dmb-button>
        </dmb-footer>
    </dmb-form>
</dmb-simple-form>
```````````````````````````````````````````````````````````text

---

### dmb-input

Campo de texto con label, validación y máscaras de entrada.

**Atributos:**

- `label` — texto del label y placeholder (reactivo)

- `dmb-name` — name del input interno (reactivo)

- `dmb-value` — valor inicial (reactivo)

- `dmb-id` — id del input interno

- `dmb-class` — clases del input interno

- `type` — tipo del input: `text` (default), `hidden`, `checkbox`, `email`, `number`, `file`, `password`, etc.

- `validate` — validaciones separadas por coma: `required`, `email`, `numeric`, `min:N`, `max:N` (reactivo)

- `pattern` — atributo pattern del input nativo

- `placeholder` — placeholder (si difiere del label)

- `step` — step para inputs numéricos

- `masked` — `alpha`, `numeric`, `uppercase` (restringe entrada por teclado)

- `autocomplete` — `on`/`off`

- `accept` — para `type=file`, tipos MIME aceptados

- `checked` — para `type=checkbox`, estado inicial marcado

``````````````````````````````````````````````````````````html
<!-- Campo de texto con validación requerida -->
<dmb-input
    label="Nombre"
    dmb-name="entidad[nombre]"
    dmb-value="<?= $this->data->nombre; ?>"
    validate="required">
</dmb-input>

<!-- Campo de email -->
<dmb-input
    label="Email"
    dmb-name="entidad[email]"
    type="email"
    validate="required,email">
</dmb-input>

<!-- Campo numérico con máscara -->
<dmb-input
    label="Teléfono"
    dmb-name="entidad[telefono]"
    masked="numeric"
    validate="required,min:7">
</dmb-input>

<!-- Campo hidden -->
<dmb-input type="hidden" dmb-name="entidad[id]" dmb-value="<?= $this->data->id; ?>"></dmb-input>

<!-- Checkbox -->
<dmb-input
    type="checkbox"
    label="Activo"
    dmb-name="entidad[activo]"
    dmb-value="1">
</dmb-input>
`````````````````````````````````````````````````````````text

---

### dmb-select

Campo select con label, opciones dinámicas y validación.

**Atributos:**

- `label` — texto del label (reactivo)

- `dmb-name` — name del select (reactivo)

- `dmb-value` — valor preseleccionado; para `multiple`, JSON array (reactivo)

- `dmb-id` — id del select

- `dmb-class` — clases del select

- `validate` — validaciones: `required` (reactivo)

- `multiple` — presencia activa selección múltiple

- `values` — JSON array para poblar opciones dinámicamente: `[{value:"1",text:"Opción"}]` (reactivo)

**Propiedad JS:**

- `select.values = [{value, text, selected?}]` — pobla las opciones

- `select.value = val` — establece el valor seleccionado

**Eventos emitidos:** `inputChanged`, `change`

````````````````````````````````````````````````````````html
<!-- Select con opciones estáticas en HTML -->
<dmb-select label="Estado" dmb-name="entidad[status]" dmb-value="<?= $this->data->status; ?>" validate="required">
    <option value="">Seleccione...</option>
    <option value="1">Activo</option>
    <option value="0">Inactivo</option>
</dmb-select>

<!-- Select que se puebla dinámicamente desde JS -->
<dmb-select id="mi-select" label="Categoría" dmb-name="entidad[categoria_id]" validate="required">
    <option value="">Seleccione...</option>
</dmb-select>

<!-- Desde JS: -->
<!-- document.querySelector('#mi-select').values = [{value:'1',text:'Cat A'},{value:'2',text:'Cat B'}]; -->
```````````````````````````````````````````````````````text

---

### dmb-textarea

Área de texto con label, validación y máscaras.

**Atributos:**

- `label` — texto del label

- `dmb-name` — name del textarea (reactivo)

- `placeholder` — placeholder

- `validate` — validaciones: `required`, `numeric`, `min:N`, `max:N` (reactivo)

- `masked` — `alpha`, `numeric`, `uppercase`

- `autocomplete` — `on`/`off`

- `dmb-class` — clases del textarea

- `dmb-id` — id del textarea

``````````````````````````````````````````````````````html
<dmb-textarea
    label="Descripción"
    dmb-name="entidad[descripcion]"
    validate="required">
</dmb-textarea>
`````````````````````````````````````````````````````text

---

### dmb-toggle

Toggle switch (on/off). Estado interno `0`/`1`.

**Atributos:**

- `value` — valor inicial: `0` (off) o `1` (on) (reactivo)

**Propiedad JS:**

- `toggle.value` — getter/setter del estado actual

**Método JS:**

- `toggle.click(fn)` — agrega handler de clic personalizado

````````````````````````````````````````````````````html
<dmb-toggle value="<?= $this->data->activo; ?>"></dmb-toggle>
```````````````````````````````````````````````````text

---

### dmb-wysiwyg

Editor de texto enriquecido con barra de herramientas de formato.

**Atributos:**

- `dmb-name` — name del campo oculto que contiene el HTML (reactivo)

- `validate` — `required` (reactivo)

Usa `templateUrl` (carga `dmb-wysiwyg.html`). Debe estar dentro de un `dmb-form`.

``````````````````````````````````````````````````html
<dmb-wysiwyg
    dmb-name="contenido[cuerpo]"
    validate="required">
</dmb-wysiwyg>
`````````````````````````````````````````````````text

---

### dmb-table

Wrapper semántico para tablas de datos. Sin lógica propia, aplica estilos.

````````````````````````````````````````````````html
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
                <td><?= $row->status; ?></td>
                <td>
                    <dmb-more-options>
                        <dmb-more-option behavior="open-panel"
                            url="/admin/entidad/edit/<?= $row->id; ?>"
                            panel="#panel-form-add-edit-reg">
                            Editar
                        </dmb-more-option>
                        <dmb-more-option behavior="ajax"
                            url="/admin/entidad/delete/<?= $row->id; ?>">
                            Eliminar
                        </dmb-more-option>
                    </dmb-more-options>
                </td>
            </tr>
            <? endforeach; ?>
        </tbody>
    </table>
</dmb-table>
```````````````````````````````````````````````text

---

### dmb-pagination

Paginación que puede integrarse con un formulario de búsqueda para paginar por POST.

**Atributos:**

- `filter-form` — selector CSS del formulario de filtro (si se pagina via POST)

El contenido interno usa `$this->data->WillPaginate($this->fullUrl())` de DumboPHP para generar los links.

``````````````````````````````````````````````html
<dmb-pagination filter-form="#form-search">
    Página: <?= $this->data->WillPaginate($this->fullUrl()); ?>
    de <?= $this->data->PaginateTotalPages; ?>
</dmb-pagination>
`````````````````````````````````````````````text

---

### dmb-search

Barra de búsqueda con formulario POST integrado. Genera campos hidden con los nombres de columna a buscar.

**Atributos:**

- `fields` — columnas a buscar, separadas por coma (requerido): `"nombre,descripcion"`

- `action` — URL del endpoint de búsqueda (requerido)

- `label` — placeholder del input de búsqueda

- `dmb-value` — valor inicial del término de búsqueda

- `dmb-id` — id del formulario interno

````````````````````````````````````````````html
<dmb-search
    fields="nombre,descripcion"
    action="/admin/entidades"
    label="Buscar..."
    dmb-id="form-search"
    dmb-value="<?= $this->searchTerm ?? ''; ?>">
</dmb-search>
```````````````````````````````````````````text

---

### dmb-search-popup

Búsqueda con popup en panel. El resultado se carga en el panel indicado.

**Atributos:**

- `panel` — selector CSS del panel donde se mostrarán los resultados (requerido)

- `data-action` — URL base del endpoint de búsqueda (requerido, vía `dataset`)

``````````````````````````````````````````html
<dmb-search-popup panel="#panel-results" data-action="/admin/entidades/search">
</dmb-search-popup>
`````````````````````````````````````````text

---

### dmb-autocomplete

Componente de autocompletado. Ver directivo para detalles de implementación específica.

````````````````````````````````````````html
<dmb-autocomplete></dmb-autocomplete>
```````````````````````````````````````text

---

### dmb-more-options

Menú contextual de tres puntos verticales. Agrupa `dmb-more-option` y los muestra al hacer clic.

**Método JS:**

- `moreOptions.setOptions([{text, behavior, url, panel?}])` — pobla opciones dinámicamente

``````````````````````````````````````html
<dmb-more-options>
    <dmb-more-option
        behavior="open-panel"
        url="/admin/entidad/edit/<?= $row->id; ?>"
        panel="#panel-form-add-edit-reg">
        Editar
    </dmb-more-option>
    <dmb-more-option
        behavior="ajax"
        url="/admin/entidad/delete/<?= $row->id; ?>">
        Eliminar
    </dmb-more-option>
</dmb-more-options>
`````````````````````````````````````text

---

### dmb-more-option

Opción individual dentro de `dmb-more-options`. Requiere estar contenida en `dmb-more-options`.

**Atributos:**

- `behavior` — `open-panel`, `launch-url`, `ajax` (requerido)

- `url` — URL destino

- `panel` — selector CSS del panel (para `open-panel`)

---

### dmb-tooltip

Componente de estilo puro (solo SCSS, sin directivo). Aplica estilos de tooltip via CSS.

````````````````````````````````````html
<dmb-tooltip>Texto del tooltip</dmb-tooltip>
```````````````````````````````````text

---

### dmb-info-pop

Popup flotante de información. Muestra contenido transcluido en un float.

**Atributos:**

- `size` — `small` (default), `large`

``````````````````````````````````html
<dmb-info-pop size="small">
    <p>Información adicional sobre este campo.</p>
</dmb-info-pop>
`````````````````````````````````text

---

### dmb-help-icon

Icono de ayuda con contenido transcluido. Solo aplica estilos.

````````````````````````````````html
<dmb-help-icon>
    <section class="content">
        <p>Texto de ayuda contextual.</p>
    </section>
</dmb-help-icon>
```````````````````````````````text

---

### dmb-donut-chart

Gráfico de dona SVG con porcentaje y color automático según rango.

**Atributos (data-*):**

- `data-percent` — porcentaje a mostrar (float, 0-100)

Color automático: rojo (<15%), naranja (15-45%), verde (45-75%), azul (>75%).

``````````````````````````````html
<dmb-donut-chart data-percent="<?= $this->porcentaje; ?>"></dmb-donut-chart>
`````````````````````````````text

---

### dmb-clock-text

Contador de tiempo en formato HH:MM:SS que se incrementa automáticamente.

**Atributos (data-*):**

- `data-hours` — horas iniciales

- `data-minutes` — minutos iniciales

- `data-seconds` — segundos iniciales

- `data-days` — días iniciales

````````````````````````````html
<dmb-clock-text
    data-hours="<?= $row->hours; ?>"
    data-minutes="<?= $row->minutes; ?>"
    data-seconds="<?= $row->seconds; ?>"
    data-days="0">
</dmb-clock-text>
```````````````````````````text

---

### dmb-day-clock-text

Variante del contador que incluye días. Mismos atributos `data-*` que `dmb-clock-text`.

---

### dmb-hexagon-button

Botón hexagonal. Extiende `dmb-button-action`, soporta los mismos atributos `behavior`, `url`, `panel`, `action`.

``````````````````````````html
<dmb-hexagon-button
    behavior="launch-url"
    url="/user/index">
    Ir al portal
</dmb-hexagon-button>
`````````````````````````text

---

### dmb-draggable

Elemento arrastrable con soporte de drag and drop nativo.

````````````````````````html
<dmb-draggable>
    <p>Contenido arrastrable</p>
</dmb-draggable>
```````````````````````text

---

### dmb-clone-component

Botón que clona un elemento del DOM al hacer clic.

**Atributos:**

- `target` — selector CSS del elemento a clonar (requerido)

- `clone-into` — selector CSS del contenedor donde insertar el clon (requerido)

**Eventos emitidos:** `beforeClone`, `afterClone`

**Método JS:**

- `cloner.getClonedElement()` — retorna el último elemento clonado

``````````````````````html
<dmb-clone-component
    target="#template-row"
    clone-into="#rows-container">
    + Agregar fila
</dmb-clone-component>
`````````````````````text

---

### dmb-add-command

Botón que agrega dinámicamente inputs de comando en un formulario. De uso específico para features de comandos.

**Atributos:**

- `counter` — contador de comandos actual

- `project-id` — id del proyecto al que pertenecen los comandos

---

### dmb-booking-calendar

Calendario de reservas. Usa `templateUrl` (`dmb-booking-calendar.html`). Ver implementación en vistas de reservas.

````````````````````html
<dmb-booking-calendar></dmb-booking-calendar>
```````````````````text

---

### dmb-parking-lot

Visualización de parqueadero. Extiende `dmb-button-action`, soporta `behavior`, `url`, `panel`.

``````````````````html
<dmb-parking-lot
    behavior="open-panel"
    url="/guarda/parking/<?= $row->id; ?>"
    panel="#panel-parking">
</dmb-parking-lot>
`````````````````text

---

### dmb-living-division-select

Selector jerárquico de unidad habitacional (dos niveles: principal → secundaria). Consume el endpoint `/params/livingDivisions` vía `propertiesService`.

**Estructura interna requerida:** debe contener un `dmb-select.main-unit` y un `dmb-select.secondary-unit`.

````````````````html
<dmb-living-division-select>
    <dmb-select class="main-unit" label="Torre/Bloque" dmb-name="booking[property_id]" validate="required">
        <option value="">Seleccione...</option>
    </dmb-select>
    <dmb-select class="secondary-unit" label="Apartamento" dmb-name="booking[unit_id]" validate="required">
        <option value="">Seleccione...</option>
    </dmb-select>
</dmb-living-division-select>
```````````````text

---

### dmb-living-division-resident-select

Selector jerárquico de unidad + residente (tres niveles: principal → secundaria → residente). Extiende la funcionalidad de `dmb-living-division-select`.

**Estructura interna requerida:** `dmb-select.main-unit`, `dmb-select.secondary-unit`, `dmb-select.residents`.

``````````````html
<dmb-living-division-resident-select>
    <dmb-select class="main-unit" label="Torre/Bloque" dmb-name="tracking[property_id]">
        <option value="">Seleccione...</option>
    </dmb-select>
    <dmb-select class="secondary-unit" label="Apartamento" dmb-name="tracking[unit_id]">
        <option value="">Seleccione...</option>
    </dmb-select>
    <dmb-select class="residents" label="Residente" dmb-name="tracking[user_id]">
        <option value="">Seleccione...</option>
    </dmb-select>
</dmb-living-division-resident-select>
`````````````text

---

### dmb-in-pedestrian / dmb-out-pedestrian

Componentes de registro de entrada/salida peatonal del módulo de guarda. Ver directivos en sus carpetas para implementación detallada.

---

### dmb-property

Visualización de propiedad. Soporta los atributos de `dmb-button-action`.

---

### dmb-property-users-add

Formulario para agregar usuarios a una propiedad. Ver directivo para implementación.

---

### dmb-user-photo

Foto de usuario. Ver directivo `dmb-user.photo.directive.js`.

---

### dmb-image-uploader / dmb-video-uploader / dmb-photo-camera

Componentes de subida de archivos multimedia. Ver directivos para implementación detallada.

---

### dmb-id-reader

Lector de identificación. Ver directivo para implementación.

---

### dmb-puc-entry

Entrada de cuentas PUC (Plan Único de Cuentas). Componente específico del módulo de contabilidad.

---

### dmb-tree-view

Vista de árbol. Ver directivo para implementación.

---

### dmb-login

Formulario de login. Ver directivo `dmb-login.directive.js`.

---

## Event Bus (spinalCord / nerve.js)

````````````js
import { spinalCord } from "../../libs/app/nerve.js";
import { appEvents } from "../../libs/app/configs.js";

spinalCord.subscribe(appEvents.cacheReset.listener, (target) => { });
spinalCord.dispatch(appEvents.cacheReset.listener, ['all']);
```````````text

---

## Modelos JS (factories)

### BaseModelClass (`base-model.factory.js`)

``````````js
import { BaseModelClass } from '../models/base-model.factory.js';

class MiModeloClass extends BaseModelClass {
    constructor() {
        super();
        this.url('/mi-endpoint');
    }

    obtenerDatos(fromCache = true) {
        return this.getElement('mi-clave', fromCache);
    }
}

export const miModelo = new MiModeloClass();
`````````text

**Métodos:** `getFromServer(params, headers)`, `postToServer(body, params, headers)`, `updateToServer(body, params, headers)`, `deleteInServer(params, headers)`. `postToServer` obtiene el CSRF token automáticamente.

### AppModelClass (`app-model.factory.js`)

````````js
import { appModel } from '../models/app-model.factory.js';

appModel.url('/admin/entidades');
appModel.createData(formData, '/admin/entidades');   // POST
appModel.updateData(formData, '/admin/entidades');   // PUT
appModel.deleteData({ id: 1 }, '/admin/entidades'); // DELETE
appModel.login(formData, '/admin/index');
```````text

---

## Archivos de acción por sección

| Archivo | Sección |
| --------- | --------- |
| `app.js` | General (todas las páginas) |
| `app-login.js` | Formularios de login |

Agregar nuevos endpoints en `ui-components/libs/app/configs.js`:

``````js
export const endpoints = {
    admin: {
        login: '/admin/signin'
    }
};
`````text

---

## Build del frontend

````json
// dumbojs.conf.json
{
    "src": "./ui-components/",
    "target": "./app/webroot/dist/",
    "tests": "./tests/",
    "dumbojsSource": "/../../libs/dumbojs/",
    "baseUrl": "/ui-components/"
}
```text

El builder compila los componentes desde `ui-components/` hacia `app/webroot/dist/`. Se ejecuta con
```bash
php uibuilder.php build
```