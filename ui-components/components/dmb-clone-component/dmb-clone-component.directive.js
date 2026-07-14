import { DumboDirective } from '../../libs/dumbojs/dumbo.min.js';

export class DmbCloneComponent extends DumboDirective {
    static selector = 'dmb-clone-component';
    #_cloneInto;
    #_insert = 'after';
    #_target;
    #_clonedElement;
    afterCloneEvent;
    beforeCloneEvent;

    constructor() {
        super();

        this.afterCloneEvent = new Event('afterClone');
        this.beforeCloneEvent = new Event('beforeClone');
    }

    #_cloneObject() {
        this.#_clonedElement = this.#_target.cloneNode(true);
        
        this.#_clonedElement.id = this.generateId();
        console.assert(this.#_clonedElement !== this);
        console.log(this.#_clonedElement);

        this.dispatchEvent(this.beforeCloneEvent);

        switch(this.#_insert) {
            case 'before':
                this.#_cloneInto.prepend(this.#_clonedElement);
            break;
            case 'after':
            default:
                this.#_cloneInto.append(this.#_clonedElement);
            break;
        }

        this.dispatchEvent(this.afterCloneEvent);

    }

    init() {
        if (this.hasAttribute('target')) {
            this.#_target = document.querySelector(this.getAttribute('target'));
        } else {
            throw new Error('No target is defined.');
        }

        if (this.hasAttribute('clone-into')) {
            this.#_cloneInto = document.querySelector(this.getAttribute('clone-into'));
        } else {
            throw new Error('No target for  is defined.');
        }

        this.addEventListener('click', () => {
            this.#_cloneObject();
        });
    }

    getClonedElement() {
        return this.#_clonedElement;
    }
}
