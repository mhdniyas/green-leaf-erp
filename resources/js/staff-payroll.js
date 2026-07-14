const moneyText = (value) => `Rs. ${Number(value || 0).toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
})}`;

const setText = (element, text, className = 'text-slate-500') => {
    if (!element) {
        return;
    }

    element.textContent = text;
    element.className = `text-xs font-semibold ${className}`;
};

const formValues = (form) => {
    return {
        overrideAmount: form.querySelector('[data-payroll-override-amount]')?.value.trim() ?? '',
        overrideReason: form.querySelector('[data-payroll-override-reason]')?.value.trim() ?? '',
    };
};

const isDirty = (form) => {
    const values = formValues(form);

    return values.overrideAmount !== (form.dataset.initialOverride ?? '')
        || values.overrideReason !== (form.dataset.initialReason ?? '');
};

const updateRun = (article, payload) => {
    const itemFinal = article.querySelector(`[data-payroll-item-final="${payload.item.id}"]`);

    if (itemFinal) {
        itemFinal.textContent = payload.item.final_amount_formatted ?? moneyText(payload.item.final_amount);
    }

    const runTotal = article.querySelector('[data-payroll-run-total]');

    if (runTotal) {
        runTotal.textContent = payload.run.net_amount_formatted ?? moneyText(payload.run.net_amount);
    }

    (payload.categories ?? []).forEach((category) => {
        article.querySelectorAll('[data-payroll-category-total]').forEach((categoryTotal) => {
            if (categoryTotal.dataset.payrollCategoryTotal === category.name) {
                categoryTotal.textContent = category.total_formatted ?? moneyText(category.total);
            }
        });
    });
};

const validationMessage = (payload) => {
    const errors = payload.errors ?? {};
    const firstError = Object.values(errors).flat()[0];

    return firstError ?? payload.message ?? 'Unable to save payroll change.';
};

const saveForm = async (form) => {
    if (!form.reportValidity()) {
        return false;
    }

    const article = form.closest('[data-payroll-run]');
    const button = form.querySelector('[data-payroll-save-button]');
    const status = form.querySelector('[data-payroll-form-status]');
    const originalButtonText = button?.textContent ?? 'Save';

    button?.setAttribute('disabled', 'disabled');
    if (button) {
        button.textContent = 'Saving';
    }
    setText(status, 'Saving...');

    const response = await fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
        setText(status, validationMessage(payload), 'text-red-600');
        button?.removeAttribute('disabled');
        if (button) {
            button.textContent = originalButtonText;
        }

        return false;
    }

    if (article) {
        updateRun(article, payload);
    }

    const values = formValues(form);
    form.dataset.initialOverride = values.overrideAmount;
    form.dataset.initialReason = values.overrideReason;
    setText(status, payload.message ?? 'Saved.', 'text-emerald-700');
    button?.removeAttribute('disabled');
    if (button) {
        button.textContent = originalButtonText;
    }

    return true;
};

const saveAll = async (button) => {
    const article = button.closest('[data-payroll-run]');

    if (!article) {
        return;
    }

    const status = article.querySelector('[data-payroll-run-status]');
    const dirtyForms = [...article.querySelectorAll('[data-payroll-override-form]')].filter(isDirty);

    if (dirtyForms.length === 0) {
        setText(status, 'No changes to save.');

        return;
    }

    const originalButtonText = button.textContent;
    button.setAttribute('disabled', 'disabled');
    button.textContent = 'Saving all';
    setText(status, `Saving ${dirtyForms.length} change${dirtyForms.length === 1 ? '' : 's'}...`);

    for (const form of dirtyForms) {
        const saved = await saveForm(form);

        if (!saved) {
            setText(status, 'Fix the highlighted row, then save again.', 'text-red-600');
            button.removeAttribute('disabled');
            button.textContent = originalButtonText;

            return;
        }
    }

    setText(status, 'All changes saved.', 'text-emerald-700');
    button.removeAttribute('disabled');
    button.textContent = originalButtonText;
};

const initStaffPayroll = () => {
    document.querySelectorAll('[data-payroll-override-form]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            await saveForm(form);
        });
    });

    document.querySelectorAll('[data-payroll-save-all]').forEach((button) => {
        button.addEventListener('click', async () => {
            await saveAll(button);
        });
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initStaffPayroll);
} else {
    initStaffPayroll();
}
