# Diseño técnico — Gestión de Proyectos

## Alcance de este documento

Reducido por decisión explícita: el proyecto cuenta con un mecanismo
genérico propio que resuelve Modelo, Controlador y Vistas sin clase
por entidad. Este documento cubre **únicamente diseño de datos**
(migraciones y la relación muchos-a-muchos). No especifica
controlador, vistas, ni la sintaxis de declaración de relaciones a
nivel de "modelo" (`has_many_and_belongs_to` o lo que use el
mecanismo genérico) — eso es responsabilidad de ese mecanismo, no de
este spec.

## Migraciones

### `create_projects.php`

| Campo | Tipo | Notas |
| --- | --- | --- |
| `id` | INTEGER | autoincrement, primary |
| `name` | VARCHAR(255) | NOT NULL |
| `description` | TEXT | NULL |
| `repository_url` | VARCHAR(255) | NULL |
| `type` | VARCHAR(50) | NOT NULL — lista cerrada de valores válidos (ej. `backend`, `frontend`, `library`, `mobile`); el enforcement de esa lista es responsabilidad de la capa de modelo genérica, no de la migración |
| `status` | INTEGER | NOT NULL, `limit=1`, `default=0` |
| `created_at` / `updated_at` | INTEGER | automáticos |

Índices:
- `Add_Single_Index('name')` — soporta la validación de unicidad
  (Requisito 2.3) y búsquedas por nombre.

### `create_groups.php`

| Campo | Tipo | Notas |
| --- | --- | --- |
| `id` | INTEGER | autoincrement, primary |
| `name` | VARCHAR(255) | NOT NULL |
| `created_at` / `updated_at` | INTEGER | automáticos |

Índices:
- `Add_Single_Index('name')`.

### `create_project_groups.php` (tabla pivote)

| Campo | Tipo | Notas |
| --- | --- | --- |
| `id` | INTEGER | autoincrement, primary — igual que el resto de tablas del proyecto, no se usa PK compuesta `(project_id, group_id)` |
| `project_id` | INTEGER | NOT NULL |
| `group_id` | INTEGER | NOT NULL |
| `created_at` / `updated_at` | INTEGER | automáticos |

Índices:
- `Add_Index(['project_id', 'group_id'])` — compuesto, para consultar
  en ambas direcciones (proyectos de un grupo, grupos de un proyecto).
- **Pendiente de verificar contra el framework real** (mismo patrón
  que `Add_Index` del núcleo OEM): si este índice compuesto puede
  declararse como `UNIQUE` para impedir a nivel de BD que el mismo
  par `(project_id, group_id)` se repita, o si esa unicidad debe
  garantizarse en la capa de modelo genérica en su lugar. No asumido
  aquí — confirmar con el agente antes de implementar.

Sin claves foráneas (`FOREIGN KEY`) declaradas explícitamente —
`migrations.md` no documenta esa opción en el array de campos, y
ninguna tabla existente del proyecto (incluido `events` del núcleo
OEM) las usa. Consistente con el resto del proyecto, no una excepción
de este spec.

## Relación muchos-a-muchos — diseño físico

```text
projects (1) ──┐
                ├── project_groups (N) ──┐
groups (1) ─────┘                        │
                                    (project_id, group_id)
```

Un proyecto puede estar en cero o más grupos; un grupo puede tener
cero o más proyectos. Eliminar un grupo (Requisito 5.3) implica
eliminar únicamente las filas de `project_groups` que lo referencian
— ni `projects` ni `groups` se ven afectadas directamente por esa
operación.

## Fuera de alcance de este documento

- Declaración de la relación a nivel de "modelo" — depende del
  mecanismo genérico del usuario.
- Controlador(es) y vistas — resueltos por ese mismo mecanismo.
- Enforcement de la lista cerrada de valores válidos para `type`.
- Todo lo ya excluido en `requirements.md` (integración con el núcleo
  OEM, jerarquía de grupos, dashboard, ACL/multiusuario).
  