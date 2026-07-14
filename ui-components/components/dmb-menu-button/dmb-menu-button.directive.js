import { DumboDirective } from "../../libs/dumbojs/dumbo.min.js";

export class DmbMenuButton extends DumboDirective {
    static selector = 'dmb-menu-button';

    init() {
        this.addEventListener('click', () => {
            let menu = this.getAttribute('menu');
    
            if (menu.length) {
                document.querySelector(menu).showModal();
            }
        });
    }
}
