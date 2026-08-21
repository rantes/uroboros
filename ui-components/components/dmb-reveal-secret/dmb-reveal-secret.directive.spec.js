import { DumboTestApp } from '../../libs/dumbojs/dumbo.min.js';
import { DmbRevealSecret } from './dmb-reveal-secret.directive.js';

describe('DmbRevealSecret Directive', () => {
    let component = null;
    let fixture = null;

    DumboTestApp.setComponents([
        DmbRevealSecret
    ]);

    beforeEach(() => {
        fixture = DumboTestApp.fixture(DmbRevealSecret);
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
