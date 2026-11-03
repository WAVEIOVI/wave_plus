@extends('pdf.layouts.pdf-pro-vertical')

@section('title', 'Bon de Commande — ' . $po->reference)
@section('doc-type-code', 'PO')
@section('doc-reference', $po->reference)

{{-- Footer legal info — override per environment if needed --}}
@section('footer-address', 'Route de Tunis km 2.5, Sfax 3000, Tunisie')
@section('footer-tax-id', config('company.tax_id', '—'))
@section('footer-phone', config('company.phone', '—'))

@section('content')
  @php
    $statusMap = [
      'draft'              => ['label' => 'Brouillon',          'badge' => 'default'],
      'pending_approval'   => ['label' => 'En attente appro.',  'badge' => 'warning'],
      'approved'           => ['label' => 'Approuvé',           'badge' => 'success'],
      'sent'               => ['label' => 'Envoyé',             'badge' => 'info'],
      'acknowledged'       => ['label' => 'Accusé de réc.',     'badge' => 'primary'],
      'in_delivery'        => ['label' => 'En livraison',       'badge' => 'orange'],
      'partially_received' => ['label' => 'Partiel.',           'badge' => 'warning'],
      'fully_received'     => ['label' => 'Réceptionné',        'badge' => 'success'],
      'closed'             => ['label' => 'Clôturé',            'badge' => 'secondary'],
      'cancelled'          => ['label' => 'Annulé',             'badge' => 'error'],
    ];
    $statusInfo = $statusMap[$po->status] ?? ['label' => $po->status, 'badge' => 'default'];

    $paymentLabels = [
      'immediate'    => 'Paiement immédiat',
      'net_15'       => 'Net 15 jours',
      'net_30'       => 'Net 30 jours',
      'net_60'       => 'Net 60 jours',
      'net_90'       => 'Net 90 jours',
      'advance_100'  => 'Avance 100%',
      'split_50_50'  => 'Acompte 50/50',
    ];
    $paymentLabel = $paymentLabels[$po->payment_terms] ?? str_replace('_', ' ', $po->payment_terms);
    $currency = $po->currency ?? 'TND';
  @endphp

  {{-- ═══════════════════════════════════════════
       DOC HERO — Company identity + Document header
  ═══════════════════════════════════════════ --}}
  <div class="doc-hero">
    {{-- Left: SS Plus identity (repeating header already displays the SS PLUS brand mark) --}}
    <div class="doc-hero-company">
      <div class="doc-type-label" style="color: var(--brand-accent); margin-bottom: 3px;">Émetteur / Acheteur</div>
      <div class="doc-hero-company-name">Sécurité Services Plus</div>
      <div class="doc-hero-company-tagline" style="margin-bottom: 6px;">Protection · EPI · Vêtements de travail</div>
      <div class="doc-hero-address">
        Route de Tunis km 2.5, Sfax 3000, Tunisie<br>
        <strong>Tél :</strong> {{ config('company.phone', '—') }}
        &nbsp;·&nbsp;
        <strong>Email :</strong> {{ config('company.email', '—') }}<br>
        <strong>MF :</strong> {{ config('company.tax_id', '—') }}
        &nbsp;·&nbsp;
        <strong>RNE :</strong> {{ config('company.rne_id', '—') }}
      </div>
    </div>

    {{-- Right: Document identity --}}
    <div class="doc-hero-document">
      <div class="doc-type-label">Achat &amp; Approvisionnement</div>
      <div class="doc-type-title">Bon de<br>Commande</div>
      <div class="doc-ref-block">
        <div class="doc-ref-block-label">Référence BC</div>
        <div class="doc-ref-block-value">{{ $po->reference }}</div>
      </div>
      <div class="doc-meta-pills">
        <div class="doc-meta-pill">
          <span class="doc-meta-pill-label">Date d'émission</span>
          <span class="doc-meta-pill-value">{{ $po->created_at->format('d M Y') }}</span>
        </div>
        <div class="doc-meta-pill">
          <span class="doc-meta-pill-label">Statut</span>
          <span class="doc-meta-pill-value">
            <span class="badge badge--{{ $statusInfo['badge'] }}">{{ $statusInfo['label'] }}</span>
          </span>
        </div>
        @if($po->committed_delivery_date)
          <div class="doc-meta-pill">
            <span class="doc-meta-pill-label">Livraison prévue</span>
            <span class="doc-meta-pill-value">{{ \Carbon\Carbon::parse($po->committed_delivery_date)->format('d M Y') }}</span>
          </div>
        @endif
      </div>
    </div>
  </div>

  <div class="doc-separator"></div>

  {{-- ═══════════════════════════════════════════
       APPROVAL BAND (conditional)
  ═══════════════════════════════════════════ --}}
  @if($po->status === 'approved' && $po->approved_by)
    <div class="approval-band">
      <div class="approval-band-icon">✓</div>
      <div class="approval-band-text">
        <div class="approval-band-title">Bon de commande approuvé</div>
        <div class="approval-band-detail">
          Approuvé par {{ $po->approver->full_name ?? '—' }}
          @if($po->approved_at) le {{ \Carbon\Carbon::parse($po->approved_at)->format('d M Y à H:i') }} @endif
        </div>
      </div>
      <span class="approval-band-ref">{{ $po->reference }}</span>
    </div>
  @elseif($po->status === 'pending_approval')
    <div class="approval-band approval-band--pending">
      <div class="approval-band-icon">⏳</div>
      <div class="approval-band-text">
        <div class="approval-band-title">En attente d'approbation</div>
        <div class="approval-band-detail">Ce document est soumis à validation hiérarchique avant envoi fournisseur.</div>
      </div>
    </div>
  @elseif($po->status === 'cancelled')
    <div class="approval-band approval-band--cancelled">
      <div class="approval-band-icon">✕</div>
      <div class="approval-band-text">
        <div class="approval-band-title">Bon de commande annulé</div>
        <div class="approval-band-detail">Ce document est annulé et n'a aucune valeur contractuelle.</div>
      </div>
    </div>
  @endif

  {{-- ═══════════════════════════════════════════
       PARTIES — Fournisseur + Livraison
  ═══════════════════════════════════════════ --}}
  <div class="parties-row">
    {{-- Vendor / Fournisseur --}}
    <div class="party-block">
      <div class="party-block-header">
        <div class="party-block-header-dot party-block-header-dot--vendor"></div>
        <span class="party-block-header-label">Fournisseur</span>
      </div>
      <div class="party-block-body">
        <div class="party-name">{{ $po->supplier->name ?? '—' }}</div>
        <div class="party-detail">
          @if($po->supplier->address) {{ $po->supplier->address }}<br> @endif
          @if($po->supplier->city) {{ $po->supplier->city }}, {{ $po->supplier->country ?? 'Tunisie' }}<br> @endif
          @if($po->supplier->tax_id) <strong>MF :</strong> {{ $po->supplier->tax_id }}<br> @endif
          @if($po->supplierContact)
            <strong>Contact :</strong> {{ $po->supplierContact->first_name }} {{ $po->supplierContact->last_name }}
            @if($po->supplierContact->phone) · {{ $po->supplierContact->phone }} @endif
          @endif
          @if($po->supplier_po_reference)
            <br><strong>Réf fournisseur :</strong> {{ $po->supplier_po_reference }}
          @endif
        </div>
      </div>
    </div>

    {{-- Ship To --}}
    <div class="party-block">
      <div class="party-block-header">
        <div class="party-block-header-dot party-block-header-dot--ship"></div>
        <span class="party-block-header-label">Livrer à</span>
      </div>
      <div class="party-block-body">
        <div class="party-name">Sécurité Services Plus</div>
        <div class="party-detail">
          {{ $po->delivery_address ?? config('company.address', 'Route de Tunis km 2.5, Sfax 3000, Tunisie') }}<br>
          <strong>Réf. BC :</strong> {{ $po->reference }}<br>
          @if($po->committed_delivery_date)
            <strong>Date souhaitée :</strong> {{ \Carbon\Carbon::parse($po->committed_delivery_date)->format('d M Y') }}
          @endif
        </div>
      </div>
    </div>
  </div>

  {{-- ═══════════════════════════════════════════
       DOC META STRIP — payment terms, incoterms, currency…
  ═══════════════════════════════════════════ --}}
  <div class="doc-meta-strip avoid-break">
    <div class="doc-meta-item">
      <div class="doc-meta-item-label">Conditions de paiement</div>
      <div class="doc-meta-item-value">{{ $paymentLabel }}</div>
    </div>
    <div class="doc-meta-item">
      <div class="doc-meta-item-label">Devise</div>
      <div class="doc-meta-item-value mono">{{ $currency }}
        @if($currency !== 'TND' && $po->exchange_rate != 1)
          <span class="text-muted" style="font-size: 7.5pt;">· 1 {{ $currency }} = {{ number_format($po->exchange_rate, 4) }} TND</span>
        @endif
      </div>
    </div>
    @if($po->incoterms)
      <div class="doc-meta-item">
        <div class="doc-meta-item-label">Incoterms</div>
        <div class="doc-meta-item-value mono">{{ $po->incoterms }}</div>
      </div>
    @endif
    <div class="doc-meta-item">
      <div class="doc-meta-item-label">Origine</div>
      <div class="doc-meta-item-value" style="font-size: 8pt; text-transform: capitalize;">
        {{ str_replace(['from_rfq', 'from_ipr', 'manual'], ['Appel d\'offres', 'Demande d\'achat', 'Saisie directe'], $po->creation_path ?? 'manual') }}
      </div>
    </div>
    @if($po->sent_at)
      <div class="doc-meta-item">
        <div class="doc-meta-item-label">Envoyé le</div>
        <div class="doc-meta-item-value mono" style="font-size: 8pt;">{{ \Carbon\Carbon::parse($po->sent_at)->format('d M Y') }}</div>
      </div>
    @endif
  </div>

  {{-- ═══════════════════════════════════════════
       LINE ITEMS TABLE
  ═══════════════════════════════════════════ --}}
  <div class="section-label">
    <div class="section-label-bar"></div>
    <span class="section-label-text">Lignes de commande</span>
  </div>

  <table class="lines-table">
    <thead>
      <tr>
        <th style="width: 26px; text-align: center;">#</th>
        <th style="width: 14%;">Référence</th>
        <th>Désignation</th>
        <th style="width: 8%; text-align: right;">Qté</th>
        <th style="width: 10%; text-align: right;">P.U. HT</th>
        <th style="width: 6%; text-align: right;">TVA</th>
        <th style="width: 11%; text-align: right;">Total HT</th>
        <th style="width: 11%; text-align: right;">Total TTC</th>
        <th style="width: 11%; text-align: center;">Statut livraison</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($po->lines as $index => $line)
        @php
          $fStatus = [
            'pending'           => ['Attente',    '#94a3b8', '#f1f5f9'],
            'partially_received'=> ['Partiel.',   '#a16207', '#fef9c3'],
            'fully_received'    => ['Complet',    '#15803d', '#dcfce7'],
            'over_received'     => ['Excédent',   '#b91c1c', '#fee2e2'],
          ][$line->fulfillment_status] ?? ['—', '#94a3b8', '#f1f5f9'];
        @endphp
        <tr>
          <td class="line-num">{{ $index + 1 }}</td>
          <td class="text-mono" style="font-size: 7.8pt;">
            {{ $line->is_ad_hoc ? ($line->ad_hoc_sku ?? '—') : ($line->catalogItem->sku ?? '—') }}
          </td>
          <td>
            <div class="line-desc">
              {{ $line->is_ad_hoc ? $line->ad_hoc_name : $line->description_snapshot }}
            </div>
            @if(!$line->is_ad_hoc && $line->variant)
              <div class="line-ref">{{ $line->variant->label ?? '' }}</div>
            @endif
            @if($line->supplier_reference)
              <div class="line-ref">Réf. fourn. : {{ $line->supplier_reference }}</div>
            @endif
          </td>
          <td class="text-right font-semibold">{{ number_format($line->qty_ordered, 2) }}</td>
          <td class="amount">{{ number_format($line->unit_price_ht, 3) }}</td>
          <td class="text-right text-secondary" style="font-size: 8pt;">{{ $line->tax_rate_pct }}%</td>
          <td class="amount">{{ number_format($line->total_ht, 3) }}</td>
          <td class="amount">{{ number_format($line->total_ttc, 3) }}</td>
          <td class="text-center">
            <span class="line-fulfillment" style="color: {{ $fStatus[1] }}; background: {{ $fStatus[2] }};">
              {{ $fStatus[0] }}
            </span>
            @if($line->qty_received > 0)
              <div style="font-size: 6.5pt; color: var(--text-muted); margin-top: 2px;">
                {{ number_format($line->qty_received, 2) }} reçu
              </div>
            @endif
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="9" style="text-align: center; padding: 30px; color: var(--text-muted);">
            Aucune ligne de commande.
          </td>
        </tr>
      @endforelse
    </tbody>
  </table>

  {{-- ═══════════════════════════════════════════
       TOTALS + TERMS ROW
  ═══════════════════════════════════════════ --}}
  <div class="bottom-row">
    {{-- Left: Notes + Terms --}}
    <div class="doc-terms">
      @if($po->notes_to_supplier)
        <div class="doc-terms-box" style="margin-bottom: 10px;">
          <strong>Note au fournisseur</strong>
          {{ $po->notes_to_supplier }}
        </div>
      @endif
      @if($po->internal_notes)
        <div class="doc-terms-box" style="background: #fff7ed; border-color: #fed7aa;">
          <strong style="color: var(--brand-orange);">Note interne (confidentiel)</strong>
          {{ $po->internal_notes }}
        </div>
      @endif
      @if(!$po->notes_to_supplier && !$po->internal_notes)
        <div class="doc-terms-box">
          <strong>Conditions générales</strong>
          La livraison doit être accompagnée du bon de livraison fournisseur.
          Tout article non conforme sera refusé et retourné aux frais du fournisseur.
          Le paiement sera effectué après réception et contrôle qualité.
        </div>
      @endif
    </div>

    {{-- Right: Totals --}}
    <div class="totals-block">
      <table class="totals-table">
        <tbody>
          <tr>
            <td style="padding: 8px 14px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between;">
              <span class="totals-label">Total HT</span>
              <span class="totals-value">{{ number_format($po->total_ht, 3) }} <span class="totals-currency">{{ $currency }}</span></span>
            </td>
          </tr>
          <tr>
            <td style="padding: 8px 14px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between;">
              <span class="totals-label">TVA</span>
              <span class="totals-value">{{ number_format($po->total_tax, 3) }} <span class="totals-currency">{{ $currency }}</span></span>
            </td>
          </tr>
          <tr class="totals-row--grand">
            <td style="padding: 11px 14px; display: flex; justify-content: space-between; background: var(--brand-dark); border-radius: 0 0 6px 6px;">
              <span class="totals-label" style="color: rgba(255,255,255,0.75); font-weight: 600;">TOTAL TTC</span>
              <span class="totals-value" style="color: #ffffff; font-size: 10.5pt;">
                {{ number_format($po->total_ttc, 3) }}
                <span style="font-size: 7pt; opacity: 0.65; margin-left: 3px;">{{ $currency }}</span>
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  {{-- ═══════════════════════════════════════════
       SIGNATURE BLOCK
  ═══════════════════════════════════════════ --}}
  <div class="signatures-row">
    {{-- Établi par --}}
    <div class="signature-box">
      <div class="signature-box-header">Établi par</div>
      <div class="signature-box-body">
        <div>
          <div class="signature-name">{{ $po->creator->full_name ?? 'WAVE PLUS' }}</div>
          <div class="signature-date">{{ $po->created_at->format('d M Y') }}</div>
        </div>
        <div class="signature-stamp-area">
          <div class="signature-stamp-line"></div>
          <div class="signature-stamp-label">Signature</div>
          <div class="signature-stamp-line"></div>
        </div>
      </div>
    </div>

    {{-- Approuvé par --}}
    <div class="signature-box">
      <div class="signature-box-header">Approuvé par</div>
      <div class="signature-box-body">
        <div>
          @if($po->approved_by && $po->approver)
            <div class="signature-name">{{ $po->approver->full_name }}</div>
            <div class="signature-date">{{ \Carbon\Carbon::parse($po->approved_at)->format('d M Y') }}</div>
          @else
            <div class="signature-name text-muted" style="font-weight: 400;">En attente</div>
          @endif
        </div>
        <div class="signature-stamp-area">
          <div class="signature-stamp-line"></div>
          <div class="signature-stamp-label">Cachet &amp; Signature</div>
          <div class="signature-stamp-line"></div>
        </div>
      </div>
    </div>

    {{-- Accepté par le fournisseur --}}
    <div class="signature-box">
      <div class="signature-box-header">Accepté par le fournisseur</div>
      <div class="signature-box-body">
        <div>
          <div class="signature-name text-muted" style="font-weight: 400;">{{ $po->supplier->name ?? '—' }}</div>
          @if($po->acknowledged_at)
            <div class="signature-date">{{ \Carbon\Carbon::parse($po->acknowledged_at)->format('d M Y') }}</div>
          @endif
        </div>
        <div class="signature-stamp-area">
          <div class="signature-stamp-line"></div>
          <div class="signature-stamp-label">Cachet &amp; Signature</div>
          <div class="signature-stamp-line"></div>
        </div>
      </div>
    </div>
  </div>

@endsection
