
import { DumboDirective } from '../../libs/dumbojs/dumbo.min.js';

export class DmbDock extends DumboDirective {
    static selector = 'dmb-dock';

    init () {
        const items = [...this.querySelectorAll('.item')];

        items.map((item) => {
            item.addEventListener('click', (e) => {
                item.classList.add('clicked');
                setTimeout(() => {
                    item.classList.remove('clicked');
                }, 1200);
            });
        });
    }
}
