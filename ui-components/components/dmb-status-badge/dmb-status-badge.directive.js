import { DumboDirective } from '/../../libs/dumbojs/dumbo.min.js';

const TONES = ['warning', 'error', 'success', 'information', 'default'];

export class DmbStatusBadge extends DumboDirective {
    static selector = 'dmb-status-badge';
    static templateUrl = 'dmb-status-badge.html';
    static get observedAttributes() { return ['tone']; }

    tone = 'default';

    constructor() {
        super();
    }

    init() {
        this.setTone(this.getAttribute('tone'));
    }

    attributeChangedCallback(attr, oldValue, newValue) {
        switch (attr) {
        case 'tone':
            if (oldValue !== newValue) {
                this.setTone(newValue);
            }
            break;
        }
    }

    setTone(tone) {
        this.classList.remove(`tone-${this.tone}`);
        this.tone = TONES.includes(tone) ? tone : 'default';
        this.classList.add(`tone-${this.tone}`);
    }
}
