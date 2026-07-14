const initAccountingCalendar = () => {
    document.querySelectorAll('[data-accounting-calendar-month-input]').forEach((input) => {
        input.addEventListener('change', () => {
            const form = input.closest('[data-accounting-calendar-month-form]');

            if (form instanceof HTMLFormElement && input.value) {
                form.requestSubmit();
            }
        });
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAccountingCalendar);
} else {
    initAccountingCalendar();
}
