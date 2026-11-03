@php
  $wpReportTitle = trim($__env->yieldContent('report-title', $__env->yieldContent('title', 'Fiche')));
  $wpHeaderBadge = trim(strip_tags($__env->yieldPushContent('header-badge')));
  $wpFooterCenter = trim(strip_tags($__env->yieldPushContent('footer-center')));
  $wpDocRef = trim($__env->yieldContent('doc-reference', ''));
  $wpFooterAddress = trim($__env->yieldContent('footer-address', ''));
  $wpFooterTaxId = trim($__env->yieldContent('footer-tax-id', ''));
  $wpFooterPhone = trim($__env->yieldContent('footer-phone', ''));
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('title', 'WAVE PLUS — Fiche')</title>
  <meta name="wp-pdf-orientation" content="portrait">
  <meta name="wp-pdf-title" content="{{ $wpReportTitle }}">
  @if($wpHeaderBadge !== '')
    <meta name="wp-pdf-badge" content="{{ $wpHeaderBadge }}">
  @endif
  @if($wpDocRef !== '')
    <meta name="wp-pdf-ref" content="{{ $wpDocRef }}">
  @endif
  @if($wpFooterCenter !== '')
    <meta name="wp-pdf-footer-note" content="{{ $wpFooterCenter }}">
  @endif
  @if($wpFooterAddress !== '')
    <meta name="wp-pdf-footer-address" content="{{ $wpFooterAddress }}">
  @endif
  @if($wpFooterTaxId !== '')
    <meta name="wp-pdf-footer-tax-id" content="{{ $wpFooterTaxId }}">
  @endif
  @if($wpFooterPhone !== '')
    <meta name="wp-pdf-footer-phone" content="{{ $wpFooterPhone }}">
  @endif
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --brand-dark:         #0f172a;
      --brand-navy:         #1e293b;
      --brand-accent:       #0d9488;
      --brand-accent-light: #ccfbf1;
      --brand-accent-muted: #5eead4;
      --brand-orange:       #f97316;

      --surface-0:  #ffffff;
      --surface-1:  #f8fafc;
      --surface-2:  #f1f5f9;
      --surface-3:  #e2e8f0;

      --border:        #e2e8f0;
      --border-strong: #cbd5e1;

      --text-primary:   #0f172a;
      --text-secondary: #475569;
      --text-muted:     #64748b;

      --font-sans: "Segoe UI", Inter, -apple-system, BlinkMacSystemFont, Arial, Helvetica, sans-serif;
      --font-mono: Consolas, "JetBrains Mono", "Courier New", monospace;
    }

    /*
     * Page size only — margins are owned by DocumentRenderService::PAGE_GEOMETRY
     * and applied via Browsershot ->margins() so native repeating header/footer
     * templates sit in the top/bottom margin boxes without ever overlapping body content.
     */
    @page {
      size: A4 portrait;
    }

    body {
      font-family: var(--font-sans);
      font-size: 9.5pt;
      color: var(--text-primary);
      background: var(--surface-0);
      line-height: 1.5;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
      overflow-wrap: anywhere;
      word-break: break-word;
    }

    main {
      width: 100%;
      padding: 0;
    }

    img, svg {
      max-width: 100%;
      height: auto;
    }

    /* ═══════════════════════════════════════════
       PROFILE HEADER
    ═══════════════════════════════════════════ */
    .profile-header {
      border: 1px solid var(--border);
      border-radius: 8px;
      overflow: hidden;
      margin-bottom: 12px;
      background: var(--surface-0);
      break-inside: avoid;
      page-break-inside: avoid;
    }

    .profile-header::before {
      content: '';
      display: block;
      height: 3px;
      background: linear-gradient(90deg, var(--brand-accent) 0%, var(--brand-accent-muted) 65%, transparent 100%);
    }

    .profile-header-inner {
      display: flex;
      align-items: center;
      gap: 16px;
      padding: 14px 18px;
    }

    .profile-avatar {
      width: 48px;
      height: 48px;
      border-radius: 50%;
      background: var(--brand-dark);
      color: #ffffff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 16px;
      font-weight: 700;
      letter-spacing: -0.5px;
      flex-shrink: 0;
      box-shadow: 0 0 0 2px var(--surface-0), 0 0 0 4px var(--brand-accent);
    }

    .profile-info {
      flex: 1;
      min-width: 0;
    }

    .profile-name {
      font-size: 14pt;
      font-weight: 700;
      color: var(--brand-dark);
      letter-spacing: -0.3px;
      margin-bottom: 2px;
      line-height: 1.25;
    }

    .profile-subtitle {
      font-size: 8.8pt;
      color: var(--text-secondary);
      margin-bottom: 6px;
    }

    .profile-badges {
      display: flex;
      align-items: center;
      gap: 6px;
      flex-wrap: wrap;
    }

    .profile-meta-right {
      text-align: right;
      flex-shrink: 0;
    }

    .profile-id {
      font-family: var(--font-mono);
      font-size: 9pt;
      font-weight: 600;
      color: var(--text-secondary);
    }

    .profile-id-label {
      font-size: 6.5pt;
      color: var(--text-muted);
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 2px;
    }

    /* ═══════════════════════════════════════════
       SECTION CARD
       Note: Outer .section-card intentionally allows page breaks
       when a card holds many rows (e.g. 20+ addresses, contacts,
       suppliers, or multi-paragraph descriptions), while keeping
       the title attached to the first row and each row intact.
    ═══════════════════════════════════════════ */
    .section-card {
      border: 1px solid var(--border);
      border-radius: 8px;
      margin-bottom: 12px;
      background: var(--surface-0);
    }

    .section-card--compact {
      break-inside: avoid;
      page-break-inside: avoid;
    }

    .section-card-title {
      padding: 7px 16px;
      font-size: 7.2pt;
      font-weight: 700;
      color: var(--brand-accent);
      background: var(--surface-1);
      border-bottom: 1px solid var(--border);
      border-radius: 7px 7px 0 0;
      text-transform: uppercase;
      letter-spacing: 0.9px;
      display: flex;
      align-items: center;
      gap: 8px;
      break-after: avoid;
      page-break-after: avoid;
    }

    .section-card-title::before {
      content: '';
      display: inline-block;
      width: 3px;
      height: 11px;
      background: var(--brand-accent);
      border-radius: 2px;
      flex-shrink: 0;
    }

    /* ── Key-Value Rows ── */
    .kv-table {
      width: 100%;
      border-collapse: collapse;
    }

    .kv-row {
      display: flex;
      border-bottom: 1px solid var(--border);
      break-inside: avoid;
      page-break-inside: avoid;
    }

    .kv-row:last-child {
      border-bottom: none;
      border-radius: 0 0 7px 7px;
    }

    .kv-row:nth-child(even) {
      background: var(--surface-1);
    }

    .kv-label {
      width: 35%;
      flex-shrink: 0;
      padding: 7px 16px;
      font-size: 7.2pt;
      font-weight: 600;
      color: var(--text-secondary);
      text-transform: uppercase;
      letter-spacing: 0.4px;
      display: flex;
      align-items: center;
    }

    .kv-value {
      flex: 1;
      min-width: 0;
      padding: 7px 16px 7px 0;
      font-size: 9.2pt;
      font-weight: 500;
      color: var(--text-primary);
      display: flex;
      align-items: center;
      flex-wrap: wrap;
      gap: 4px;
      overflow-wrap: anywhere;
      word-break: break-word;
    }

    /* Two-column grid variant */
    .kv-grid-2col {
      display: grid;
      grid-template-columns: 1fr 1fr;
    }

    .kv-grid-2col .kv-row {
      border-right: 1px solid var(--border);
    }

    .kv-grid-2col .kv-row:nth-child(2n) {
      border-right: none;
    }

    .kv-grid-2col .kv-label {
      width: 42%;
    }

    /* ── Description & Supplier Blocks (Catalog Item Record) ── */
    .description-text {
      padding: 10px 16px;
      font-size: 9pt;
      line-height: 1.6;
      color: var(--text-primary);
      overflow-wrap: anywhere;
      word-break: break-word;
    }

    .description-text p + p {
      margin-top: 8px;
    }

    .supplier-item {
      padding: 9px 16px;
      border-bottom: 1px solid var(--border);
      break-inside: avoid;
      page-break-inside: avoid;
    }

    .supplier-item:last-child {
      border-bottom: none;
    }

    .supplier-item:nth-child(even) {
      background: var(--surface-1);
    }

    .supplier-name {
      font-size: 9.5pt;
      font-weight: 700;
      color: var(--brand-dark);
      margin-bottom: 4px;
    }

    .supplier-details {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 6px 14px;
      font-size: 8.2pt;
      color: var(--text-secondary);
    }

    /* ═══════════════════════════════════════════
       DATA TABLE
    ═══════════════════════════════════════════ */
    .data-table {
      width: 100%;
      border-collapse: collapse;
      table-layout: fixed;
      margin-bottom: 12px;
      border: 1px solid var(--border);
    }

    .data-table thead {
      display: table-header-group;
      background: var(--brand-dark);
    }

    .data-table tfoot {
      display: table-footer-group;
    }

    .data-table tr {
      break-inside: avoid;
      page-break-inside: avoid;
    }

    .data-table th {
      padding: 7px 10px;
      text-align: left;
      background: var(--brand-dark);
      color: #f8fafc;
      font-size: 6.8pt;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.6px;
      border-right: 1px solid rgba(255, 255, 255, 0.08);
      border-bottom: 1px solid var(--brand-dark);
      overflow-wrap: break-word;
      word-break: normal;
    }

    .data-table th:last-child {
      border-right: none;
    }

    .data-table td {
      padding: 6.5px 10px;
      text-align: left;
      border-bottom: 1px solid var(--border);
      border-right: 1px solid var(--surface-2);
      vertical-align: middle;
      font-size: 8.8pt;
      overflow-wrap: break-word;
      word-break: normal;
    }

    .data-table td:last-child {
      border-right: none;
    }

    .data-table tbody tr:nth-child(even) {
      background-color: var(--surface-1);
    }

    .data-table tbody tr:nth-child(odd) {
      background-color: var(--surface-0);
    }

    .data-table thead th:first-child,
    .data-table tbody td.row-index,
    .data-table tbody td:first-child {
      padding-left: 4px;
      padding-right: 4px;
      white-space: nowrap;
      overflow-wrap: normal;
      word-break: normal;
      text-align: center;
    }

    .data-table tbody td.row-index,
    .data-table tbody td:first-child {
      color: var(--text-muted);
      font-family: var(--font-mono);
      font-size: 7.2pt;
      font-weight: 600;
      background: var(--surface-2);
      border-right: 1px solid var(--border);
    }

    /* ═══════════════════════════════════════════
       BADGES
    ═══════════════════════════════════════════ */
    .badge {
      display: inline-block;
      padding: 2px 8px;
      border-radius: 20px;
      font-size: 6.3pt;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      white-space: nowrap;
      line-height: 1.3;
    }

    .badge--success   { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
    .badge--warning   { background: #fef9c3; color: #a16207; border: 1px solid #fde68a; }
    .badge--error     { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
    .badge--info      { background: #dbeafe; color: #1d4ed8; border: 1px solid #bfdbfe; }
    .badge--primary   { background: var(--brand-accent-light); color: var(--brand-accent); border: 1px solid var(--brand-accent-muted); }
    .badge--secondary { background: var(--surface-2); color: var(--text-secondary); border: 1px solid var(--border); }
    .badge--default   { background: var(--surface-2); color: var(--text-muted); border: 1px solid var(--border); }
    .badge--orange    { background: #fff7ed; color: var(--brand-orange); border: 1px solid #fed7aa; }

    /* ═══════════════════════════════════════════
       RECORD CARD COMPAT (User Record & Legacy Templates)
    ═══════════════════════════════════════════ */
    .record-card {
      border: 1px solid var(--border);
      border-radius: 8px;
      background: var(--surface-0);
      margin-bottom: 12px;
    }

    .record-header {
      background: var(--surface-1);
      padding: 18px 20px;
      border-bottom: 1px solid var(--border);
      border-radius: 7px 7px 0 0;
      text-align: center;
      break-inside: avoid;
      page-break-inside: avoid;
    }

    .record-header .avatar {
      width: 50px;
      height: 50px;
      border-radius: 50%;
      background: var(--brand-dark);
      color: #ffffff;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 16px;
      font-weight: 700;
      margin-bottom: 8px;
      letter-spacing: -0.5px;
      border: 2px solid var(--surface-0);
      box-shadow: 0 0 0 2px var(--brand-accent);
    }

    .record-header .name {
      font-size: 14pt;
      font-weight: 700;
      color: var(--brand-dark);
      margin-bottom: 2px;
      letter-spacing: -0.3px;
    }

    .record-header .email {
      font-size: 8.8pt;
      color: var(--text-secondary);
      margin-bottom: 8px;
    }

    .record-header .badges {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      flex-wrap: wrap;
    }

    .record-body {
      padding: 0;
    }

    .record-section-title {
      padding: 7px 16px;
      font-size: 7.2pt;
      font-weight: 700;
      color: var(--brand-accent);
      background: var(--surface-1);
      border-bottom: 1px solid var(--border);
      border-top: 1px solid var(--border);
      text-transform: uppercase;
      letter-spacing: 0.9px;
      display: flex;
      align-items: center;
      gap: 8px;
      break-after: avoid;
      page-break-after: avoid;
    }

    .record-section-title::before {
      content: '';
      display: inline-block;
      width: 3px;
      height: 11px;
      background: var(--brand-accent);
      border-radius: 2px;
      flex-shrink: 0;
    }

    .record-grid {
      display: table;
      width: 100%;
      border-collapse: collapse;
      table-layout: fixed;
    }

    .record-row {
      display: table-row;
      break-inside: avoid;
      page-break-inside: avoid;
    }

    .record-row:nth-child(even) .record-label,
    .record-row:nth-child(even) .record-value {
      background: var(--surface-1);
    }

    .record-label {
      display: table-cell;
      width: 35%;
      padding: 7px 16px;
      font-size: 7.2pt;
      font-weight: 600;
      color: var(--text-secondary);
      border-bottom: 1px solid var(--border);
      text-transform: uppercase;
      letter-spacing: 0.4px;
      vertical-align: middle;
    }

    .record-value {
      display: table-cell;
      padding: 7px 16px 7px 0;
      font-size: 9.2pt;
      font-weight: 500;
      color: var(--text-primary);
      border-bottom: 1px solid var(--border);
      vertical-align: middle;
      overflow-wrap: anywhere;
      word-break: break-word;
    }

    .record-row:last-child .record-label,
    .record-row:last-child .record-value {
      border-bottom: none;
    }

    /* ═══════════════════════════════════════════
       UTILITIES
    ═══════════════════════════════════════════ */
    .text-mono      { font-family: var(--font-mono); font-size: 8pt; }
    .text-right     { text-align: right !important; }
    .text-center    { text-align: center !important; }
    .text-muted     { color: var(--text-muted); }
    .text-secondary { color: var(--text-secondary); }
    .font-semibold  { font-weight: 600; }
    .font-bold      { font-weight: 700; }
    .capitalize     { text-transform: capitalize; }
    .uppercase      { text-transform: uppercase; }

    .mt-0 { margin-top: 0; } .mt-1 { margin-top: 4px; }  .mt-2 { margin-top: 8px; }  .mt-3 { margin-top: 14px; }
    .mb-0 { margin-bottom: 0; } .mb-1 { margin-bottom: 4px; } .mb-2 { margin-bottom: 8px; } .mb-3 { margin-bottom: 14px; }

    .avoid-break  { break-inside: avoid; page-break-inside: avoid; }
    .break-before { break-before: page; page-break-before: always; }

    .summary-footer {
      margin-top: 8px;
      padding: 6px 12px;
      background: var(--surface-1);
      border: 1px solid var(--border);
      border-radius: 6px;
      font-size: 8pt;
      color: var(--text-secondary);
      text-align: right;
      break-inside: avoid;
      page-break-inside: avoid;
    }

    .summary-footer strong {
      color: var(--text-primary);
      font-weight: 700;
    }

    @media print {
      body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
  </style>
  @stack('styles')
</head>
<body>
  <main>
    @yield('content')
  </main>
</body>
</html>
