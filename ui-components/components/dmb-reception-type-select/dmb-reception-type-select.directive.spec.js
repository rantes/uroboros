import { DumboTestApp } from '/../../libs/dumbojs/dumbo.min.js';
import { DmbReceptionTypeSelect } from './dmb-reception-type-select.directive.js';

describe('DmbReceptionTypeSelect Directive', () => {
    let component = null;
    let fixture = null;

    DumboTestApp.setComponents([
        DmbReceptionTypeSelect
    ]);

    beforeEach(() => {
        fixture = DumboTestApp.fixture(DmbReceptionTypeSelect);
        component = DumboTestApp.createComponent(fixture);
    });

    afterEach( done => {
        component && component.remove();
        done();
    });

    it('Should render component', () => {
        expect(component).toBeDefined();
    });
});