import { DumboDirective } from "../../libs/dumbojs/dumbo.min.js";

export class DmbTreeView extends DumboDirective {
    static selector = 'dmb-tree-view';
    static templateUrl = 'dmb-tree-view.html';

    constructor() {
        super();
    }

    init () {
        this.addEventListener('click', (event) => {
            if (event.target.closest('.btn-action')) {
                event.preventDefault();
                event.stopPropagation();
            }
        });
        this.addEventListener('toggle', async (event) => {
            const details = event.target;

            if (details.tagName == 'DETAILS' && details.open && details.dataset.loaded === 'false') {
                const id = details.dataset.id;
                const placeholder = details.querySelector('.content-placeholder');

                try {
                    const response = await fetch(`/contabilidad/puc_children/${id}`);
                    placeholder.outerHTML = await response.text();
                    details.dataset.loaded = 'true';
                } catch (error) {
                    console.error(error);
                    placeholder.textContent = 'Error al cargar';
                }
            }
        }, true);
    }
}
