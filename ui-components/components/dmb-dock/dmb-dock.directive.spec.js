import { DumboTestApp } from '../../libs/dumbojs/dumbo.min.js';
import { DmbDock } from './dmb-dock.directive.js';

describe('DmbDock Directive', () => {
    let component = null;
    let fixture = null;

    DumboTestApp.setComponents([
        DmbDock
    ]);

    beforeEach(() => {
        fixture = DumboTestApp.fixture(DmbDock);
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