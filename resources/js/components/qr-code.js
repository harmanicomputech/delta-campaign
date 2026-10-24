// Renders a QR code (e.g. an otpauth:// URI) as inline SVG. Loaded on demand.
export default function qrCode(value) {
    return {
        async init() {
            const { default: QRCode } = await import('qrcode');
            this.$el.innerHTML = await QRCode.toString(value, { type: 'svg', margin: 1, errorCorrectionLevel: 'M' });
            this.$el.querySelector('svg')?.setAttribute('aria-hidden', 'true');
        },
    };
}
