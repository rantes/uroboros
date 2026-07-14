import { DumboTestApp } from '/../../libs/dumbojs/dumbo.min.js';
import { DmbStatusBadge } from './dmb-status-badge.directive.js';

describe('DmbStatusBadge Directive', () => {
    let component = null;
    let fixture = null;

    DumboTestApp.setComponents([
        DmbStatusBadge
    ]);

    beforeEach(() => {
        fixture = DumboTestApp.fixture(DmbStatusBadge);
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