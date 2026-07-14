import { DumboDirective } from "../../libs/dumbojs/dumbo.min.js";

// ─── SVG Icons ───────────────────────────────────────────────────────────────

const ICONS = {
    bold:          '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M6 4h8a4 4 0 0 1 0 8H6z"/><path d="M6 12h9a4 4 0 0 1 0 8H6z"/></svg>',
    italic:        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="19" y1="4" x2="10" y2="4"/><line x1="14" y1="20" x2="5" y2="20"/><line x1="15" y1="4" x2="9" y2="20"/></svg>',
    underline:     '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M6 3v7a6 6 0 0 0 12 0V3"/><line x1="4" y1="21" x2="20" y2="21"/></svg>',
    strikethrough: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M16 4H9a3 3 0 0 0 0 6h6a3 3 0 0 1 0 6H6"/><line x1="4" y1="12" x2="20" y2="12"/></svg>',
    ul:            '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="9" y1="6" x2="20" y2="6"/><line x1="9" y1="12" x2="20" y2="12"/><line x1="9" y1="18" x2="20" y2="18"/><circle cx="4" cy="6" r="1" fill="currentColor"/><circle cx="4" cy="12" r="1" fill="currentColor"/><circle cx="4" cy="18" r="1" fill="currentColor"/></svg>',
    ol:            '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="10" y1="6" x2="21" y2="6"/><line x1="10" y1="12" x2="21" y2="12"/><line x1="10" y1="18" x2="21" y2="18"/><text x="1" y="7" font-size="6" fill="currentColor" stroke="none">1</text><text x="1" y="13" font-size="6" fill="currentColor" stroke="none">2</text><text x="1" y="19" font-size="6" fill="currentColor" stroke="none">3</text></svg>',
    link:          '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>',
    unlink:        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/><line x1="2" y1="2" x2="22" y2="22"/></svg>',
    h1:            '<svg viewBox="0 0 24 24" fill="currentColor"><text x="1" y="18" font-size="14" font-weight="bold" font-family="serif">H1</text></svg>',
    h2:            '<svg viewBox="0 0 24 24" fill="currentColor"><text x="1" y="18" font-size="14" font-weight="bold" font-family="serif">H2</text></svg>',
    h3:            '<svg viewBox="0 0 24 24" fill="currentColor"><text x="1" y="18" font-size="14" font-weight="bold" font-family="serif">H3</text></svg>',
    h4:            '<svg viewBox="0 0 24 24" fill="currentColor"><text x="1" y="18" font-size="14" font-weight="bold" font-family="serif">H4</text></svg>',
    h5:            '<svg viewBox="0 0 24 24" fill="currentColor"><text x="1" y="18" font-size="14" font-weight="bold" font-family="serif">H5</text></svg>',
    h6:            '<svg viewBox="0 0 24 24" fill="currentColor"><text x="1" y="18" font-size="14" font-weight="bold" font-family="serif">H6</text></svg>',
    p:             '<svg viewBox="0 0 24 24" fill="currentColor"><text x="3" y="18" font-size="16" font-weight="bold" font-family="serif">¶</text></svg>',
    blockquote:    '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 21c3 0 7-1 7-8V5c0-1.25-.756-2.017-2-2H4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2 1 0 1 0 1 1v1c0 1-1 2-2 2s-1 .008-1 1.031V20c0 1 0 1 1 1z"/><path d="M15 21c3 0 7-1 7-8V5c0-1.25-.757-2.017-2-2h-4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2h.75c0 2.25.25 4-2.75 4v3c0 1 0 1 1 1z"/></svg>',
    alignLeft:     '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="15" y2="12"/><line x1="3" y1="18" x2="18" y2="18"/></svg>',
    alignCenter:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="6" y1="12" x2="18" y2="12"/><line x1="4" y1="18" x2="20" y2="18"/></svg>',
    alignRight:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="9" y1="12" x2="21" y2="12"/><line x1="6" y1="18" x2="21" y2="18"/></svg>',
    alignJustify:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>',
    superscript:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 19 19 5M4 5l15 14"/><path d="M20 12V7h-2l2-2"/></svg>',
    subscript:     '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 19 19 5M4 5l15 14"/><path d="M20 22v-5h-2l2-2"/></svg>',
    undo:          '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="9 14 4 9 9 4"/><path d="M20 20v-7a4 4 0 0 0-4-4H4"/></svg>',
    redo:          '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="15 14 20 9 15 4"/><path d="M4 20v-7a4 4 0 0 1 4-4h12"/></svg>',
    source:        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>',
    fontColor:     '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 3L5 21h2.5l1-4h7l1 4H19L15 3H9zm-.25 12L11 6l2.25 9H8.75z"/><rect x="3" y="22" width="18" height="2" rx="1" fill="var(--primary)"/></svg>',
};

// ─── Toolbar definitions per mode ────────────────────────────────────────────

const TOOLBAR = {
    basic: [
        { cmd: 'bold',           icon: 'bold',        title: 'Negrita',       exec: 'bold' },
        { cmd: 'italic',         icon: 'italic',      title: 'Cursiva',       exec: 'italic' },
        { cmd: 'underline',      icon: 'underline',   title: 'Subrayado',     exec: 'underline' },
        { type: 'sep' },
        { cmd: 'insertUnorderedList', icon: 'ul',     title: 'Lista',         exec: 'insertUnorderedList' },
        { cmd: 'insertOrderedList',   icon: 'ol',     title: 'Lista numerada',exec: 'insertOrderedList' },
        { type: 'sep' },
        { cmd: 'link',           icon: 'link',        title: 'Insertar enlace',exec: 'link' },
        { cmd: 'unlink',         icon: 'unlink',      title: 'Quitar enlace', exec: 'unlink' },
        { type: 'sep' },
        { cmd: 'undo',           icon: 'undo',        title: 'Deshacer',      exec: 'undo' },
        { cmd: 'redo',           icon: 'redo',        title: 'Rehacer',       exec: 'redo' },
    ],
    medium: [
        { cmd: 'bold',           icon: 'bold',        title: 'Negrita',       exec: 'bold' },
        { cmd: 'italic',         icon: 'italic',      title: 'Cursiva',       exec: 'italic' },
        { cmd: 'underline',      icon: 'underline',   title: 'Subrayado',     exec: 'underline' },
        { cmd: 'strikethrough',  icon: 'strikethrough',title: 'Tachado',      exec: 'strikeThrough' },
        { type: 'sep' },
        { cmd: 'h1',             icon: 'h1',          title: 'Título 1',      exec: 'formatBlock', value: 'h1' },
        { cmd: 'h2',             icon: 'h2',          title: 'Título 2',      exec: 'formatBlock', value: 'h2' },
        { cmd: 'h3',             icon: 'h3',          title: 'Título 3',      exec: 'formatBlock', value: 'h3' },
        { cmd: 'p',              icon: 'p',           title: 'Párrafo',       exec: 'formatBlock', value: 'p' },
        { cmd: 'blockquote',     icon: 'blockquote',  title: 'Cita',          exec: 'formatBlock', value: 'blockquote' },
        { type: 'sep' },
        { cmd: 'insertUnorderedList', icon: 'ul',     title: 'Lista',         exec: 'insertUnorderedList' },
        { cmd: 'insertOrderedList',   icon: 'ol',     title: 'Lista numerada',exec: 'insertOrderedList' },
        { type: 'sep' },
        { cmd: 'justifyLeft',    icon: 'alignLeft',   title: 'Alinear izq.',  exec: 'justifyLeft' },
        { cmd: 'justifyCenter',  icon: 'alignCenter', title: 'Centrar',       exec: 'justifyCenter' },
        { cmd: 'justifyRight',   icon: 'alignRight',  title: 'Alinear der.',  exec: 'justifyRight' },
        { cmd: 'justifyFull',    icon: 'alignJustify',title: 'Justificar',    exec: 'justifyFull' },
        { type: 'sep' },
        { cmd: 'link',           icon: 'link',        title: 'Insertar enlace',exec: 'link' },
        { cmd: 'unlink',         icon: 'unlink',      title: 'Quitar enlace', exec: 'unlink' },
        { type: 'sep' },
        { cmd: 'undo',           icon: 'undo',        title: 'Deshacer',      exec: 'undo' },
        { cmd: 'redo',           icon: 'redo',        title: 'Rehacer',       exec: 'redo' },
        { type: 'sep' },
        { cmd: 'source',         icon: 'source',      title: 'Ver HTML',      exec: 'source' },
    ],
    full: [
        { cmd: 'bold',           icon: 'bold',        title: 'Negrita',       exec: 'bold' },
        { cmd: 'italic',         icon: 'italic',      title: 'Cursiva',       exec: 'italic' },
        { cmd: 'underline',      icon: 'underline',   title: 'Subrayado',     exec: 'underline' },
        { cmd: 'strikethrough',  icon: 'strikethrough',title: 'Tachado',      exec: 'strikeThrough' },
        { cmd: 'superscript',    icon: 'superscript', title: 'Superíndice',   exec: 'superscript' },
        { cmd: 'subscript',      icon: 'subscript',   title: 'Subíndice',     exec: 'subscript' },
        { type: 'sep' },
        { cmd: 'h1',             icon: 'h1',          title: 'Título 1',      exec: 'formatBlock', value: 'h1' },
        { cmd: 'h2',             icon: 'h2',          title: 'Título 2',      exec: 'formatBlock', value: 'h2' },
        { cmd: 'h3',             icon: 'h3',          title: 'Título 3',      exec: 'formatBlock', value: 'h3' },
        { cmd: 'h4',             icon: 'h4',          title: 'Título 4',      exec: 'formatBlock', value: 'h4' },
        { cmd: 'h5',             icon: 'h5',          title: 'Título 5',      exec: 'formatBlock', value: 'h5' },
        { cmd: 'h6',             icon: 'h6',          title: 'Título 6',      exec: 'formatBlock', value: 'h6' },
        { cmd: 'p',              icon: 'p',           title: 'Párrafo',       exec: 'formatBlock', value: 'p' },
        { cmd: 'blockquote',     icon: 'blockquote',  title: 'Cita',          exec: 'formatBlock', value: 'blockquote' },
        { type: 'sep' },
        { cmd: 'insertUnorderedList', icon: 'ul',     title: 'Lista',         exec: 'insertUnorderedList' },
        { cmd: 'insertOrderedList',   icon: 'ol',     title: 'Lista numerada',exec: 'insertOrderedList' },
        { type: 'sep' },
        { cmd: 'justifyLeft',    icon: 'alignLeft',   title: 'Alinear izq.',  exec: 'justifyLeft' },
        { cmd: 'justifyCenter',  icon: 'alignCenter', title: 'Centrar',       exec: 'justifyCenter' },
        { cmd: 'justifyRight',   icon: 'alignRight',  title: 'Alinear der.',  exec: 'justifyRight' },
        { cmd: 'justifyFull',    icon: 'alignJustify',title: 'Justificar',    exec: 'justifyFull' },
        { type: 'sep' },
        { cmd: 'link',           icon: 'link',        title: 'Insertar enlace',exec: 'link' },
        { cmd: 'unlink',         icon: 'unlink',      title: 'Quitar enlace', exec: 'unlink' },
        { type: 'sep' },
        { cmd: 'fontColor',      icon: 'fontColor',   title: 'Color de texto', exec: 'fontColor' },
        { type: 'sep' },
        { cmd: 'undo',           icon: 'undo',        title: 'Deshacer',      exec: 'undo' },
        { cmd: 'redo',           icon: 'redo',        title: 'Rehacer',       exec: 'redo' },
        { type: 'sep' },
        { cmd: 'source',         icon: 'source',      title: 'Ver HTML',      exec: 'source' },
    ],
};

const COLOR_PALETTE = [
    '#000000', '#434343', '#666666', '#999999', '#b7b7b7', '#cccccc', '#d9d9d9', '#ffffff',
    '#ff0000', '#ff9900', '#ffff00', '#00ff00', '#00ffff', '#4a86e8', '#0000ff', '#9900ff',
    '#e06666', '#f6b26b', '#ffd966', '#93c47d', '#76d7ea', '#6fa8dc', '#8e7cc3', '#c27ba0',
];

// ─── Component ───────────────────────────────────────────────────────────────

export class DmbWysiwyg extends DumboDirective {
    static selector = 'dmb-wysiwyg';
    static template = '<div class="dmb-wysiwyg__root"></div>';

    static get observedAttributes() {
        return ['valid', 'dmb-name', 'validate', 'mode', 'dmb-value'];
    }

    // Private fields
    #_mode         = 'basic';
    #_toolbar      = null;
    #_editArea     = null;
    #_textarea     = null;
    #_linkPopup    = null;
    #_colorPopup   = null;
    #_sourceMode   = false;
    #_savedRange   = null;
    #_isValid      = true;
    #_validators   = [];
    #_errorClass   = 'wysiwyg--invalid';

    init() {
        // connectedCallback is async — template may not be in DOM yet on first call.
        // Guard: if root missing, wait for afterRendered then retry once.
        const root = this.querySelector('.dmb-wysiwyg__root');
        if (!root) {
            this.addEventListener(DmbEvents.afterRendered.listener, () => this.init(), { once: true });
            return;
        }

        this.#_mode = (this.getAttribute('mode') || 'basic').toLowerCase();
        if (!TOOLBAR[this.#_mode]) this.#_mode = 'basic';

        this.#_build();
        this.#_bindToolbar();
        this.#_bindEditArea();
        this.#_bindForm();
        this.#_buildValidators();

        const initial = this.getAttribute('dmb-value') || '';
        if (initial) this.#_editArea.innerHTML = initial;
    }

    // ── Build DOM ─────────────────────────────────────────────────────────────

    #_build() {
        const root = this.querySelector('.dmb-wysiwyg__root');
        root.innerHTML = '';
        root.classList.add(`dmb-wysiwyg--${this.#_mode}`);

        // Toolbar
        this.#_toolbar = document.createElement('div');
        this.#_toolbar.className = 'dmb-wysiwyg__toolbar';
        this.#_toolbar.setAttribute('role', 'toolbar');
        this.#_buildButtons();
        root.appendChild(this.#_toolbar);

        // Edit area
        this.#_editArea = document.createElement('div');
        this.#_editArea.className = 'dmb-wysiwyg__editor';
        this.#_editArea.contentEditable = 'true';
        this.#_editArea.setAttribute('role', 'textbox');
        this.#_editArea.setAttribute('aria-multiline', 'true');
        root.appendChild(this.#_editArea);

        // Hidden textarea (carries value on form submit)
        this.#_textarea = document.createElement('textarea');
        this.#_textarea.className = 'dmb-wysiwyg__value';
        this.#_textarea.setAttribute('hidden', '');
        this.#_textarea.name = this.getAttribute('dmb-name') || '';
        root.appendChild(this.#_textarea);

        // Error container
        const err = document.createElement('span');
        err.className = 'dmb-wysiwyg__error';
        root.appendChild(err);

        // Link popup (shared across modes that have link)
        this.#_buildLinkPopup(root);

        // Color popup (full mode only)
        if (this.#_mode === 'full') {
            this.#_buildColorPopup(root);
        }
    }

    #_buildButtons() {
        const buttons = TOOLBAR[this.#_mode];
        buttons.forEach(btn => {
            if (btn.type === 'sep') {
                const sep = document.createElement('span');
                sep.className = 'dmb-wysiwyg__sep';
                this.#_toolbar.appendChild(sep);
                return;
            }
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'dmb-wysiwyg__btn';
            b.dataset.cmd = btn.cmd;
            b.title = btn.title;
            b.setAttribute('aria-label', btn.title);
            b.innerHTML = ICONS[btn.icon] || btn.icon;
            this.#_toolbar.appendChild(b);
        });
    }

    #_buildLinkPopup(root) {
        this.#_linkPopup = document.createElement('div');
        this.#_linkPopup.className = 'dmb-wysiwyg__popup dmb-wysiwyg__popup--link';
        this.#_linkPopup.setAttribute('hidden', '');
        this.#_linkPopup.innerHTML = `
            <input class="dmb-wysiwyg__popup-input" type="url" placeholder="https://" />
            <button type="button" class="dmb-wysiwyg__popup-ok">Insertar</button>
            <button type="button" class="dmb-wysiwyg__popup-cancel">✕</button>
        `;
        root.appendChild(this.#_linkPopup);
    }

    #_buildColorPopup(root) {
        this.#_colorPopup = document.createElement('div');
        this.#_colorPopup.className = 'dmb-wysiwyg__popup dmb-wysiwyg__popup--color';
        this.#_colorPopup.setAttribute('hidden', '');

        const swatches = COLOR_PALETTE.map((c, i) =>
            `<button type="button" class="dmb-wysiwyg__swatch dmb-wysiwyg__swatch--${i}" data-color="${c}" title="${c}" aria-label="${c}"></button>`
        ).join('');

        this.#_colorPopup.innerHTML = `<div class="dmb-wysiwyg__swatches">${swatches}</div>`;
        root.appendChild(this.#_colorPopup);
    }

    // ── Toolbar binding ───────────────────────────────────────────────────────

    #_bindToolbar() {
        this.#_toolbar.addEventListener('mousedown', e => {
            // Prevent toolbar clicks from blurring the editor
            e.preventDefault();
        });

        this.#_toolbar.addEventListener('click', e => {
            const btn = e.target.closest('[data-cmd]');
            if (!btn) return;
            e.preventDefault();
            this.#_execute(btn.dataset.cmd);
            this.#_updateActiveStates();
        });

        // Link popup ok
        this.#_linkPopup.querySelector('.dmb-wysiwyg__popup-ok').addEventListener('click', () => {
            const url = this.#_linkPopup.querySelector('input').value.trim();
            if (url) {
                this.#_restoreRange();
                document.execCommand('createLink', false, url);
            }
            this.#_closeLinkPopup();
        });

        this.#_linkPopup.querySelector('.dmb-wysiwyg__popup-cancel').addEventListener('click', () => {
            this.#_closeLinkPopup();
        });

        this.#_linkPopup.querySelector('input').addEventListener('keydown', e => {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.#_linkPopup.querySelector('.dmb-wysiwyg__popup-ok').click();
            }
            if (e.key === 'Escape') this.#_closeLinkPopup();
        });

        // Color popup swatches
        if (this.#_colorPopup) {
            this.#_colorPopup.addEventListener('click', e => {
                const swatch = e.target.closest('[data-color]');
                if (!swatch) return;
                this.#_restoreRange();
                document.execCommand('foreColor', false, swatch.dataset.color);
                this.#_closeColorPopup();
            });
        }

        // Close popups on outside click
        document.addEventListener('click', e => {
            if (!this.contains(e.target)) {
                this.#_closeLinkPopup();
                this.#_closeColorPopup();
            }
        });
    }

    // ── Execute commands ──────────────────────────────────────────────────────

    #_execute(cmd) {
        const def = TOOLBAR[this.#_mode].find(b => b.cmd === cmd);
        if (!def) return;

        switch (def.exec) {
        case 'bold':
        case 'italic':
        case 'underline':
        case 'strikeThrough':
        case 'superscript':
        case 'subscript':
        case 'insertUnorderedList':
        case 'insertOrderedList':
        case 'justifyLeft':
        case 'justifyCenter':
        case 'justifyRight':
        case 'justifyFull':
        case 'unlink':
        case 'undo':
        case 'redo':
            this.#_editArea.focus();
            document.execCommand(def.exec, false, null);
            break;

        case 'formatBlock':
            this.#_editArea.focus();
            document.execCommand('formatBlock', false, def.value);
            break;

        case 'link':
            this.#_saveRange();
            this.#_openLinkPopup();
            break;

        case 'fontColor':
            this.#_saveRange();
            this.#_toggleColorPopup();
            break;

        case 'source':
            this.#_toggleSourceMode();
            break;
        }
    }

    // ── Selection / Range helpers ─────────────────────────────────────────────

    #_saveRange() {
        const sel = window.getSelection();
        if (sel && sel.rangeCount > 0) {
            this.#_savedRange = sel.getRangeAt(0).cloneRange();
        }
    }

    #_restoreRange() {
        if (!this.#_savedRange) return;
        const sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(this.#_savedRange);
        this.#_editArea.focus();
    }

    // ── Link popup ────────────────────────────────────────────────────────────

    #_openLinkPopup() {
        // Pre-fill if selection is already a link
        const sel = window.getSelection();
        let existing = '';
        if (sel && sel.rangeCount > 0) {
            const anchor = sel.anchorNode?.parentElement?.closest('a');
            if (anchor) existing = anchor.href;
        }
        this.#_linkPopup.querySelector('input').value = existing;
        this.#_linkPopup.removeAttribute('hidden');
        this.#_linkPopup.querySelector('input').focus();
        this.#_closeColorPopup();
    }

    #_closeLinkPopup() {
        this.#_linkPopup.setAttribute('hidden', '');
        this.#_linkPopup.querySelector('input').value = '';
    }

    // ── Color popup ───────────────────────────────────────────────────────────

    #_toggleColorPopup() {
        if (this.#_colorPopup.hasAttribute('hidden')) {
            this.#_colorPopup.removeAttribute('hidden');
            this.#_closeLinkPopup();
        } else {
            this.#_closeColorPopup();
        }
    }

    #_closeColorPopup() {
        if (this.#_colorPopup) this.#_colorPopup.setAttribute('hidden', '');
    }

    // ── Source mode ───────────────────────────────────────────────────────────

    #_toggleSourceMode() {
        this.#_sourceMode = !this.#_sourceMode;
        const sourceBtn = this.#_toolbar.querySelector('[data-cmd="source"]');

        if (this.#_sourceMode) {
            this.#_textarea.value = this.#_editArea.innerHTML;
            this.#_textarea.removeAttribute('hidden');
            this.#_textarea.classList.add('dmb-wysiwyg__value--visible');
            this.#_textarea.name = ''; // don't double-submit
            this.#_editArea.setAttribute('hidden', '');
            if (sourceBtn) sourceBtn.classList.add('dmb-wysiwyg__btn--active');
        } else {
            this.#_editArea.innerHTML = this.#_textarea.value;
            this.#_editArea.removeAttribute('hidden');
            this.#_textarea.setAttribute('hidden', '');
            this.#_textarea.classList.remove('dmb-wysiwyg__value--visible');
            this.#_textarea.name = this.getAttribute('dmb-name') || '';
            if (sourceBtn) sourceBtn.classList.remove('dmb-wysiwyg__btn--active');
        }
    }

    // ── Active state tracking ─────────────────────────────────────────────────

    #_updateActiveStates() {
        const toggleCmds = {
            'bold':               () => document.queryCommandState('bold'),
            'italic':             () => document.queryCommandState('italic'),
            'underline':          () => document.queryCommandState('underline'),
            'strikethrough':      () => document.queryCommandState('strikeThrough'),
            'superscript':        () => document.queryCommandState('superscript'),
            'subscript':          () => document.queryCommandState('subscript'),
            'insertUnorderedList':() => document.queryCommandState('insertUnorderedList'),
            'insertOrderedList':  () => document.queryCommandState('insertOrderedList'),
            'justifyLeft':        () => document.queryCommandState('justifyLeft'),
            'justifyCenter':      () => document.queryCommandState('justifyCenter'),
            'justifyRight':       () => document.queryCommandState('justifyRight'),
            'justifyFull':        () => document.queryCommandState('justifyFull'),
        };

        this.#_toolbar.querySelectorAll('[data-cmd]').forEach(btn => {
            const check = toggleCmds[btn.dataset.cmd];
            if (check) {
                btn.classList.toggle('dmb-wysiwyg__btn--active', check());
            }
        });
    }

    // ── Edit area binding ─────────────────────────────────────────────────────

    #_bindEditArea() {
        this.#_editArea.addEventListener('keyup', () => this.#_updateActiveStates());
        this.#_editArea.addEventListener('mouseup', () => this.#_updateActiveStates());
        this.#_editArea.addEventListener('input', () => this.#_syncTextarea());

        // Ensure block-level elements on Enter instead of bare <br>
        this.#_editArea.addEventListener('keydown', e => {
            if (e.key === 'Enter' && !e.shiftKey) {
                // Default behavior is fine for most cases;
                // this prevents double-br in some browsers
                const sel = window.getSelection();
                if (!sel.rangeCount) return;
                const block = sel.anchorNode?.parentElement?.closest('p,h1,h2,h3,h4,h5,h6,li,blockquote');
                if (!block) {
                    e.preventDefault();
                    document.execCommand('formatBlock', false, 'p');
                }
            }
        });

        this.#_editArea.addEventListener('blur', () => {
            this.#_syncTextarea();
            if (this.#_validators.length) this.#_validate();
        });

        // Paste: strip styles, keep structure
        this.#_editArea.addEventListener('paste', e => {
            e.preventDefault();
            const html = e.clipboardData.getData('text/html');
            const text = e.clipboardData.getData('text/plain');

            if (html) {
                const clean = this.#_cleanPaste(html);
                document.execCommand('insertHTML', false, clean);
            } else {
                document.execCommand('insertText', false, text);
            }
        });
    }

    // ── Paste sanitizer ───────────────────────────────────────────────────────

    #_cleanPaste(html) {
        const tmp = document.createElement('div');
        tmp.innerHTML = html;

        // Remove scripts, styles, comments
        tmp.querySelectorAll('script, style, meta, link').forEach(el => el.remove());

        // Strip inline styles and class/id attributes
        tmp.querySelectorAll('*').forEach(el => {
            el.removeAttribute('style');
            el.removeAttribute('class');
            el.removeAttribute('id');
        });

        // Unwrap spans (they carry no semantic value after stripping styles)
        tmp.querySelectorAll('span').forEach(el => {
            el.replaceWith(...el.childNodes);
        });

        return tmp.innerHTML;
    }

    // ── Form sync ─────────────────────────────────────────────────────────────

    #_syncTextarea() {
        if (!this.#_sourceMode) {
            this.#_textarea.value = this.#_editArea.innerHTML;
        }
    }

    #_bindForm() {
        const form = this.closest('dmb-form') || this.closest('form');
        if (form) {
            form.addEventListener('submit', () => this.#_syncTextarea());
        }
    }

    // ── Validation ────────────────────────────────────────────────────────────

    #_buildValidators() {
        const raw = (this.getAttribute('validate') || '').trim();
        if (!raw) return;

        raw.split(',').forEach(part => {
            const [key, param] = part.trim().split(':');
            if (key) this.#_validators.push({ key, param: param || null });
            if (key === 'required') {
                this.#_textarea.setAttribute('required', '');
                this.classList.add('required');
            }
        });

        // Global validate event (DumboJS pattern)
        document.body.addEventListener(window.DmbEvents?.validate?.listener || 'dmb:validate', () => {
            this.#_validate();
        }, true);

        document.body.addEventListener(window.DmbEvents?.resetValidation?.listener || 'dmb:reset-validation', () => {
            this.#_clearError();
        }, true);
    }

    #_validate() {
        const content = this.#_editArea.innerHTML.replace(/<[^>]*>/g, '').trim();
        let valid = true;
        let message = '';

        for (const v of this.#_validators) {
            if (v.key === 'required' && !content) {
                valid = false;
                message = 'Este campo es obligatorio';
                break;
            }
        }

        this.#_isValid = valid;
        const errEl = this.querySelector('.dmb-wysiwyg__error');

        if (valid) {
            this.classList.remove(this.#_errorClass);
            this.setAttribute('valid', '');
            if (errEl) errEl.textContent = '';
        } else {
            this.classList.add(this.#_errorClass);
            this.removeAttribute('valid');
            if (errEl) errEl.textContent = message;
        }
    }

    #_clearError() {
        this.classList.remove(this.#_errorClass);
        this.setAttribute('valid', '');
        const errEl = this.querySelector('.dmb-wysiwyg__error');
        if (errEl) errEl.textContent = '';
    }

    // ── Public API ────────────────────────────────────────────────────────────

    getValue() {
        return this.#_editArea.innerHTML;
    }

    setValue(html) {
        this.#_editArea.innerHTML = html || '';
        this.#_syncTextarea();
    }

    clear() {
        this.#_editArea.innerHTML = '';
        this.#_syncTextarea();
    }

    // ── attributeChangedCallback ──────────────────────────────────────────────

    attributeChangedCallback(attr, oldValue, newValue) {
        switch (attr) {
        case 'dmb-name':
            if (this.#_textarea && !this.#_sourceMode) {
                this.#_textarea.name = newValue || '';
            }
            break;
        case 'dmb-value':
            if (this.#_editArea && oldValue !== null) {
                this.#_editArea.innerHTML = newValue || '';
                this.#_syncTextarea();
            }
            break;
        case 'valid':
            this.#_isValid = (newValue !== null);
            break;
        case 'validate':
            if (oldValue !== null && this.#_editArea) {
                this.#_validators = [];
                this.#_buildValidators();
            }
            break;
        case 'mode':
            if (oldValue !== null && newValue && TOOLBAR[newValue]) {
                this.#_mode = newValue;
                const root = this.querySelector('.dmb-wysiwyg__root');
                if (root) {
                    const value = this.#_editArea?.innerHTML || '';
                    this.#_build();
                    this.#_bindToolbar();
                    this.#_bindEditArea();
                    this.#_editArea.innerHTML = value;
                }
            }
            break;
        }
    }
}
