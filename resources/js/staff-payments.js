const initStaffPayments = () => {
    document.querySelectorAll('[data-payroll-payment-open]').forEach((button) => {
        button.addEventListener('click', () => {
            const dialog = document.getElementById(button.dataset.payrollPaymentOpen);

            if (dialog instanceof HTMLDialogElement) {
                dialog.showModal();
            }
        });
    });

    document.querySelectorAll('[data-payroll-payment-close]').forEach((button) => {
        button.addEventListener('click', () => {
            const dialog = button.closest('dialog');

            if (dialog instanceof HTMLDialogElement) {
                dialog.close();
            }
        });
    });

    document.querySelectorAll('[data-payroll-payment-type]').forEach((select) => {
        select.addEventListener('change', () => {
            const form = select.closest('form');
            const amountInput = form?.querySelector('[data-payroll-payment-amount]');

            if (!(amountInput instanceof HTMLInputElement)) {
                return;
            }

            if (select.value === 'full') {
                amountInput.value = select.dataset.fullAmount ?? amountInput.max;
            } else {
                amountInput.focus();
                amountInput.select();
            }
        });
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initStaffPayments);
} else {
    initStaffPayments();
}
