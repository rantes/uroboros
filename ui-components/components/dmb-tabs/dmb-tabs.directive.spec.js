import { DumboTestApp } from '/../../libs/dumbojs/dumbo.min.js';
import { DmbTabs } from './dmb-tabs.directive.js';

describe('DmbTabs Directive', () => {
    let component = null;
    let fixture = null;

    DumboTestApp.setComponents([
        DmbTabs
    ]);

    beforeEach(() => {
        fixture = DumboTestApp.fixture(DmbTabs);
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