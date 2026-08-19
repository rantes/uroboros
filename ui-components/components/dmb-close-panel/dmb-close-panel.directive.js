import { DumboDirective } from "../../libs/dumbojs/dumbo.min.js";

export class DmbClosePanel extends DumboDirective {
    static selector = 'dmb-close-panel';
    // El archivo vivía como dmbClosePanel.html (camelCase) sin
    // ningún templateUrl declarado — mismo patrón ya corregido en
    // dmb-dialog. Sin esto this.querySelector('span[icon]') en
    // init() siempre es null (la plantilla nunca se carga) y el
    // botón de cerrar panel falla con "Cannot read properties of
    // null (reading 'classList')" en cualquier addedit real.
    static templateUrl = 'dmb-close-panel.html';

    init() {
        const orientation = this.getAttribute('orientation') || 'right';
        const icon = this.querySelector('span[icon]');
        let panel = null;

        this.classList.add(orientation);
        icon.classList.add(`chevron_${orientation}`);

        this.addEventListener('click', () => {
            panel = this.closest('dmb-panel');
            panel.close();
        });
    }
}
