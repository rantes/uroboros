export class DmbNotificationService {

    success(container, msg) {
        const notification = document.createElement('dmb-notification');

        notification.classList.add('success');
        notification.innerHTML = msg;
        container.prepend(notification);

        return true;
    }

    warning(container, msg) {
        const notification = document.createElement('dmb-notification');

        notification.classList.add('warning');
        notification.innerHTML = msg;
        container.prepend(notification);

        return true;
    }

    error(container, msg) {
        const notification = document.createElement('dmb-notification');

        notification.classList.add('error');
        notification.innerHTML = msg;
        container.prepend(notification);

        return true;
    }
}

export const dmbNotificationService = new DmbNotificationService();