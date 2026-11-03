@php
  $wpReportTitle = trim($__env->yieldContent('report-title', $__env->yieldContent('title', 'Rapport')));
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
  <title>@yield('title', 'WAVE PLUS — Rapport')</title>
  <meta name="wp-pdf-orientation" content="landscape">
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
      /* Brand */
      --brand-dark:         #0f172a;
      --brand-navy:         #1e293b;
      --brand-accent:       #0d9488;
      --brand-accent-light: #ccfbf1;
      --brand-accent-muted: #5eead4;
      --brand-orange:       #f97316;

      /* Surfaces */
      --surface-0:  #ffffff;
      --surface-1:  #f8fafc;
      --surface-2:  #f1f5f9;
      --surface-3:  #e2e8f0;

      /* Borders */
      --border:        #e2e8f0;
      --border-strong: #cbd5e1;

      /* Text */
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
      size: A4 landscape;
    }

    body {
      font-family: var(--font-sans);
      font-size: 9pt;
      color: var(--text-primary);
      background: var(--surface-0);
      line-height: 1.45;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
      overflow-wrap: anywhere;
      word-break: break-word;
    }

    main {
      width: 100%;
      padding: 0;
    }

    /* ═══════════════════════════════════════════
       SECTION HEADER (inline title block)
    ═══════════════════════════════════════════ */
    .section-heading {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 8px;
      break-after: avoid;
      page-break-after: avoid;
    }

    .section-heading-bar {
      width: 3px;
      height: 14px;
      background: var(--brand-accent);
      border-radius: 2px;
      flex-shrink: 0;
    }

    .section-heading-text {
      font-size: 7.5pt;
      font-weight: 700;
      color: var(--text-secondary);
      text-transform: uppercase;
      letter-spacing: 0.9px;
    }

    /* ═══════════════════════════════════════════
       DATA TABLE
       Uses border-collapse: collapse + table-header-group
       so Chromium reliably repeats <thead> across pages
       without clipping or row splitting.
    ═══════════════════════════════════════════ */
    .data-table {
      width: 100%;
      border-collapse: collapse;
      table-layout: fixed;
      margin-bottom: 10px;
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
      font-size: 8.6pt;
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
       SUMMARY STATS ROW (above/below a table)
    ═══════════════════════════════════════════ */
    .stats-row {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      margin-bottom: 10px;
      break-inside: avoid;
      page-break-inside: avoid;
    }

    .stat-pill {
      display: flex;
      align-items: center;
      gap: 6px;
      padding: 6px 12px;
      border: 1px solid var(--border);
      border-radius: 6px;
      background: var(--surface-1);
    }

    .stat-pill-label {
      font-size: 6.8pt;
      font-weight: 600;
      color: var(--text-muted);
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .stat-pill-value {
      font-size: 9pt;
      font-weight: 700;
      color: var(--brand-dark);
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
