import { DumboTestApp } from '/../../libs/dumbojs/dumbo.min.js';
import { DmbPushSubscribe } from './dmb-push-subscribe.directive.js';

describe('DmbPushSubscribe Directive', () => {
    let component = null;
    let fixture = null;

    DumboTestApp.setComponents([
        DmbPushSubscribe
    ]);

    beforeEach(() => {
        fixture = DumboTestApp.fixture(DmbPushSubscribe);
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