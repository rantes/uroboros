import { DumboDirective } from '../../libs/dumbojs/dumbo.min.js';

export class DmbDraggable extends DumboDirective {
    static selector = 'dmb-draggable';
    #_effect = 'move';

    constructor() {
        super();

        this.addEventListener('dragstart', this.dragStart, false);
        this.addEventListener('dragenter', this.dragEnter, false);
        this.addEventListener('dragover', this.dragOver, false);
        this.addEventListener('dragleave', this.dragLeave, false);
        this.addEventListener('drop', this.dragDrop, false);
        this.addEventListener('dragend', this.dragEnd, false);
    }

    init() {
    }

    dragStart(e) {
        this.classList.add('drag-start');
        e.dataTransfer.effectAllowed = this.#_effect;
        e.dataTransfer.setData('text/html', this.innerHTML);
    }

    dragEnter(e) {
        this.classList.add('over');
    }

    dragLeave(e) {
        e.stopPropagation();
        this.classList.remove('over');
    }

    dragOver(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = this.#_effect;
        return false;
    }

    dragDrop() {
        return false;
    }

    dragEnd(e) {
        this.classList.remove('over');
    }
}
