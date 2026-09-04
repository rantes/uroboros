import { DumboDirective } from '../../libs/dumbojs/dumbo.min.js';

/**
 * Resumen de fallo — nombre del paso + extracto de su output real,
 * más las acciones sugeridas (Ver Logs/Reintentar/Rollback)
 * transcluidas tal cual desde la vista. step-name/output llegan como
 * atributos ya escapados por htmlspecialchars() en PHP — el navegador
 * los decodifica al parsear el atributo, y se asignan de vuelta con
 * textContent (nunca innerHTML), así que nunca hay doble escape ni
 * riesgo de inyección.
 */
export class DmbFailureSummary extends DumboDirective {
    static selector = 'dmb-failure-summary';
    static templateUrl = 'dmb-failure-summary.html';
    static get observedAttributes() { return ['step-name', 'output']; }

    init() {
        this.querySelector('.failure-summary-step').textContent =
            (this.getAttribute('step-name') || '') + ' falló.';
        this.querySelector('.failure-summary-output').textContent =
            this.getAttribute('output') || '';
    }
}
