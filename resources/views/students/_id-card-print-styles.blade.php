<style>
    {{-- A4 portrait, same convention as fee-invoices/print.blade.php. --}}
    @page {
        size: A4 portrait;
        margin: 12mm;
    }

    @media print {
        .sms-sidebar, .sms-topbar, .no-print, .toast-container { display: none !important; }
        main { padding: 0 !important; }
    }

    {{-- Each card is fixed to true CR-80 card size (85.6mm x 54mm), not a
         percentage width, since these are meant to be cut out and fitted
         into standard card holders / laminating pouches. 2 columns fit
         comfortably per A4 portrait page; #id-cards-back always starts on
         a fresh page (page-break-before) so "every front, then every back,
         same order" holds regardless of how many students there are - flip
         the printed stack over and feed it through the printer again to
         print the reverse of each card. --}}
    .id-cards-page {
        display: flex;
        flex-wrap: wrap;
        gap: 4mm;
    }

    #id-cards-back {
        page-break-before: always;
    }

    .id-cards-page > .id-card:nth-child(10n+1):not(:first-child) {
        page-break-before: always;
    }

    .id-card {
        width: 85.6mm;
        height: 54mm;
        box-sizing: border-box;
        border: 1px dashed #999;
        border-radius: 3mm;
        padding: 3mm 4mm;
        page-break-inside: avoid;
        font-size: 8pt;
        line-height: 1.35;
        display: flex;
        flex-direction: column;
        background: #fff;
        color: #222;
    }

    .id-card-header {
        display: flex;
        align-items: center;
        gap: 2mm;
        border-bottom: 0.5pt solid #ccc;
        padding-bottom: 1mm;
        margin-bottom: 1mm;
    }

    .id-card-logo, .id-card-logo-placeholder {
        height: 7mm;
        width: 7mm;
        flex: 0 0 auto;
        object-fit: cover;
        border-radius: 1mm;
    }

    .id-card-logo-placeholder {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #eef1f4;
        color: #99a1a8;
    }

    .id-card-school-name {
        font-weight: 600;
        font-size: 7.5pt;
    }

    .id-card-title {
        text-align: center;
        font-size: 6.5pt;
        letter-spacing: 0.5pt;
        text-transform: uppercase;
        color: #666;
        margin-bottom: 1mm;
    }

    .id-card-body {
        display: flex;
        gap: 3mm;
        flex: 1;
        min-height: 0;
    }

    .id-card-photo {
        flex: 0 0 auto;
        width: 18mm;
        height: 22mm;
    }

    .id-card-photo img, .id-card-photo-placeholder {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border: 0.5pt solid #ccc;
    }

    .id-card-photo-placeholder {
        display: flex;
        align-items: center;
        justify-content: center;
        background: #eef1f4;
        color: #99a1a8;
        font-size: 12pt;
    }

    .id-card-fields {
        flex: 1;
        min-width: 0;
    }

    .id-card-name {
        font-weight: 700;
        font-size: 9pt;
        margin-bottom: 0.5mm;
    }

    .id-card-fields div {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .id-card-fields span {
        color: #666;
    }

    .id-card-footer {
        display: flex;
        justify-content: space-between;
        gap: 2mm;
        border-top: 0.5pt solid #ccc;
        padding-top: 1mm;
        margin-top: 1mm;
        font-size: 6.5pt;
        color: #666;
    }

    {{-- Back side --}}
    .id-card-back {
        align-items: center;
        text-align: center;
    }

    .id-card-qr-wrap {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 0;
    }

    .id-card-qr {
        width: 24mm;
        height: 24mm;
    }

    .id-card-qr svg {
        width: 100%;
        height: 100%;
    }

    .id-card-admission {
        font-weight: 600;
        font-size: 7.5pt;
        letter-spacing: 0.5pt;
    }

    .id-card-back-footer {
        width: 100%;
        border-top: 0.5pt solid #ccc;
        margin-top: 1mm;
        padding-top: 1mm;
        font-size: 6pt;
        color: #666;
    }

    .id-card-signature {
        border-top: 0.5pt solid #333;
        width: 60%;
        margin: 3mm auto 0.5mm auto;
        padding-top: 0.5mm;
    }

    @media (max-width: 900px) {
        .id-card { width: 100%; max-width: 85.6mm; }
    }
</style>
