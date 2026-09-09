import { DumboDirective } from "../../libs/dumbojs/dumbo.min.js";

/**
 * Wrapper puramente de estilo — el SVG real (nodos, aristas, textos)
 * lo genera el backend (IndexController::_renderDependencyGraphSvg(),
 * dependency-graph/design.md) y llega ya como contenido hijo. Mismo
 * criterio que dmb-health-widget: sin static template/transclude a
 * propósito — un template propio reescribiría innerHTML y perdería el
 * SVG servido, sin aportar comportamiento nuevo que justifique tocar
 * el DOM hijo en absoluto.
 */
export class DmbDependencyGraph extends DumboDirective {
    static selector = 'dmb-dependency-graph';
}
