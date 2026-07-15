---
paths:
  - "ui-components/**/*.scss"
  - "app/views/**/*.phtml"
  - "ui-components/libs/main.css"
---

# Uroboros — Convenciones CSS y SCSS

## Estructura de archivos

```text
ui-components/
├── styles.scss                  ← entry point, importa base y fonts
├── libs/
│   └── main.css                 ← tokens de diseño (paleta + tipografía)
├── base-sass/
│   ├── base.scss                ← importa todos los _base.*
│   ├── _base.materials.scss     ← estilos estructurales globales
│   ├── _base.grid.scss          ← sistema de grid
│   ├── _base.breakpoints.scss   ← breakpoints
│   └── _base.icons.scss          ← estilos de iconografía
└── components/
    └── dmb-nombre/
        └── dmb-nombre.scss      ← estilos encapsulados del componente
```

## Dónde va cada tipo de estilo

| Tipo de estilo | Archivo |
| --- | --- |
| Tokens de color y tipografía | `ui-components/libs/main.css` |
| Estilos estructurales globales | `styles.scss` |
| Estilos de un componente DumboJS | `components/dmb-nombre/dmb-nombre.scss` |

Regla fundamental: **los estilos de un componente nunca van en archivos
`_base.*.scss`**. Si un estilo afecta solo a `dmb-parking-lot`, va en
`dmb-parking-lot.scss`, no en `_base.guard.scss`.

## Tokens de diseño — `main.css`

Todos los valores de color y tipografía viven como variables CSS en
`ui-components/libs/main.css`. **Nunca hardcodear colores ni tamaños
de fuente** — usar siempre las variables definidas.

### Paleta de colores

```css
--primary, --primary-contrast, --primary-hover
--secondary, --secondary-contrast, --secondary-hover
--default, --default-contrast, --default-hover
--error, --error-contrast, --error-hover
--success, --success-contrast, --success-hover
--warning, --warning-contrast, --warning-hover
--information, --information-contrast, --information-hover
--surface-1, --surface-2, --surface-3
--border-subtle, --border-default
--status-ok, --status-ok-text
--overlay-light, --overlay-light-hover, --overlay-subtle
--action-info
--situation-bg-warning, --situation-bg-info,
--situation-bg-ok, --situation-bg-error
```

### Tipografía

```css
--font-size-xs, --font-size-sm, --font-size-base,
--font-size-md, --font-size-lg, --font-size-xl
--font-weight-regular, --font-weight-medium, --font-weight-bold
--line-height-tight, --line-height-base, --line-height-loose
```

## Unidades

| Propiedad | Unidad | Ejemplo |
| --- | --- | --- |
| `width`, `height`, `min-height`, `max-height` | `em` | `min-height: 5em` |
| `margin`, `padding`, `gap` | `em` | `padding: 0.75em 1.25em` |
| `border-width` | `px` | `border: 1px solid` |
| `font-size` | variables CSS | `font-size: var(--font-size-sm)` |
| `border-radius` | `em` o `rem` | `border-radius: 0.75em` |

**Nunca usar `px` para alturas, anchos, márgenes ni paddings.**
Los bordes (`border`, `outline`) son la única excepción donde `1px` es correcto.

## Encapsulamiento de estilos por sección

ES TOTALMENTE PROHIBIDO usar los archivos `_base.{seccion}.scss` para encapsular estilos. Si se necesita encapsular estilos para una sección específica, se deben crear componentes que satisfagan la necesidad. No importa si es un componente que sólo se usa en esa sección, debe ser un componente independiente y no importa si es sólo para utilizar su estilo.

## Estilos de componentes DumboJS

Cada componente tiene su propio archivo SCSS. Los selectores usan
el tag del componente como raíz:

```scss
dmb-parking-lot {
    display: block;
    background: var(--success-hover);
    border: 1px solid var(--success);
    color: var(--success-contrast);

    &[occupied] {
        background: var(--warning-hover);
        border-color: var(--warning);
        color: var(--warning-contrast);
    }
}
```

Cuando un componente necesita estilos diferentes dentro de una sección
específica, el override va en el archivo de la sección, no en el del
componente:

```scss
dmb-custom-component {
    dmb-parking-lot {
        width: 2.5em;
        height: 2.5em;
    }
}
```

## Colores de estado semántico

Usar siempre las variables semánticas. La regla para texto sobre
fondos `*-hover` (fondos claros semitransparentes):

```scss
// Sobre fondo --success-hover (claro) → texto --success (no --success-contrast)
.badge-ok {
    background: var(--success-hover);
    border: 1px solid var(--success);
    color: var(--success);
}

// Sobre fondo --success sólido → texto --success-contrast
.badge-ok-solid {
    background: var(--success);
    color: var(--success-contrast);
}
```

`--*-contrast` es para texto sobre el color sólido.
Sobre fondos `--*-hover` usar el color base (`--success`, `--warning`, etc.).

## Layouts disponibles

| Layout | Archivo | Cuándo usar |
| --- | --- | --- |
| Principal | `app/views/layout.phtml` | Admin, contador |
| Residente | `app/views/user_layout.phtml` | Portal residente |
| Guarda | `app/views/guard_layout.phtml` | Portal guarda |

Definir en el constructor del controlador:

```php
$this->layout = 'user_layout';   // residente
$this->layout = 'guard_layout';  // guarda
// admin y contador usan layout por defecto
```

## Anti-patrones — nunca hacer esto

```scss
// INCORRECTO — color hardcodeado
.mi-elemento {
    background: #1F3D26;
    color: #FFFFFF;
}

// CORRECTO
.mi-elemento {
    background: var(--primary);
    color: var(--primary-contrast);
}

// INCORRECTO — dimensiones en px
.mi-elemento {
    padding: 12px 20px;
    min-height: 80px;
}

// CORRECTO
.mi-elemento {
    padding: 0.75em 1.25em;
    min-height: 5em;
}

// INCORRECTO — estilos de componente en archivo global
// En _base.guard.scss:
dmb-parking-lot { background: var(--success-hover); }

// CORRECTO — en dmb-parking-lot.scss
dmb-parking-lot { background: var(--success-hover); }
```