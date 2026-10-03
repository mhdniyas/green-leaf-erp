<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.querySelector('[data-share-modal]');
    if (!modal) return;

    const shareUrl = modal.dataset.shareUrl || window.location.href;
    const pdfUrl = modal.dataset.pdfUrl || '';
    const downloadPdfUrl = modal.dataset.downloadPdfUrl || (pdfUrl + (pdfUrl.includes('?') ? '&' : '?') + 'download=1');
    const invoiceNumber = modal.dataset.invoiceNumber || 'Invoice';
    const shopName = modal.dataset.shopName || 'Shop';
    const invoiceTotal = modal.dataset.invoiceTotal || '';
    const shareText = modal.dataset.shareText || `Green Leaf — Shop Bill\nInvoice: ${invoiceNumber}\nShop: ${shopName}\nTotal: ${invoiceTotal}\n\nView Bill: ${shareUrl}`;
    const toast = modal.querySelector('[data-share-toast]');

    const showToast = (message, duration = 3000) => {
        if (!toast) return;
        toast.textContent = message;
        toast.classList.remove('hidden');
        setTimeout(() => {
            toast.classList.add('hidden');
        }, duration);
    };

    const openModal = () => {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    };

    const closeModal = () => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    };

    // Open/close listeners
    document.querySelectorAll('[data-open-share-modal]').forEach(btn => btn.addEventListener('click', openModal));
    modal.querySelectorAll('[data-close-share-modal]').forEach(btn => btn.addEventListener('click', closeModal));

    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
            closeModal();
        }
    });

    // Fetch PDF File as File object for Web Share API
    const fetchPdfFile = async () => {
        try {
            const response = await fetch(downloadPdfUrl, { credentials: 'same-origin' });
            if (!response.ok) throw new Error('PDF fetch failed');
            const blob = await response.blob();
            return new File([blob], `Shop-Invoice-${invoiceNumber}.pdf`, { type: 'application/pdf' });
        } catch (err) {
            console.warn('Could not fetch PDF file:', err);
            return null;
        }
    };

    // Share action handler (WhatsApp / Native Share)
    const shareBtn = modal.querySelector('[data-share-action="share-whatsapp"]');
    shareBtn?.addEventListener('click', async () => {
        let shared = false;

        // Try native Web Share on mobile devices that support file sharing
        if (navigator.share) {
            try {
                showToast('Preparing share...');
                const pdfFile = await fetchPdfFile();
                if (pdfFile && navigator.canShare && navigator.canShare({ files: [pdfFile] })) {
                    await navigator.share({
                        files: [pdfFile],
                        title: `Shop Bill ${invoiceNumber}`,
                        text: `Shop Bill: ${shopName} (${invoiceNumber}) - ${invoiceTotal}`,
                    });
                    shared = true;
                    closeModal();
                    return;
                }
            } catch (err) {
                if (err.name === 'AbortError') {
                    return; // User cancelled share sheet
                }
                console.warn('Web Share failed, switching to WhatsApp web/app link:', err);
            }
        }

        // Direct WhatsApp fallback using reliable wa.me endpoint
        if (!shared) {
            const waUrl = `https://wa.me/?text=${encodeURIComponent(shareText)}`;
            window.open(waUrl, '_blank', 'noopener,noreferrer');
        }
    });
});
</script>
