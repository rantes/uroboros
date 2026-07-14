import { DumboTestApp } from '../../libs/dumbojs/dumbo.min.js';
import { DmbCloneComponent } from './dmb-clone-component.directive.js';

describe('DmbCloneComponent Directive', () => {
    let component = null;
    let fixture = null;

    DumboTestApp.setComponents([
        DmbCloneComponent
    ]);

    beforeEach(() => {
        fixture = DumboTestApp.fixture(DmbCloneComponent);
        fixture.setAttribute('target', 'target');
        fixture.setAttribute('clone-into', 'target');
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