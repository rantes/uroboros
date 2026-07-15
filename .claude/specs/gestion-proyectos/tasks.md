# Tareas de implementación — Gestión de Proyectos

Alcance reducido: solo migraciones. Modelo, controlador y vistas los
resuelve el mecanismo genérico del usuario — no son tareas de este
spec.

## Migraciones

- [ ] 1. Crear `migrations/create_projects.php` según `design.md`
- [ ] 2. Crear `migrations/create_groups.php` según `design.md`
- [ ] 3. Crear `migrations/create_project_groups.php` (pivote) según
      `design.md`
- [ ] 4. Verificar contra el framework real si `Add_Index()` soporta
      declarar el índice compuesto de `project_groups` como `UNIQUE`
      — si sí, usar esa forma; si no, dejar constancia de que la
      unicidad del par `(project_id, group_id)` debe garantizarla la
      capa de modelo genérica, no la migración
- [ ] 5. Ejecutar `dumbo migration up projects`, `dumbo migration up
      groups`, `dumbo migration up project_groups`
- [ ] 6. Agregar las tres tablas a `tests/testTables.php`
      (`migrationsTest` con `assertHasFields`/`assertHasFieldTypes`
      para `Project` y `Group` si el mecanismo genérico expone algo
      compatible con esas assertions — verificar, no asumido)

## Fuera de alcance de tasks.md (responsabilidad del usuario)

- Configuración del mecanismo genérico para exponer CRUD de `Project`
  y `Group`.
- Configuración de la relación muchos-a-muchos en ese mecanismo.
- Validaciones (`presence_of`, `unique`, lista cerrada de `type`).
- Formulario con `dmb-select multiple` para asignar grupos a un
  proyecto (Requisito 6.1).
