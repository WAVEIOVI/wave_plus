@php
  $wpReportTitle = trim($__env->yieldContent('report-title', $__env->yieldContent('title', 'Document')));
  $wpDocTypeCode = trim($__env->yieldContent('doc-type-code', ''));
  $wpDocRef = trim($__env->yieldContent('doc-reference', ''));
  $wpFooterAddress = trim($__env->yieldContent('footer-address', 'Sfax, Tunisie'));
  $wpFooterTaxId = trim($__env->yieldContent('footer-tax-id', ''));
  $wpFooterPhone = trim($__env->yieldContent('footer-phone', ''));
  $wpFooterNote = trim(strip_tags($__env->yieldPushContent('footer-center')));
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('title', 'WAVE PLUS — Document')</title>
  <meta name="wp-pdf-orientation" content="portrait">
  <meta name="wp-pdf-title" content="{{ $wpReportTitle }}">
  @if($wpDocTypeCode !== '')
    <meta name="wp-pdf-badge" content="{{ $wpDocTypeCode }}">
  @endif
  @if($wpDocRef !== '')
    <meta name="wp-pdf-ref" content="{{ $wpDocRef }}">
  @endif
  @if($wpFooterAddress !== '')
    <meta name="wp-pdf-footer-address" content="{{ $wpFooterAddress }}">
  @endif
  @if($wpFooterTaxId !== '' && $wpFooterTaxId !== '—')
    <meta name="wp-pdf-footer-tax-id" content="{{ $wpFooterTaxId }}">
  @endif
  @if($wpFooterPhone !== '' && $wpFooterPhone !== '—')
    <meta name="wp-pdf-footer-phone" content="{{ $wpFooterPhone }}">
  @endif
  @if($wpFooterNote !== '')
    <meta name="wp-pdf-footer-note" content="{{ $wpFooterNote }}">
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

    /* ═══════════════════════════════════════════
       DOCUMENT HERO — Full company + document identity block
    ═══════════════════════════════════════════ */
    .doc-hero {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 20px;
      margin-bottom: 14px;
      break-inside: avoid;
      page-break-inside: avoid;
    }

    /* Left: company identity */
    .doc-hero-company {
      flex: 1;
      min-width: 0;
    }

    .doc-hero-logo-wrap {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 8px;
    }

    .doc-hero-logo-box {
      display: flex;
      align-items: center;
      justify-content: flex-start;
      flex-shrink: 0;
    }

    .doc-hero-logo-box img {
      height: 32px;
      width: auto;
      display: block;
    }

    .doc-hero-company-name {
      font-size: 13pt;
      font-weight: 700;
      color: var(--brand-dark);
      letter-spacing: -0.3px;
      line-height: 1.2;
    }

    .doc-hero-company-tagline {
      font-size: 7.2pt;
      color: var(--text-muted);
      letter-spacing: 0.2px;
    }

    .doc-hero-address {
      font-size: 8pt;
      color: var(--text-secondary);
      line-height: 1.65;
      margin-top: 2px;
    }

    .doc-hero-address strong {
      font-weight: 600;
      color: var(--text-primary);
    }

    /* Right: document identity */
    .doc-hero-document {
      flex-shrink: 0;
      text-align: right;
      max-width: 52%;
    }

    .doc-type-label {
      font-size: 7pt;
      font-weight: 700;
      color: var(--text-muted);
      text-transform: uppercase;
      letter-spacing: 1.4px;
      margin-bottom: 4px;
    }

    .doc-type-title {
      font-size: 18pt;
      font-weight: 800;
      color: var(--brand-dark);
      letter-spacing: -0.6px;
      line-height: 1.1;
      margin-bottom: 8px;
    }

    .doc-ref-block {
      display: inline-block;
      background: var(--brand-dark);
      color: #ffffff;
      border-radius: 6px;
      padding: 5px 12px;
      margin-bottom: 8px;
    }

    .doc-ref-block-label {
      font-size: 6pt;
      font-weight: 600;
      color: rgba(255, 255, 255, 0.65);
      text-transform: uppercase;
      letter-spacing: 0.9px;
      margin-bottom: 1px;
    }

    .doc-ref-block-value {
      font-family: var(--font-mono);
      font-size: 10.5pt;
      font-weight: 700;
      color: #ffffff;
      letter-spacing: 0.4px;
    }

    .doc-meta-pills {
      display: flex;
      justify-content: flex-end;
      gap: 6px;
      flex-wrap: wrap;
      margin-top: 4px;
    }

    .doc-meta-pill {
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      border: 1px solid var(--border);
      border-radius: 5px;
      padding: 4px 10px;
      background: var(--surface-1);
    }

    .doc-meta-pill-label {
      font-size: 5.8pt;
      font-weight: 600;
      color: var(--text-muted);
      text-transform: uppercase;
      letter-spacing: 0.6px;
    }

    .doc-meta-pill-value {
      font-size: 8.5pt;
      font-weight: 600;
      color: var(--text-primary);
    }

    /* ═══════════════════════════════════════════
       HORIZONTAL SEPARATOR
    ═══════════════════════════════════════════ */
    .doc-separator {
      height: 2px;
      background: linear-gradient(90deg, var(--brand-accent) 0%, var(--brand-accent-muted) 50%, var(--border) 100%);
      border-radius: 2px;
      margin-bottom: 12px;
    }

    /* ═══════════════════════════════════════════
       PARTIES ROW — Fournisseur / Livrer à
    ═══════════════════════════════════════════ */
    .parties-row {
      display: flex;
      gap: 10px;
      margin-bottom: 12px;
      break-inside: avoid;
      page-break-inside: avoid;
    }

    .party-block {
      flex: 1;
      min-width: 0;
      border: 1px solid var(--border);
      border-radius: 7px;
      overflow: hidden;
      break-inside: avoid;
      page-break-inside: avoid;
    }

    .party-block-header {
      padding: 5px 14px;
      background: var(--surface-2);
      border-bottom: 1px solid var(--border);
      display: flex;
      align-items: center;
      gap: 7px;
    }

    .party-block-header-dot {
      width: 7px;
      height: 7px;
      border-radius: 50%;
      background: var(--brand-accent);
      flex-shrink: 0;
    }

    .party-block-header-dot--vendor { background: var(--brand-orange); }
    .party-block-header-dot--ship   { background: #8b5cf6; }

    .party-block-header-label {
      font-size: 6.5pt;
      font-weight: 700;
      color: var(--text-secondary);
      text-transform: uppercase;
      letter-spacing: 0.8px;
    }

    .party-block-body {
      padding: 9px 14px;
    }

    .party-name {
      font-size: 10pt;
      font-weight: 700;
      color: var(--brand-dark);
      margin-bottom: 3px;
      letter-spacing: -0.2px;
    }

    .party-detail {
      font-size: 8pt;
      color: var(--text-secondary);
      line-height: 1.65;
    }

    .party-detail strong {
      color: var(--text-primary);
      font-weight: 600;
    }

    /* ═══════════════════════════════════════════
       DOC META STRIP — terms, currency, dates…
    ═══════════════════════════════════════════ */
    .doc-meta-strip {
      display: flex;
      flex-wrap: wrap;
      border: 1px solid var(--border);
      border-radius: 7px;
      overflow: hidden;
      margin-bottom: 12px;
      background: var(--surface-1);
      break-inside: avoid;
      page-break-inside: avoid;
    }

    .doc-meta-item {
      flex: 1 1 0;
      min-width: 90px;
      padding: 7px 12px;
      border-right: 1px solid var(--border);
    }

    .doc-meta-item:last-child { border-right: none; }

    .doc-meta-item-label {
      font-size: 6pt;
      font-weight: 700;
      color: var(--text-muted);
      text-transform: uppercase;
      letter-spacing: 0.7px;
      margin-bottom: 2px;
    }

    .doc-meta-item-value {
      font-size: 8.8pt;
      font-weight: 600;
      color: var(--text-primary);
    }

    .doc-meta-item-value.mono {
      font-family: var(--font-mono);
      font-size: 8.3pt;
    }

    /* ═══════════════════════════════════════════
       SECTION LABEL (inline)
    ═══════════════════════════════════════════ */
    .section-label {
      display: flex;
      align-items: center;
      gap: 7px;
      margin-bottom: 6px;
      break-after: avoid;
      page-break-after: avoid;
    }

    .section-label-bar {
      width: 3px;
      height: 12px;
      background: var(--brand-accent);
      border-radius: 2px;
    }

    .section-label-text {
      font-size: 6.8pt;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.9px;
      color: var(--text-secondary);
    }

    /* ═══════════════════════════════════════════
       LINES TABLE (procurement items)
       Uses border-collapse: collapse + table-header-group
       so Chromium repeats <thead> cleanly across pages.
    ═══════════════════════════════════════════ */
    .lines-table {
      width: 100%;
      border-collapse: collapse;
      table-layout: fixed;
      border: 1px solid var(--border);
      margin-bottom: 12px;
    }

    .lines-table thead {
      display: table-header-group;
      background: var(--brand-dark);
    }

    .lines-table tfoot {
      display: table-footer-group;
    }

    .lines-table tr {
      break-inside: avoid;
      page-break-inside: avoid;
    }

    .lines-table th {
      padding: 7px 8px;
      text-align: left;
      background: var(--brand-dark);
      color: #f8fafc;
      font-size: 6.4pt;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.6px;
      border-right: 1px solid rgba(255, 255, 255, 0.08);
      border-bottom: 1px solid var(--brand-dark);
      overflow-wrap: break-word;
      word-break: normal;
    }

    .lines-table th:last-child { border-right: none; }
    .lines-table th.text-right { text-align: right; }

    .lines-table td {
      padding: 7px 8px;
      border-bottom: 1px solid var(--border);
      border-right: 1px solid var(--surface-2);
      vertical-align: top;
      font-size: 8.4pt;
      overflow-wrap: break-word;
      word-break: normal;
    }

    .lines-table td:last-child { border-right: none; }

    .lines-table tbody tr:nth-child(even) { background: var(--surface-1); }
    .lines-table tbody tr:nth-child(odd)  { background: var(--surface-0); }

    .lines-table thead th:first-child,
    .lines-table td.line-num {
      padding-left: 4px;
      padding-right: 4px;
      white-space: nowrap;
      overflow-wrap: normal;
      word-break: normal;
      text-align: center;
      width: 30px;
    }

    .lines-table td.line-num {
      color: var(--text-muted);
      font-family: var(--font-mono);
      font-size: 6.8pt;
      background: var(--surface-2);
      border-right: 1px solid var(--border);
      font-weight: 600;
    }

    .lines-table td.text-right { text-align: right; }
    .lines-table td.amount     { font-family: var(--font-mono); font-size: 8.2pt; font-weight: 600; text-align: right; white-space: nowrap; }

    .line-desc { font-weight: 600; color: var(--brand-dark); overflow-wrap: anywhere; word-break: break-word; }
    .line-ref  { font-family: var(--font-mono); font-size: 7.2pt; color: var(--text-muted); margin-top: 2px; }

    /* Fulfillment / QC badge inside table */
    .line-fulfillment {
      display: inline-block;
      font-size: 6.2pt;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.4px;
      padding: 1px 6px;
      border-radius: 10px;
      white-space: nowrap;
    }

    /* ═══════════════════════════════════════════
       TOTALS + TERMS ROW
    ═══════════════════════════════════════════ */
    .bottom-row {
      display: flex;
      gap: 12px;
      margin-bottom: 12px;
      break-inside: avoid;
      page-break-inside: avoid;
    }

    /* Terms block — left */
    .doc-terms {
      flex: 1;
      min-width: 0;
    }

    .doc-terms-box {
      border: 1px solid var(--border);
      border-radius: 7px;
      padding: 10px 14px;
      background: var(--surface-1);
      font-size: 7.5pt;
      color: var(--text-secondary);
      line-height: 1.65;
      overflow-wrap: anywhere;
      word-break: break-word;
    }

    .doc-terms-box strong {
      display: block;
      font-size: 6.5pt;
      text-transform: uppercase;
      letter-spacing: 0.7px;
      color: var(--text-muted);
      margin-bottom: 4px;
      font-weight: 700;
    }

    /* Totals block — right */
    .totals-block {
      width: 220px;
      flex-shrink: 0;
    }

    .totals-table {
      width: 100%;
      border: 1px solid var(--border);
      border-radius: 7px;
      overflow: hidden;
      border-collapse: separate;
      border-spacing: 0;
    }

    .totals-row {
      display: flex;
    }

    .totals-row td {
      padding: 8px 14px;
      border-bottom: 1px solid var(--border);
      font-size: 8.8pt;
    }

    .totals-row:last-child td { border-bottom: none; }

    .totals-label {
      flex: 1;
      color: var(--text-secondary);
      font-size: 8pt;
      font-weight: 500;
    }

    .totals-value {
      text-align: right;
      font-family: var(--font-mono);
      font-size: 8.5pt;
      font-weight: 600;
      color: var(--text-primary);
      flex-shrink: 0;
      min-width: 90px;
    }

    .totals-divider {
      height: 1px;
      background: var(--border);
    }

    .totals-row--grand {
      background: var(--brand-dark);
    }

    .totals-row--grand .totals-label {
      color: rgba(255, 255, 255, 0.75);
      font-weight: 600;
    }

    .totals-row--grand .totals-value {
      color: #ffffff;
      font-size: 10pt;
    }

    .totals-currency {
      font-size: 6.5pt;
      font-weight: 700;
      color: var(--text-muted);
      text-transform: uppercase;
      margin-left: 4px;
    }

    /* ═══════════════════════════════════════════
       SIGNATURE BLOCK
    ═══════════════════════════════════════════ */
    .signatures-row {
      display: flex;
      gap: 10px;
      margin-bottom: 8px;
      break-inside: avoid;
      page-break-inside: avoid;
    }

    .signature-box {
      flex: 1;
      min-width: 0;
      border: 1px solid var(--border);
      border-radius: 7px;
      overflow: hidden;
      break-inside: avoid;
      page-break-inside: avoid;
    }

    .signature-box-header {
      padding: 5px 12px;
      background: var(--surface-2);
      border-bottom: 1px solid var(--border);
      font-size: 6.5pt;
      font-weight: 700;
      color: var(--text-secondary);
      text-transform: uppercase;
      letter-spacing: 0.7px;
    }

    .signature-box-body {
      height: 66px;
      padding: 8px 12px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }

    .signature-name {
      font-size: 8pt;
      font-weight: 600;
      color: var(--text-secondary);
    }

    .signature-date {
      font-size: 7pt;
      color: var(--text-muted);
      font-family: var(--font-mono);
    }

    .signature-stamp-area {
      height: 32px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .signature-stamp-line {
      flex: 1;
      height: 1px;
      border-bottom: 1px dashed var(--border-strong);
    }

    .signature-stamp-label {
      font-size: 6pt;
      color: var(--text-muted);
      text-transform: uppercase;
      letter-spacing: 0.6px;
      white-space: nowrap;
    }

    /* ═══════════════════════════════════════════
       APPROVAL BAND (optional, for approved docs)
    ═══════════════════════════════════════════ */
    .approval-band {
      display: flex;
      align-items: center;
      gap: 12px;
      border: 1.5px solid #bbf7d0;
      background: #f0fdf4;
      border-radius: 7px;
      padding: 9px 14px;
      margin-bottom: 12px;
      break-inside: avoid;
      page-break-inside: avoid;
    }

    .approval-band-icon {
      width: 26px;
      height: 26px;
      border-radius: 50%;
      background: #dcfce7;
      border: 1.5px solid #86efac;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 12px;
      flex-shrink: 0;
    }

    .approval-band-text { flex: 1; min-width: 0; }

    .approval-band-title {
      font-size: 8.8pt;
      font-weight: 700;
      color: #15803d;
    }

    .approval-band-detail {
      font-size: 7.5pt;
      color: #166534;
      margin-top: 1px;
    }

    .approval-band-ref {
      font-family: var(--font-mono);
      font-size: 8pt;
      color: #15803d;
      font-weight: 600;
    }

    /* Variant: pending */
    .approval-band--pending {
      border-color: #fde68a;
      background: #fefce8;
    }
    .approval-band--pending .approval-band-icon { background: #fef9c3; border-color: #fde68a; }
    .approval-band--pending .approval-band-title { color: #a16207; }
    .approval-band--pending .approval-band-detail { color: #92400e; }
    .approval-band--pending .approval-band-ref { color: #a16207; }

    /* Variant: cancelled */
    .approval-band--cancelled {
      border-color: #fecaca;
      background: #fff1f2;
    }
    .approval-band--cancelled .approval-band-icon { background: #fee2e2; border-color: #fecaca; }
    .approval-band--cancelled .approval-band-title { color: #b91c1c; }
    .approval-band--cancelled .approval-band-detail { color: #991b1b; }
    .approval-band--cancelled .approval-band-ref { color: #b91c1c; }

    /* ═══════════════════════════════════════════
       SECTION CARD (shared with pdf-vertical)
    ═══════════════════════════════════════════ */
    .section-card {
      border: 1px solid var(--border);
      border-radius: 7px;
      margin-bottom: 12px;
      background: var(--surface-0);
    }

    .section-card-title {
      padding: 6px 16px;
      font-size: 6.8pt;
      font-weight: 700;
      color: var(--brand-accent);
      background: var(--surface-1);
      border-bottom: 1px solid var(--border);
      border-radius: 6px 6px 0 0;
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
      height: 10px;
      background: var(--brand-accent);
      border-radius: 2px;
      flex-shrink: 0;
    }

    /* ── KV rows ── */
    .kv-row {
      display: flex;
      border-bottom: 1px solid var(--border);
      break-inside: avoid;
      page-break-inside: avoid;
    }

    .kv-row:last-child { border-bottom: none; }
    .kv-row:nth-child(even) { background: var(--surface-1); }

    .kv-label {
      width: 38%;
      flex-shrink: 0;
      padding: 7px 16px;
      font-size: 7pt;
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
      font-size: 9pt;
      font-weight: 500;
      color: var(--text-primary);
      display: flex;
      align-items: center;
      flex-wrap: wrap;
      gap: 4px;
    }

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
    .badge--purple    { background: #f5f3ff; color: #7c3aed; border: 1px solid #ddd6fe; }

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

    .mt-1 { margin-top: 4px; }  .mt-2 { margin-top: 8px; }  .mt-3 { margin-top: 14px; }
    .mb-1 { margin-bottom: 4px; } .mb-2 { margin-bottom: 8px; } .mb-3 { margin-bottom: 14px; }

    .avoid-break  { break-inside: avoid; page-break-inside: avoid; }
    .break-before { break-before: page; page-break-before: always; }

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
