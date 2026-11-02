{{--
    Renders the QR code on each ID card's back side.

    Vendored as a plain <script src> from public/vendor/qrcode.min.js rather
    than imported through resources/js/app.js (Vite's bundle), on purpose:
    the ID card print pages then need no `npm install`/`npm run build` step
    at all to work - just PHP - which matters on a school office machine
    that may not have Node.js installed. public/vendor/qrcode.min.js is a
    pre-built, self-contained bundle of the "qrcode" npm package (see
    https://www.npmjs.com/package/qrcode) exposing a single global
    `QRCode.toString()`; nothing else on this page depends on Vite.
--}}
<script src="{{ asset('vendor/qrcode.min.js') }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-qrcode]').forEach((el) => {
            const payload = el.dataset.qrcode;
            if (!payload || typeof QRCode === 'undefined') {
                return;
            }

            QRCode.toString(payload, { type: 'svg', margin: 1, errorCorrectionLevel: 'M' }, (error, svg) => {
                if (!error) {
                    el.innerHTML = svg;
                }
            });
        });
    });
</script>
