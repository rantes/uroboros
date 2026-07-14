import { DumboTestApp } from '../../libs/dumbojs/dumbo.min.js';
import { DmbSearchPopup } from './dmb-search-popup.directive.js'

describe('DmbSearchPopup Directive', () => {
    let component = null;
    let fixture = null;

    DumboTestApp.setComponents([
        DmbSearchPopup
    ]);

    beforeEach(() => {
        fixture = DumboTestApp.fixture(DmbSearchPopup);
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