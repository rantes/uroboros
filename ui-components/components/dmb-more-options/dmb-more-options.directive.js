/**
 * @dmbdoc directive
 * @name DMB.directive:DmbMoreOptions
 *
 * @description
 * Wrapper handler for the group of muy-option items. Render an icon (three vertical points) and on click display the options.
 *
 * @example
<dmb-more-options>
    <dmb-more-option
        behavior="open-panel"
        url="/url/to/run/action"
        panel="#panel-to-open">
    </dmb-more-option>
    <dmb-more-option
        behavior="open-panel"
        url="/url/to/run/action"
        panel="#panel-to-open">
    </dmb-more-option>
</dmb-more-options>
 */
import { DumboDirective } from '../../libs/dumbojs/dumbo.min.js';
export class DmbMoreOptions extends DumboDirective {
    static selector = 'dmb-more-options';
    static template = '<div class="wrapper" transclude></div>';
    wrapper = null;
    option = null;
    #_outsideClickHandler = null;

    constructor() {
        super();
        this.option = document.createElement('dmb-more-option');
    }

    init() {
        this.wrapper = this.querySelector('.wrapper');
        this.setAttribute('icon', 'more_vert');

        this.addEventListener('click', (e) => {
            e.preventDefault();
            document.querySelectorAll('dmb-more-options').forEach(el => {
                if (el !== this) el.querySelector('.wrapper')?.classList.remove('active');
            });
            this.wrapper.classList.toggle('active');
        });

        this.#_outsideClickHandler = (e) => {
            if (!this.contains(e.target)) {
                this.wrapper.classList.remove('active');
            }
        };
        document.addEventListener('click', this.#_outsideClickHandler);

        this.buildOptions();
    }

    destroy() {
        document.removeEventListener('click', this.#_outsideClickHandler);
    }

    setOptions(options) {
        if (!Array.isArray(options)) {
            throw 'Options must to be an array';
        }
        this.options = options;
        this.buildOptions();
    }

    buildOptions() {
        if (!this.wrapper) return;

        let size = 0;
        let i = 0;
        let option = null;

        if (this.options && Array.isArray(this.options)) {
            size = this.options.length;
            for (i = 0; i < size; i++) {
                option = this.option.cloneNode();
                option.innerHTML = this.options[i].text;

                if (this.options[i].behavior) option.setAttribute('behavior', this.options[i].behavior);
                if (this.options[i].url) option.setAttribute('url', this.options[i].url);
                if (this.options[i].panel) option.setAttribute('panel', this.options[i].panel);

                this.wrapper.appendChild(option);
            }
        }
    }
}
