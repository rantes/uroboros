import { DumboDirective } from "../../libs/dumbojs/dumbo.min.js";

export class DmbClock extends DumboDirective {
    static selector = 'dmb-clock';

    #_interval = null;

    init() {
        this.#_update();
        this.#_interval = setInterval(() => this.#_update(), 1000);
    }

    destroy() {
        clearInterval(this.#_interval);
    }

    #_update() {
        const now = new Date();
        const hh  = String(now.getHours()).padStart(2, '0');
        const mm  = String(now.getMinutes()).padStart(2, '0');
        this.textContent = `${hh}:${mm}`;
    }
}
