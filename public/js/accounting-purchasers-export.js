(() => {
    const exportRoot = document.querySelector('[data-purchasers-export]');

    if (!exportRoot) {
        return;
    }

    const tableId = exportRoot.getAttribute('data-export-table-id') || '';
    const reportTitle = exportRoot.getAttribute('data-export-title') || 'Purchasers Ledger';
    const filename = exportRoot.getAttribute('data-export-filename') || 'purchasers-ledger';
    const table = document.getElementById(tableId);

    if (!table) {
        return;
    }

    const exportCsv = () => {
        const rows = Array.from(table.querySelectorAll('tr')).map((row) =>
            Array.from(row.querySelectorAll('th, td')).map((cell) => `"${cell.innerText.trim().replace(/"/g, '""')}"`).join(',')
        );

        const blob = new Blob([rows.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `${filename}.csv`;
        link.click();
        URL.revokeObjectURL(url);
    };

    const exportPdf = () => {
        const popup = window.open('', '_blank', 'width=1200,height=900');

        if (!popup) {
            return;
        }

        popup.document.open();
        popup.document.write('<!DOCTYPE html><html><head><title>' + reportTitle + '</title><style>body{font-family:Arial,sans-serif;padding:24px;color:#0f172a}h1{margin:0 0 16px;font-size:24px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #cbd5e1;padding:10px 12px;font-size:12px;text-align:left}th{background:#0f172a;color:#fff;text-transform:uppercase;letter-spacing:.08em}</style></head><body></body></html>');
        popup.document.close();

        const title = popup.document.createElement('h1');
        title.textContent = reportTitle;
        popup.document.body.appendChild(title);
        popup.document.body.appendChild(table.cloneNode(true));
        popup.focus();
        popup.print();
    };

    document.querySelectorAll('[data-export]').forEach((button) => {
        button.addEventListener('click', () => {
            const format = button.getAttribute('data-export');

            if (format === 'excel') {
                exportCsv();
            }

            if (format === 'pdf') {
                exportPdf();
            }
        });
    });
})();
