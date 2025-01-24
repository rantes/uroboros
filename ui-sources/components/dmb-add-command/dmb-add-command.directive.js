import { DumboDirective } from '../../libs/dumbojs/dumbo.min.js';
export class DmbAddCommand extends DumboDirective {
    static selector = 'dmb-add-command';

    init() {
        this.classList.add('icon');
        this.classList.add('button');
        this.classList.add('icon-plus1');

        this.addEventListener('click', e => {
            this.handleClick(e);
        });
    }

    handleClick(e) {
        const target = e.target;
        const id = document.createElement('input');
        const projectid = document.createElement('input');
        const command = document.createElement('dmb-input');
        const form = target.closest('dmb-content');
        let counter = parseInt(target.getAttribute('counter'));

        id.setAttribute('type', 'hidden');
        id.setAttribute('value', '');
        id.setAttribute('name', `command[${counter}][id]`);

        projectid.setAttribute('type', 'hidden');
        projectid.setAttribute('value', target.getAttribute('project-id'));
        projectid.setAttribute('name', `command[${counter}][project_id]`);

        command.setAttribute('label', 'Command');
        command.setAttribute('value', '');
        command.setAttribute('dmb-name', `command[${counter}][command]`);
        command.setAttribute('validate', 'required');

        form.appendChild(id);
        form.appendChild(projectid);
        form.appendChild(command);

        counter++;
        target.setAttribute('counter', counter);
    }
}
