import { DumboDirective } from "./dumbo.min.js";

export class DmbClosePanel extends DumboDirective {
    static selector = 'dmb-close-panel';
    static template = '<span icon="chevron_left"></span>';

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
