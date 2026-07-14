import { DumboTestApp } from '../../libs/dumbojs/dumbo.min.js';
import { DmbProperty } from './dmb-property.directive.js';

describe('DmbProperty Directive', () => {
    let component = null;
    let fixture = null;

    DumboTestApp.setComponents([
        DmbProperty
    ]);

    beforeEach(() => {
        fixture = DumboTestApp.fixture(DmbProperty);
        component = DumboTestApp.createComponent(fixture);
    });

    afterEach( done => {
        component && component.remove();
        done();
    });


    it('Should render element', () => {
        expect(component).toBeDefined();
    });
});