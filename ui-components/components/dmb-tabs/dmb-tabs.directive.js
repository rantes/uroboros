import { DumboDirective } from '../../libs/dumbojs/dumbo.min.js';

/**
 * Tabs genéricas — cada hijo directo con [data-tab-label] se
 * convierte en un panel, con su botón de navegación generado
 * automáticamente. Activación por fragmento de URL (#slug-del-label),
 * no por JS inline en la vista — un enlace <a href="#logs"> normal
 * puede saltar a un tab específico sin recargar la página y sin
 * violar CSP (nunca onclick/<script> en .phtml).
 */
export class DmbTabs extends DumboDirective {
    static selector = 'dmb-tabs';
    static templateUrl = 'dmb-tabs.html';

    #_panels = [];
    #_nav = null;
    #_onHashChange = null;

    init() {
        this.#_nav = this.querySelector('.dmb-tabs-nav');
        this.#_panels = Array.from(this.querySelectorAll('.dmb-tabs-panels > [data-tab-label]'));

        this.#_panels.forEach(panel => {
            const id = this.#slug(panel.getAttribute('data-tab-label'));
            panel.setAttribute('data-tab-id', id);
            panel.hidden = true;

            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = panel.getAttribute('data-tab-label');
            button.dataset.tabId = id;
            button.addEventListener('click', () => {
                window.location.hash = id;
            });
            this.#_nav.appendChild(button);
        });

        this.#_onHashChange = () => this.#activateFromHash();
        window.addEventListener('hashchange', this.#_onHashChange);
        this.#activateFromHash();
    }

    destroy() {
        this.#_onHashChange && window.removeEventListener('hashchange', this.#_onHashChange);
    }

    #slug(label) {
        return label
            .toLowerCase()
            .normalize('NFD')
            .replace(/\p{Diacritic}/gu, '')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }

    #activateFromHash() {
        const hash = window.location.hash.replace('#', '');
        const target = this.#_panels.find(p => p.getAttribute('data-tab-id') === hash) || this.#_panels[0];

        if (!target) {
            return;
        }

        this.#_panels.forEach(panel => {
            panel.hidden = (panel !== target);
        });

        Array.from(this.#_nav.children).forEach(button => {
            button.classList.toggle('active', button.dataset.tabId === target.getAttribute('data-tab-id'));
        });
    }
}
