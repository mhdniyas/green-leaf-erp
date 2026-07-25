const statusClasses = {
    present: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    half_day: 'border-amber-200 bg-amber-50 text-amber-700',
    leave: 'border-cyan-200 bg-cyan-50 text-cyan-700',
    absent: 'border-rose-200 bg-rose-50 text-rose-700',
};

const baseBadgeClasses = 'rounded-full border px-2 py-1 text-[10px] font-black uppercase';

const firstError = (payload) => {
    const errors = payload.errors ?? {};

    return Object.values(errors).flat()[0] ?? payload.message ?? 'Unable to update attendance.';
};

const marker = (text, classes) => {
    const element = document.createElement('span');
    element.className = `rounded-full border px-3 py-1 ${classes}`;
    element.textContent = text;

    return element;
};

const updateMarkers = (form, attendance) => {
    const markers = form.querySelector('[data-attendance-markers]');

    if (!markers) {
        return;
    }

    markers.replaceChildren();

    if (attendance.checked_in_at) {
        markers.appendChild(marker(`Checked in ${attendance.checked_in_at}`, 'border-emerald-200 bg-emerald-50 text-emerald-800'));
    }

    if (attendance.latest_mark_at) {
        markers.appendChild(marker(`Latest mark ${attendance.latest_mark_at}`, 'border-slate-200 bg-white'));
    }

    if (attendance.changed_at) {
        markers.appendChild(marker(`Changed ${attendance.changed_at}`, 'border-amber-200 bg-amber-50 text-amber-800'));
    }

    markers.classList.toggle('hidden', markers.children.length === 0);
};

const updateForm = (form, payload) => {
    const attendance = payload.attendance ?? {};
    const badge = form.querySelector('[data-attendance-status-badge]');
    const button = form.querySelector('[data-attendance-submit]');

    if (badge) {
        badge.textContent = attendance.status_label ?? 'checked in';
        badge.className = `${baseBadgeClasses} ${statusClasses[attendance.status] ?? 'border-slate-200 bg-slate-100 text-slate-700'}`;
    }

    updateMarkers(form, attendance);

    if (button) {
        button.textContent = attendance.button_label ?? 'Update Check-In';
    }
};

const submitAttendance = async (form) => {
    if (!form.reportValidity()) {
        return;
    }

    const button = form.querySelector('[data-attendance-submit]');
    const originalText = button?.textContent ?? 'Check In';

    button?.setAttribute('disabled', 'disabled');
    if (button) {
        button.textContent = 'Saving';
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
        button?.removeAttribute('disabled');
        if (button && button.textContent === 'Saving') {
            button.textContent = originalText;
        }
    }
};

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-owned-shop-attendance-form]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            submitAttendance(form);
        });
    });
});
