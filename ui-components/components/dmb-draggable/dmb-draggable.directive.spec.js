import { DumboTestApp } from '../../libs/dumbojs/dumbo.min.js';
import { DmbDraggable } from './dmb-draggable.directive.js';

describe('DmbDraggable Directive', () => {
    let component = null;
    let fixture = null;

    DumboTestApp.setComponents([
        DmbDraggable
    ]);

    beforeEach(() => {
        fixture = DumboTestApp.fixture(DmbDraggable);
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