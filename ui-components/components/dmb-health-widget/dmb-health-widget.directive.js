import { DumboDirective } from "../../libs/dumbojs/dumbo.min.js";

/**
 * Wrapper de comportamiento para el widget "Operational Health" del
 * dashboard — el PHP server-rendered sigue siendo el contenido real
 * (dmb-donut-chart, dmb-select, texto de Lead Time); este componente
 * solo escucha el 'change' del dmb-select interno, dispara la
 * llamada AJAX a /admin/healthmetrics, y actualiza el DOM en el
 * lugar — sin recargar la página completa (Requisito 3.2).
 */
export class DmbHealthWidget extends DumboDirective {
    static selector = 'dmb-health-widget';
    // Sin static template/transclude a propósito — DumboDirective.
    // connectedCallback() es async (await this.setTemplate(), sin
    // importar si se usa templateUrl o un template inline) y hace
    // `transclude.innerHTML = this.innerHTML` cuando hay template.
    // Anidar otro DumboDirective (dmb-select) dentro de ese
    // transclude es una carrera real: si el padre serializa/reescribe
    // su innerHTML antes de que el hijo termine su propio ciclo de
    // render, los <option> del dmb-select interno se pierden en
    // silencio (confirmado empíricamente — select vacío en
    // /admin/index). Mismo tipo de hallazgo ya documentado en
    // dumbochromedriver.md, "Componentes anidados con renderizado
    // async". Sin template propio, este wrapper no toca su DOM hijo
    // en absoluto — mismo patrón que dmb-content/dmb-footer.

    init() {
        const select = this.querySelector('dmb-select select');

        select && select.addEventListener('change', () => {
            this._refresh(select.value);
        });
    }

    _refresh(windowDays) {
        fetch(`/admin/healthmetrics?window=${encodeURIComponent(windowDays)}`)
            .then(response => response.json())
            .then(payload => this._render(payload.d))
            .catch(() => {});
    }

    _render(data) {
        const successItem = this.querySelector('[data-metric="success-rate"]');
        const leadItem = this.querySelector('[data-metric="lead-time"]');

        successItem.innerHTML = (data.success_rate === null)
            ? '<p class="widget-empty-reason">Sin datos en este período</p><p class="label">Deployment Success Rate</p>'
            : `<dmb-donut-chart data-percent="${data.success_rate}"></dmb-donut-chart><p class="label">Deployment Success Rate</p>`;

        leadItem.innerHTML = `<span class="content">${data.lead_time}</span><p class="label">Lead Time</p>`;
    }
}
