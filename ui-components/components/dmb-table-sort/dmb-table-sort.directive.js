import { DumboDirective } from '../../libs/dumbojs/dumbo.min.js';
import { DmbDialogService } from '../../components/dmb-dialog/dmb-dialog.factory.js';

export class DmbTableSort extends DumboDirective {
    #_dialog = undefined;
    static selector = 'dmb-table-sort';

    static get observedAttributes() {
        return ['field', 'filter-form', 'current-field', 'current-dir'];
    }

    constructor() {
        super();
        this.#_dialog = new DmbDialogService();
    }

    init() {
        const formTarget = this.getAttribute('filter-form') || '';
        this.#_dialog.closeAll();

        this.addEventListener('click', e => {
            // Bloque de definiciones — todo al inicio
            const field = this.getAttribute('field') || '';
            const currentField = this.getAttribute('current-field') || '';
            const currentDir = this.getAttribute('current-dir') || 'asc';
            const isSameFieldAsc = currentField === field && currentDir === 'asc';
            const newDir = isSameFieldAsc ? 'desc' : 'asc';
            const panel = this.closest('dmb-panel');
            const form = formTarget ? document.querySelector(formTarget) : null;

            e.preventDefault();

            // Lógica — después del bloque de definiciones
            if (!field) return;

            if (panel) {
                this._sortWithinPanel(panel, field, newDir);
            } else if (form) {
                this._sortWithForm(form, field, newDir);
            } else {
                this._sortWithNavigation(field, newDir);
            }
        });
    }

    _sortWithinPanel(panel, field, dir) {
        // Bloque de definiciones — todo al inicio
        const baseSource = panel.getAttribute('source') || '';
        this.#_dialog.loader();

        // Lógica — después del bloque de definiciones
        if (!baseSource) return;

        const url = new URL(baseSource, window.location.origin);

        url.searchParams.set('sort_field', field);
        url.searchParams.set('sort_dir', dir);

        // dmb-panel observa el atributo 'source' (attributeChangedCallback) y
        // dispara su propio fetch() al cambiar — no se navega la ventana ni
        // se usa ningún form.
        panel.setAttribute('source', url.toString());
    }

    _sortWithForm(form, field, dir) {
        // Bloque de definiciones — todo al inicio
        const url = new URL(window.location.href);

        url.searchParams.set('sort_field', field);
        url.searchParams.set('sort_dir', dir);

        // Lógica — después del bloque de definiciones
        form.setAttribute('action', url.toString());
        form.submit();
    }

    _sortWithNavigation(field, dir) {
        // Bloque de definiciones — todo al inicio
        const url = new URL(window.location.href);

        url.searchParams.set('sort_field', field);
        url.searchParams.set('sort_dir', dir);

        // Lógica — después del bloque de definiciones
        window.location.href = url.toString();
    }
}
