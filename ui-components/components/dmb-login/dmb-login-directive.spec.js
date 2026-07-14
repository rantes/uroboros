import { DumboTestApp } from '../../libs/dumbojs/dumbo.min.js';
import { DmbLogin } from './dmb-login.directive.js';

describe('DmbLogin Directive', () => {
    let element = null;
    let fixture = null;

    DumboTestApp.setComponents([
        DmbLogin
    ]);

    beforeEach((done) => {
        fixture = DumboTestApp.fixture(DmbLogin);
        element = DumboTestApp.createComponent(fixture);
        done();
    });

    afterEach( done => {
        element && element.remove();
        done();
    });

    it('Should render element', (done) => {
        expect(element).toBeDefined();
        done();
    });
});