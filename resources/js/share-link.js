import qrcode from 'qrcode-generator';

/**
 * Alpine component for sharing a public link: renders a locally generated
 * QR code and copies the link to the clipboard with visual feedback.
 */
export function shareLink(url) {
    return {
        url,
        linkCopied: false,
        qrSrc: '',

        // Generate the QR code locally (no external service) as a data URL.
        makeQr() {
            const qr = qrcode(0, 'M');
            qr.addData(this.url);
            qr.make();
            this.qrSrc = qr.createDataURL(6, 8);
        },

        async copyLink() {
            const text = this.url;
            try {
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(text);
                } else {
                    // Fallback for non-secure contexts (e.g. http://*.test).
                    const ta = document.createElement('textarea');
                    ta.value = text;
                    ta.style.position = 'fixed';
                    ta.style.opacity = '0';
                    document.body.appendChild(ta);
                    ta.focus();
                    ta.select();
                    document.execCommand('copy');
                    document.body.removeChild(ta);
                }
                this.linkCopied = true;
                setTimeout(() => { this.linkCopied = false; }, 2000);
            } catch (e) {
                // Leave the visible link as a manual fallback.
            }
        },
    };
}
