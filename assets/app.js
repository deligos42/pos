window.showToast = function(message, type = 'info') {
    const container = document.getElementById('appToastContainer');
    if (!container || !message) {
        return;
    }

    const styles = {
        success: { className: 'text-bg-success', icon: 'bi-check-circle-fill' },
        error: { className: 'text-bg-danger', icon: 'bi-exclamation-triangle-fill' },
        danger: { className: 'text-bg-danger', icon: 'bi-exclamation-triangle-fill' },
        warning: { className: 'text-bg-warning', icon: 'bi-exclamation-circle-fill' },
        info: { className: 'text-bg-primary', icon: 'bi-info-circle-fill' }
    };
    const style = styles[type] || styles.info;
    const toast = document.createElement('div');
    toast.className = `toast app-toast align-items-center border-0 ${style.className}`;
    toast.role = 'alert';
    toast.ariaLive = 'assertive';
    toast.ariaAtomic = 'true';
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <i class="bi ${style.icon} me-2"></i>${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    `;
    container.appendChild(toast);

    const instance = new bootstrap.Toast(toast, { delay: 3500 });
    toast.addEventListener('hidden.bs.toast', () => toast.remove());
    instance.show();
};

document.querySelectorAll('.alert').forEach(alertBox => {
    const message = alertBox.textContent.trim();
    if (!message) {
        return;
    }

    const type = alertBox.classList.contains('alert-success') ? 'success'
        : alertBox.classList.contains('alert-danger') ? 'error'
        : alertBox.classList.contains('alert-warning') ? 'warning'
        : 'info';
    window.showToast(message, type);
});

(() => {
    const validateEmailInput = input => {
        if (!input) {
            return;
        }

        const value = input.value.trim();
        if (!value) {
            input.setCustomValidity('');
            return;
        }

        input.setCustomValidity(/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value) ? '' : 'Please enter a valid email address.');
    };

    document.querySelectorAll('input[type="email"]').forEach(input => {
        input.addEventListener('input', () => validateEmailInput(input));
        input.addEventListener('blur', () => validateEmailInput(input));
        input.addEventListener('change', () => validateEmailInput(input));
        validateEmailInput(input);
    });
})();

(() => {
    'use strict';
    const forms = document.querySelectorAll('.needs-validation');
    Array.prototype.slice.call(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
})();
