@extends('pdf.layouts.pdf-horizontal')

@section('title', 'Répertoire Clients')
@section('report-title', 'Partenaires Commerciaux — Liste des Clients')

@push('header-badge')
  <span class="badge badge--secondary" style="font-size: 6pt; letter-spacing: 0.6px; border-radius: 4px;">
    CRM · B3
  </span>
@endpush

@push('footer-center')
  Répertoire confidentiel — usage interne uniquement
@endpush

@section('content')

  {{-- ── Stats summary ── --}}
  @php
    $total      = count($clients);
    $active     = collect($clients)->where('status', 'active')->count();
    $prospects  = collect($clients)->where('status', 'prospect')->count();
    $inactive   = $total - $active - $prospects;
  @endphp

  <div class="stats-row avoid-break">
    <div class="stat-pill">
      <span class="stat-pill-label">Total</span>
      <span class="stat-pill-value">{{ $total }}</span>
    </div>
    <div class="stat-pill">
      <span class="stat-pill-label">Actifs</span>
      <span class="stat-pill-value" style="color: #15803d;">{{ $active }}</span>
    </div>
    <div class="stat-pill">
      <span class="stat-pill-label">Prospects</span>
      <span class="stat-pill-value" style="color: #1d4ed8;">{{ $prospects }}</span>
    </div>
    <div class="stat-pill">
      <span class="stat-pill-label">Autres</span>
      <span class="stat-pill-value" style="color: var(--text-muted);">{{ $inactive }}</span>
    </div>
  </div>

  {{-- ── Main table ── --}}
  <table class="data-table">
    <thead>
      <tr>
        <th style="width: 3%; text-align: center;">#</th>
        <th style="width: 11%;">ID Client</th>
        <th style="width: 26%;">Raison Sociale</th>
        <th style="width: 14%;">Secteur</th>
        <th style="width: 11%;">Catégorie</th>
        <th style="width: 10%;">Statut</th>
        <th style="width: 11%;">Partenaire depuis</th>
        <th style="width: 14%;">Responsable</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($clients as $index => $client)
        <tr>
          <td class="row-index">{{ $index + 1 }}</td>
          <td class="text-mono font-semibold">{{ $client->client_id }}</td>
          <td class="font-semibold">{{ $client->legal_name }}</td>
          <td class="capitalize text-secondary">{{ $client->industry ?? '—' }}</td>
          <td class="capitalize text-secondary">{{ $client->client_category ? str_replace('_', ' ', $client->client_category) : '—' }}</td>
          <td>
            @php
              $statusMap = [
                'active'      => 'success',
                'inactive'    => 'secondary',
                'prospect'    => 'info',
                'suspended'   => 'warning',
                'blacklisted' => 'error',
                'archived'    => 'default',
              ];
              $variant = $statusMap[$client->status] ?? 'default';
              $statusLabels = [
                'active'      => 'Actif',
                'inactive'    => 'Inactif',
                'prospect'    => 'Prospect',
                'suspended'   => 'Suspendu',
                'blacklisted' => 'Bloqué',
                'archived'    => 'Archivé',
              ];
            @endphp
            <span class="badge badge--{{ $variant }}">
              {{ $statusLabels[$client->status] ?? $client->status ?? '—' }}
            </span>
          </td>
          <td class="text-mono">
            {{ $client->partner_since ? \Carbon\Carbon::parse($client->partner_since)->format('d M Y') : '—' }}
          </td>
          <td class="text-secondary" style="font-size: 8.5pt;">
            {{ $client->accountManager ? $client->accountManager->full_name : '—' }}
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="8" style="text-align: center; padding: 40px 10px; color: var(--text-muted);">
            Aucun client enregistré dans le système.
          </td>
        </tr>
      @endforelse
    </tbody>
  </table>

  <div class="summary-footer">
    Total enregistrements : <strong>{{ count($clients) }}</strong> client(s)
    @if(isset($filters) && count($filters))
      &nbsp;·&nbsp; Filtres appliqués : {{ implode(', ', $filters) }}
    @endif
  </div>

@endsection
