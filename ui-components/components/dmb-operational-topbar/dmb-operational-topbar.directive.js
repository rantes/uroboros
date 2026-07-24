import { DumboDirective } from "../../libs/dumbojs/dumbo.min.js";

/**
 * Barra superior del shell operativo. Requisito 4 (dashboard-shell):
 * el command palette es visual únicamente en v1 — sin lógica de
 * búsqueda funcional. init() solo pinta las iniciales del usuario
 * recibidas por data-attribute; no hay listeners de teclado ni fetch.
 */
export class DmbOperationalTopbar extends DumboDirective {
    static selector = 'dmb-operational-topbar';
    static templateUrl = 'dmb-operational-topbar.html';

    constructor() {
        super();
    }

    init () {
        const avatar = this.querySelector('.topbar-avatar');

        avatar && (avatar.textContent = this.dataset.userInitials || 'U');
    }
}
