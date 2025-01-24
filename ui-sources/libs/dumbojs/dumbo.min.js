export const DmbEvents = {
    panelClose: {event: new Event('dmb-panel.close'), listener: 'dmb-panel.close'},
    panelOpen: {event: new Event('dmb-panel.open'), listener: 'dmb-panel.open'},
    panelClosed: {event: new Event('dmb-panel.closed'), listener: 'dmb-panel.closed'},
    panelOpened: {event: new Event('dmb-panel.opened'), listener: 'dmb-panel.opened'},
    resetValidation: {event: new Event('dmb-validation.reset'), listener: 'dmb-validation.reset'},
    validate: {event: new Event('dmb-validation.validate'), listener: 'dmb-validation.validate'},
    dialogClose: {event: new Event('dmb-dialog.close'), listener: 'dmb-dialog.close'},
    dialogOpen: {event: new Event('dmb-dialog.open'), listener: 'dmb-dialog.open'},
    pageLoaderClose: {event: new Event('dmb-page-loader.close'), listener: 'dmb-page-loader.close'},
    formSubmit: {event: new Event('dmb-form.submit'), listener: 'dmb-form.submit'},
    formBeforeValidate: {event: new Event('dmb-form.before-validate'), listener: 'dmb-form.before-validate'},
    formAfterValidate: {event: new Event('dmb-form.after-validate'), listener: 'dmb-form.after-validate'},
    afterRendered: {event: new Event('dmb.after-rendered'), listener: 'dmb.after-rendered'},
    inputChanged: {event: new Event('dmb.input-changed'), listener: 'dmb.input-changed'}
};

const vDom = document.cloneNode(true);

// eslint-disable-next-line no-unused-vars
export class DumboFactory {
    setTemplate(template) {
        this.childrenTemplate = vDom.querySelector(`#${this.constructor.name}-template`);

        if (!this.childrenTemplate) {
            this.childrenTemplate = vDom.createElement('template');
            this.childrenTemplate.id = `${this.constructor.name}-template`;
            this.childrenTemplate.innerHTML = template;
            vDom.body.appendChild(this.childrenTemplate);
        }
    }
}

export class DumboDirective extends HTMLElement {
    static selector = '';
    static template = '';
    _reactives = [];
    _onChangeActions = [];
    _values = {};
    _reactiveNodes = {};

    constructor () {
        super();
        this.setTemplate();
    }

    pre() {}

    init() {}

    setReactives() {
        if (this._reactives.length) {
            this._reactives.forEach((val) => {
                this._values[val] = this[val];
                delete this[val];
                Object.defineProperty(this, val, {
                    set(value) {
                        this._values[val] = value;
                        this.interpolate(val);
                        return true;
                    },
                    get() {
                        return this._values[val];
                    },
                    configurable: true,
                    enumerable: true
                });
            });
        }
    }

    interpolate(rVar) {
        let regex = null;
        const nodesP = this._reactiveNodes[rVar];

        regex = new RegExp(`{{${rVar}}}`, 'g');
        nodesP.forEach((pNode) => {
            pNode.cNode.data = pNode.oCont.replace(regex, this._values[rVar]);
        });
    }

    setNodes() {
        const walker = document.createTreeWalker(this, NodeFilter.SHOW_ELEMENT);
        let node = null;
        let currReactives = [];

        while(null !== (node = walker.nextNode())) {
            node.childNodes.forEach((cNode) => {
                if (cNode.nodeType === 3) {
                    currReactives = [...cNode.nodeValue.matchAll(/{{(.*?)}}/g)].map( (p) => p[1]).filter((val, i, values) => values.indexOf(val) === i);
                    if (currReactives.length) {
                        currReactives.forEach((rVar) => {
                            if (!this._reactiveNodes[rVar]) {
                                this._reactiveNodes[rVar] = [];
                            }
                            this._reactiveNodes[rVar].push({
                                cNode,
                                oCont: cNode.nodeValue
                            });
                        });
                    }
                }
            });
            if (currReactives.length) {
                this._reactives = this._reactives.concat(currReactives).map( (p) => p).filter((val, i, values) => values.indexOf(val) === i);
            }
        }
    }

    setTemplate() {
        if (this.constructor.template && this.constructor.template.length) {
            this.childrenTemplate = vDom.querySelector(`#${this.constructor.name}-template`);

            if (!this.childrenTemplate) {
                this.childrenTemplate = vDom.createElement('template');
                this.childrenTemplate.id = `${this.constructor.name}-template`;
                this.childrenTemplate.innerHTML = this.constructor.template;
                vDom.body.appendChild(this.childrenTemplate);
            }
        }
    }

    connectedCallback() {
        let temp = null;
        let transclude = null;
        let res = [];
        let rVar = null;

        this.pre();
        if (!this.hasAttribute('rendered')) {
            if (this.childrenTemplate) {
                temp = this.childrenTemplate.content.cloneNode(true);
                transclude = temp.querySelector('[transclude]');
                if (transclude) {
                    transclude.innerHTML = this.innerHTML;
                    this.innerHTML = null;
                }
            }

            if (temp) {
                this.appendChild(temp);
            }
            this.setNodes();
            this.setReactives();
            if (this._reactives.length) {
                res = this._reactives;
                while((rVar = res.shift())) {
                    this.interpolate(rVar);
                }
            }
            this.setAttribute('rendered', 'true');

            this.dispatchEvent(DmbEvents.afterRendered.event);
            this.addEventListener(DmbEvents.inputChanged.listener, () => {
                callbacks = this._onChangeActions;
                while(undefined !== (callback = callbacks.shift())) {
                    callback.call(this);
                }
            });
        }

        this.init();
    }

    generateId(limit = 6) { return Math.random().toString(36).replace(/[^a-z]+/g, '').substring(0, limit - 1); }

    onChange(callback) {
        this._onChangeActions.push(callback);
    }
}

export class DumboApp {
    components = [];

    buildComponents () {
        let tmp = this.components;
        let component = undefined;

        while(undefined !== (component = tmp.shift())) {
            if (undefined === customElements.get(component.selector)) {
                customElements.define(component.selector, component);
            }
        }
    }
}

export class DumboTest extends DumboApp {
    constructor() {
        super();
    }

    setComponents(components) {
        this.components = components;
        this.buildComponents();
    }

    fixture(component) {
        return document.createElement(component.selector);
    }

    createComponent(fixture) {
        document.body.append(fixture);

        return fixture;
    }
}

export const DumboTestApp = new DumboTest();
