const statusClasses = {
    present: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    half_day: 'border-amber-200 bg-amber-50 text-amber-800',
    leave: 'border-cyan-200 bg-cyan-50 text-cyan-800',
    absent: 'border-rose-200 bg-rose-50 text-rose-800',
};

const firstError = (payload) => {
    const errors = payload.errors ?? {};

    return Object.values(errors).flat()[0] ?? payload.message ?? 'Unable to update attendance.';
};

const updateForm = (form, payload) => {
    const attendance = payload.attendance ?? {};
    const badge = form.querySelector('[data-attendance-status-badge]');
    const timeElem = form.querySelector('[data-attendance-time]');
    const reasonElem = form.querySelector('[data-attendance-reason]');
    const notesInput = form.querySelector('[data-attendance-notes-input]');

    if (badge) {
        badge.textContent = attendance.status_label ?? '✓ Present';
        badge.className = `rounded px-2 py-0.5 text-[10px] font-black uppercase border inline-block ${statusClasses[attendance.status] ?? 'border-slate-200 bg-slate-100 text-slate-600'}`;
    }

    if (timeElem && attendance.marked_at) {
        timeElem.textContent = `· ${attendance.marked_at}`;
        timeElem.classList.remove('hidden');
    }

    if (reasonElem) {
        if (attendance.notes) {
            reasonElem.textContent = `Reason: ${attendance.notes}`;
            reasonElem.classList.remove('hidden');
        } else {
            reasonElem.textContent = '';
            reasonElem.classList.add('hidden');
        }
    }

    if (notesInput) {
        notesInput.value = attendance.notes ?? '';
    }

    form.dispatchEvent(new CustomEvent('attendance-saved', { detail: attendance, bubbles: true }));
};

const submitAttendance = async (form, triggerButton = null) => {
    if (!form.reportValidity()) {
        return;
    }

    const activeBtn = triggerButton || form.querySelector('[data-attendance-submit]');
    const originalText = activeBtn?.textContent ?? '';

    if (activeBtn) {
        activeBtn.setAttribute('disabled', 'disabled');
        activeBtn.textContent = 'Saving...';
    }

    try {
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
            window.showAppAlert?.(firstError(payload));

            return;
        }

        updateForm(form, payload);
    } catch (error) {
        window.showAppAlert?.('Unable to update attendance. Check the connection and try again.');
    } finally {
        if (activeBtn) {
            activeBtn.removeAttribute('disabled');
            if (activeBtn.textContent === 'Saving...') {
                activeBtn.textContent = originalText;
            }
        }
    }
};

window.submitAttendanceStatus = (form, status, notes = '', triggerButton = null) => {
    if (!form) {
        return;
    }

    let statusInput = form.querySelector('input[name="status"]');
    if (!statusInput) {
        statusInput = document.createElement('input');
        statusInput.type = 'hidden';
        statusInput.name = 'status';
        form.appendChild(statusInput);
    }
    statusInput.value = status;

    let notesInput = form.querySelector('input[name="notes"]');
    if (!notesInput) {
        notesInput = document.createElement('input');
        notesInput.type = 'hidden';
        notesInput.name = 'notes';
        form.appendChild(notesInput);
    }
    notesInput.value = notes;

    submitAttendance(form, triggerButton);
};

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-owned-shop-attendance-form]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            submitAttendance(form);
        });
    });
});
