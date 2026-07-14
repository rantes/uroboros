import { DumboTestApp } from '../../libs/dumbojs/dumbo.min.js';
import { DmbHelpIcon } from './dmb-help-icon.directive.js';

describe('DmbHelpIcon Directive', () => {
    let component = null;
    let fixture = null;

    DumboTestApp.setComponents([
        DmbHelpIcon
    ]);

    beforeEach(() => {
        fixture = DumboTestApp.fixture(DmbHelpIcon);
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