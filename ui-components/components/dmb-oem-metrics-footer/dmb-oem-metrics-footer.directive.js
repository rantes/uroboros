import { DumboDirective } from "../../libs/dumbojs/dumbo.min.js";

const STATUS_LABELS = {
    healthy: 'OEM Status: Healthy',
    stale: 'OEM Status: Stale',
    unknown: 'OEM Status: Unknown'
};

/**
 * Footer de métricas OEM. Presentación pura — recibe los números ya
 * calculados por OperationalShell_Helper::footerMetrics()/oemStatus()
 * como atributos data-*, mismo patrón que dmb-donut-chart con
 * data-percent (ver dumbojs-components.md).
 */
export class DmbOemMetricsFooter extends DumboDirective {
    static selector = 'dmb-oem-metrics-footer';
    static templateUrl = 'dmb-oem-metrics-footer.html';

    constructor() {
        super();
    }

    #_fill(slot, value) {
        const el = this.querySelector(`[data-slot="${slot}"]`);

        el && (el.textContent = value ?? '—');
    }

    init () {
        this.#_fill('events', this.dataset.events);
        this.#_fill('commands-dispatched', this.dataset.commandsDispatched);
        this.#_fill('commands-failed', this.dataset.commandsFailed);
        this.#_fill('reactions-executed', this.dataset.reactionsExecuted);
        this.#_fill('reactions-failed', this.dataset.reactionsFailed);

        const status = STATUS_LABELS[this.dataset.oemStatus] ? this.dataset.oemStatus : 'unknown';
        const statusEl = this.querySelector('.oem-status');

        if (statusEl) {
            statusEl.textContent = STATUS_LABELS[status];
            statusEl.classList.add(`tone-${status}`);
        }
    }
}
