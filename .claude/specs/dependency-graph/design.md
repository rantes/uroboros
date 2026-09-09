# Diseño técnico — Dependency Graph

## Alcance

Sin migraciones, sin modelos nuevos. Un método que construye el grafo
desde datos existentes, un algoritmo de detección de ciclos simple, y
renderizado SVG generado en el controlador.

## Construir el grafo — `_buildProjectDependencyGraph()`

```php
private function _buildProjectDependencyGraph(): array {
    $nodes = [];
    $edges = [];

    $chainedWorkflows = $this->WorkflowDefinition->Find([
        'conditions' => [['workflow_definition_id', 'IS NOT', null]],
    ]);

    foreach ($chainedWorkflows as $childWorkflow):
        $parentWorkflow = $this->WorkflowDefinition->Find($childWorkflow->workflow_definition_id);
        $childProject   = $childWorkflow->project();
        $parentProject  = $parentWorkflow->project();

        (!empty($childProject->id) and !empty($parentProject->id) and $childProject->id !== $parentProject->id)
            and ($nodes[$parentProject->id] = $parentProject->name)
            and ($nodes[$childProject->id]  = $childProject->name)
            and ($edges["{$parentProject->id}->{$childProject->id}"] = [
                'from' => $parentProject->id,
                'to'   => $childProject->id,
            ]);
    endforeach;

    return ['nodes' => $nodes, 'edges' => array_values($edges)];
}
```

> **Verificar antes de implementar:** la sintaxis exacta de `'IS NOT'`
> como operador en `Find()` — no confirmada en ningún spec anterior de
> esta sesión (solo se usó `IN`). Verificar contra el framework real,
> o resolver con `!== null` en PHP después de traer todos los
> registros si el operador no está soportado.
>
> También verificar: `$childProject->id !== $parentProject->id`
> excluye auto-referencias de un Proyecto a sí mismo (un Workflow
> encadenado a otro Workflow del mismo Proyecto) — confirmar que este
> filtro es lo que realmente se quiere (un grafo de *Proyectos* no
> necesita una arista de un nodo a sí mismo, aunque a nivel de
> Workflow sí sea una relación real y válida).

## Detección de ciclos

```php
private function _detectCycle(array $nodes, array $edges): ?array {
    $adjacency = [];
    foreach ($edges as $edge):
        $adjacency[$edge['from']][] = $edge['to'];
    endforeach;

    $visited = [];
    $stack   = [];

    foreach (array_keys($nodes) as $nodeId):
        $cycle = $this->_dfsDetectCycle($nodeId, $adjacency, $visited, $stack);
        !empty($cycle) and count($cycle) and $result = $cycle;
    endforeach;

    return $result ?? null;
}

private function _dfsDetectCycle($nodeId, array $adjacency, array &$visited, array &$stack): array {
    $found = [];

    if (!isset($visited[$nodeId])):
        $visited[$nodeId] = true;
        $stack[$nodeId]   = true;

        foreach (($adjacency[$nodeId] ?? []) as $neighbor):
            if (!empty($stack[$neighbor])):
                $found = [$nodeId, $neighbor];
            elseif (empty($visited[$neighbor])):
                $nested = $this->_dfsDetectCycle($neighbor, $adjacency, $visited, $stack);
                !empty($nested) and ($found = $nested);
            endif;
        endforeach;

        unset($stack[$nodeId]);
    endif;

    return $found;
}
```

> **Complejidad aceptada conscientemente:** esto es DFS clásico con
> pila de recursión — funciona bien a la escala esperada (decenas de
> Proyectos, no miles). No optimizar de más para un caso que no
> existe todavía.

## Layout — niveles simples, no force-directed

Dado que replicar el layout orgánico de las imágenes de referencia
(fuerzas físicas simuladas) es sustancialmente más complejo de
implementar a mano, y no aporta valor funcional adicional sobre un
layout más simple:

```php
private function _computeLayout(array $nodes, array $edges): array {
    $incomingCount = array_fill_keys(array_keys($nodes), 0);
    foreach ($edges as $edge):
        $incomingCount[$edge['to']] = ($incomingCount[$edge['to']] ?? 0) + 1;
    endforeach;

    // Nivel 0 = nodos sin dependencias entrantes (raíces)
    // Nivel N = 1 + nivel máximo de sus "padres" directos
    // (implementación de asignación de niveles vía BFS desde las
    // raíces, con protección contra ciclos — un nodo ya en un ciclo
    // recibe un nivel de fallback, no bloquea el cómputo del resto)
}
```

Cada nivel se dibuja como una columna; los nodos de un mismo nivel se
distribuyen verticalmente de forma pareja. Flechas dibujadas como
líneas rectas o curvas suaves entre nodos (SVG `<path>`), no física
simulada.

## Renderizado SVG

Un solo método que genera el SVG completo (nodos como círculos +
texto del nombre del Proyecto, flechas dirigidas entre ellos), usando
las variables de color de `design-guide.md` (`--secondary-surface`
para nodos, `--border-outline` para líneas, `--warning` si el nodo
está marcado como parte de un ciclo detectado). Versión compacta
(widget) y completa (detalle) comparten el mismo generador, con un
parámetro de tamaño/escala — no dos implementaciones distintas.

## Vistas

- Widget en `index/index.phtml` (Cockpit) — SVG compacto + link "Ver
  detalles" hacia la acción de detalle.
- `IndexController::dependencygraphAction()` — vista de detalle
  completa, SVG a tamaño real, misma advertencia de ciclo si aplica.

## Fuera de alcance de este documento

Ver "Fuera de alcance" en `requirements.md`.