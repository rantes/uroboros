describe('DmbLogin Directive', () => {
    let element = null;
    let inputs = null;
    let form = null;

    element = document.createElement('dmb-login');
    document.body.append(element);
    form = element.querySelector('dmb-form');
    inputs = element.querySelectorAll('input');

    beforeEach(() => {
        const callback = () => {
            console.log('Trying to login');
        };
        spyOn(form, 'callback');
        inputs[0].value = '';
        inputs[1].value = '';
    });

    it('Should render element', () => {
        expect(element).toBeDefined();
    });

    it('Should throw error on no data in both inputs', () => {
        form.submit();
        expect(form.valids).toBe(0);
    });

    it('Should throw error on no data in any input', () => {
        inputs[0].value = 'user';
        form.submit();
        expect(form.valids).toBe(1);
        inputs[0].value = '';
        inputs[1].value = 'password';
        form.submit();
        expect(form.valids).toBe(1);
    });

    it('Should try to login with username and password given', () => {
        inputs[0].value = 'user';
        inputs[1].value = 'password';
        form.submit();
        expect(form.valids).toBe(2);
        expect(form.callback).toHaveBeenCalled();
    });

});