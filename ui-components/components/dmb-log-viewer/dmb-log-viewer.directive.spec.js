import { DumboTestApp } from '/../../libs/dumbojs/dumbo.min.js';
import { DmbLogViewer } from './dmb-log-viewer.directive.js';

describe('DmbLogViewer Directive', () => {
    let component = null;
    let fixture = null;

    DumboTestApp.setComponents([
        DmbLogViewer
    ]);

    beforeEach(() => {
        fixture = DumboTestApp.fixture(DmbLogViewer);
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