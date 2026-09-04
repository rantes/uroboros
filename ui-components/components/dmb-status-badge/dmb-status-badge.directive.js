import {DumboDirective } from "../../libs/dumbojs/dumbo.min.js";

// 'pending'/'running'/'completed'/'failed' son el vocabulario real de
// WorkflowExecution/StepExecution::validateStatus() — se agregan tal
// cual (no como alias de success/error) para que las vistas puedan
// pasar $row->status directo como tone, sin una capa de mapeo
// intermedia en el controlador o la vista.
const TONES = ['warning', 'error', 'success', 'information', 'default', 'pending', 'running', 'completed', 'failed'];

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
