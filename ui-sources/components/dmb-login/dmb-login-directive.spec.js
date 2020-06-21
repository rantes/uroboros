describe('DmbLogin Directive', () => {
    let element = null;
    let inputs = null;
    let form = null;
    // eslint-disable-next-line no-unused-vars
    let handleLogin = null; // this var is actually used for spy purposes
    
    element = document.createElement('dmb-login');
    document.body.append(element);
    form = element.querySelector('dmb-form');
    inputs = element.querySelectorAll('input');

    beforeEach(() => {
        inputs[0].value = '';
        inputs[1].value = '';
    });

    it('Should render element', () => {
        expect(element).toBeDefined();
    });

    it('Should throw error on no data in both inputs', () => {
        element.loginClick();
        expect(form.valids).toBe(0);
    });

    it('Should throw error on no data in any input', () => {
        inputs[0].value = 'user';
        element.loginClick();
        expect(form.valids).toBe(1);
        inputs[0].value = '';
        inputs[1].value = 'password';
        element.loginClick();
        expect(form.valids).toBe(1);
    });

    it('Should try to login with username and password given', () => {
        handleLogin = function(target) {
            console.log(`trying to login to ${target}`);
        };

        spyOn(element, 'handleLogin');
        inputs[0].value = 'user';
        inputs[1].value = 'password';
        element.loginClick();
        expect(form.valids).toBe(2);
        expect(element.handleLogin).toHaveBeenCalled();
    });
});