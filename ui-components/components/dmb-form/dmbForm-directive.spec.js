import { DumboTestApp } from '/ui-components/libs/dumbojs/dumbo.min.js';
import { DmbForm } from './dmb-form.directive.js';

describe('DmbForm Directive', () => {
    let component = null;
    let fixture = null;
    let select = null;
    let button = null;
    let inputs = null;
    let textarea = null;
    let input = null;

    DumboTestApp.setComponents([
        DmbForm
    ]);

    beforeEach(() => {
        fixture = DumboTestApp.fixture(DmbForm);
        component = DumboTestApp.createComponent(fixture);

        select = document.createElement('dmb-select');
        button = document.createElement('dmb-button');
        inputs = document.createElement('dmb-input');
        textarea = document.createElement('dmb-text-area');


        select.setAttribute('validate', 'required');
        inputs.setAttribute('validate', 'required');
        textarea.setAttribute('validate', 'required');
        button.classList.add('button');
        button.classList.add('button-primary');
        button.innerHTML = 'TestForm';


        inputs.setAttribute('label', 'label');
        component.append(inputs);
        component.append(select);
        component.append(textarea);

        button.setAttribute('type','submit');
        component.append(button);


        select.setAttribute('values', true);
        select.value = null;

        select.values = [
            {value: '', text: 'Seleccione', selected: true},
            {value: '1', text: 'Laboral'},
            {value: '2', text: 'Pension'},
            {value: '3', text: 'Accidente'}
        ];
    });

    afterEach( done => {
        component && component.remove();
        done();
    });

    it('Should render component', () => {
        expect(component).toBeDefined();
    });

    it('Should validate', (done) => {
        component.submit();
        expect(component.valids).toBe(0);
        done();
    });

    it('Should have valid inputs', (done) => {
        inputs.value = 'value';
        component.submit();
        expect(component.valids).toBe(1);
        done();
    });
});
