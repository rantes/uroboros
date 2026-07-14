import { DumboTestApp } from '/../../libs/dumbojs/dumbo.min.js';
import { DmbTreeView } from './dmb-tree-view.directive.js';

describe('DmbTreeView Directive', () => {
    let component = null;
    let fixture = null;

    DumboTestApp.setComponents([
        DmbTreeView
    ]);

    beforeEach(() => {
        fixture = DumboTestApp.fixture(DmbTreeView);
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