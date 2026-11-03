@extends('pdf.layouts.pdf-vertical')

@section('title', 'Fiche Client')
@section('report-title', 'Fiche Client — ' . $client->client_id)

@push('header-badge')
  <span class="badge badge--secondary" style="font-size: 6pt; letter-spacing: 0.6px; border-radius: 4px;">
    CRM · B3
  </span>
@endpush

@section('content')
  @php
    $statusMap = [
      'active'      => 'success',
      'inactive'    => 'secondary',
      'prospect'    => 'info',
      'suspended'   => 'warning',
      'blacklisted' => 'error',
      'archived'    => 'default',
    ];
    $statusLabels = [
      'active'      => 'Actif',
      'inactive'    => 'Inactif',
      'prospect'    => 'Prospect',
      'suspended'   => 'Suspendu',
      'blacklisted' => 'Bloqué',
      'archived'    => 'Archivé',
    ];
    $variant = $statusMap[$client->status] ?? 'default';
    $primaryContact = $client->contacts?->where('is_primary', true)->first() ?? $client->contacts?->first();
    $initials = strtoupper(substr($client->legal_name ?? 'C', 0, 2));
  @endphp

  {{-- ── Profile Header ── --}}
  <div class="profile-header">
    <div class="profile-header-inner">
      <div class="profile-avatar">{{ $initials }}</div>
      <div class="profile-info">
        <div class="profile-name">{{ $client->legal_name }}</div>
        <div class="profile-subtitle">
          {{ $client->primary_email ?? ($primaryContact->email ?? 'Aucun email renseigné') }}
          @if($client->primary_phone) &nbsp;·&nbsp; {{ $client->primary_phone }} @endif
        </div>
        <div class="profile-badges">
          <span class="badge badge--{{ $variant }}">
            {{ $statusLabels[$client->status] ?? $client->status ?? 'Inconnu' }}
          </span>
          @if($client->client_category)
            <span class="badge badge--primary">
              {{ str_replace('_', ' ', $client->client_category) }}
            </span>
          @endif
          @if($client->industry)
            <span class="badge badge--secondary">{{ $client->industry }}</span>
          @endif
        </div>
      </div>
      <div class="profile-meta-right">
        <div class="profile-id-label">ID Client</div>
        <div class="profile-id">{{ $client->client_id }}</div>
        @if($client->partner_since)
          <div class="profile-id-label" style="margin-top: 6px;">Partenaire depuis</div>
          <div style="font-size: 8.5pt; font-weight: 600; color: var(--text-secondary);">
            {{ \Carbon\Carbon::parse($client->partner_since)->format('d M Y') }}
          </div>
        @endif
      </div>
    </div>
  </div>

  {{-- ── Informations Entreprise ── --}}
  <div class="section-card">
    <div class="section-card-title">Informations de l'entreprise</div>
    <div class="kv-grid-2col">
      <div class="kv-row">
        <div class="kv-label">Raison Sociale</div>
        <div class="kv-value font-semibold">{{ $client->legal_name }}</div>
      </div>
      <div class="kv-row">
        <div class="kv-label">Nom Commercial</div>
        <div class="kv-value">{{ is_array($client->commercial_name) ? ($client->commercial_name[app()->getLocale()] ?? collect($client->commercial_name)->first()) : ($client->commercial_name ?? '—') }}</div>
      </div>
      <div class="kv-row">
        <div class="kv-label">Secteur</div>
        <div class="kv-value capitalize">{{ $client->industry ?? '—' }}</div>
      </div>
      <div class="kv-row">
        <div class="kv-label">Taille</div>
        <div class="kv-value capitalize">{{ $client->company_size ?? '—' }}</div>
      </div>
      <div class="kv-row">
        <div class="kv-label">Forme Juridique</div>
        <div class="kv-value capitalize">{{ $client->legal_status ?? '—' }}</div>
      </div>
      <div class="kv-row">
        <div class="kv-label">Année de création</div>
        <div class="kv-value">{{ $client->founding_year ?? '—' }}</div>
      </div>
    </div>
  </div>

  {{-- ── Identifiants Fiscaux ── --}}
  <div class="section-card">
    <div class="section-card-title">Identifiants Fiscaux &amp; Légaux</div>
    <div class="kv-grid-2col">
      <div class="kv-row">
        <div class="kv-label">Matricule Fiscal</div>
        <div class="kv-value text-mono">{{ $client->tax_id ?? '—' }}</div>
      </div>
      <div class="kv-row">
        <div class="kv-label">Identifiant RNE</div>
        <div class="kv-value text-mono">{{ $client->rne_id ?? '—' }}</div>
      </div>
    </div>
  </div>

  {{-- ── Données Financières ── --}}
  <div class="section-card">
    <div class="section-card-title">Données Financières</div>
    <div class="kv-grid-2col">
      <div class="kv-row">
        <div class="kv-label">Plafond Crédit</div>
        <div class="kv-value font-semibold">
          {{ $client->credit_limit ? number_format($client->credit_limit, 3, ',', ' ') : '0,000' }}
          <span class="text-muted" style="font-size: 8pt; margin-left: 4px;">{{ $client->default_currency ?? 'TND' }}</span>
        </div>
      </div>
      <div class="kv-row">
        <div class="kv-label">Conditions de Paiement</div>
        <div class="kv-value capitalize">{{ str_replace('_', ' ', $client->payment_terms ?? '—') }}</div>
      </div>
      <div class="kv-row">
        <div class="kv-label">Devise par défaut</div>
        <div class="kv-value">{{ $client->default_currency ?? 'TND' }}</div>
      </div>
      <div class="kv-row">
        <div class="kv-label">Remise accordée</div>
        <div class="kv-value">{{ $client->discount_rate ? $client->discount_rate . ' %' : '0,00 %' }}</div>
      </div>
    </div>
  </div>

  {{-- ── Contact & Web ── --}}
  <div class="section-card">
    <div class="section-card-title">Contact Principal &amp; Web</div>
    <div class="kv-grid-2col">
      <div class="kv-row">
        <div class="kv-label">Téléphone</div>
        <div class="kv-value">{{ $client->primary_phone ?? '—' }}</div>
      </div>
      <div class="kv-row">
        <div class="kv-label">Email</div>
        <div class="kv-value" style="font-size: 8.8pt;">{{ $client->primary_email ?? '—' }}</div>
      </div>
      <div class="kv-row">
        <div class="kv-label">Site Web</div>
        <div class="kv-value" style="font-size: 8.5pt; color: var(--text-secondary);">{{ $client->website ?? '—' }}</div>
      </div>
      <div class="kv-row">
        <div class="kv-label">Responsable Compte</div>
        <div class="kv-value">{{ $client->accountManager ? $client->accountManager->full_name : 'Non assigné' }}</div>
      </div>
    </div>
  </div>

  {{-- ── Adresses ── --}}
  @if($client->addresses && $client->addresses->isNotEmpty())
    <div class="section-card">
      <div class="section-card-title">Adresses ({{ $client->addresses->count() }})</div>
      @foreach($client->addresses as $idx => $address)
        <div class="kv-row" style="{{ $idx > 0 ? 'border-top: 2px solid var(--surface-2);' : '' }}">
          <div class="kv-label" style="flex-direction: column; align-items: flex-start; gap: 3px;">
            <span>{{ \Illuminate\Support\Str::title(str_replace('_', ' ', $address->address_type ?? 'Adresse')) }}</span>
            @if($address->is_primary)
              <span class="badge badge--info" style="font-size: 5.5pt;">Principale</span>
            @endif
            @if($address->is_billing)
              <span class="badge badge--secondary" style="font-size: 5.5pt;">Facturation</span>
            @endif
          </div>
          <div class="kv-value" style="line-height: 1.7; font-size: 9pt;">
            {{ $address->street_address ?? $address->address_line_1 }}<br>
            @if($address->address_line_2) {{ $address->address_line_2 }}<br> @endif
            {{ collect([$address->city, $address->state, $address->postal_code ?? $address->postcode])->filter()->join(', ') }}<br>
            <span class="text-secondary">{{ $address->country }}</span>
          </div>
        </div>
      @endforeach
    </div>
  @endif

  {{-- ── Contacts Clés ── --}}
  @if($client->contacts && $client->contacts->isNotEmpty())
    <div class="section-card">
      <div class="section-card-title">Contacts Clés ({{ $client->contacts->count() }})</div>
      @foreach($client->contacts as $idx => $contact)
        <div class="kv-row" style="{{ $idx > 0 ? 'border-top: 2px solid var(--surface-2);' : '' }}">
          <div class="kv-label" style="flex-direction: column; align-items: flex-start; gap: 3px;">
            <span class="font-semibold" style="font-size: 9pt; text-transform: none; letter-spacing: 0;">
              {{ $contact->first_name }} {{ $contact->last_name }}
            </span>
            @if($contact->is_primary)
              <span class="badge badge--info" style="font-size: 5.5pt;">Principal</span>
            @endif
          </div>
          <div class="kv-value" style="flex-direction: column; align-items: flex-start; gap: 4px;">
            <div class="font-semibold" style="font-size: 9pt;">
              {{ $contact->position ?? '—' }}
              @if($contact->department)
                <span class="text-secondary" style="font-weight: 400; font-size: 8.5pt;">({{ $contact->department }})</span>
              @endif
            </div>
            <div style="font-size: 8pt; color: var(--text-secondary);">
              @if($contact->email) <span style="margin-right: 10px;">✉ {{ $contact->email }}</span> @endif
              @if($contact->phone) <span>☎ {{ $contact->phone }}</span> @endif
            </div>
          </div>
        </div>
      @endforeach
    </div>
  @endif

  {{-- ── Relation Client ── --}}
  <div class="section-card">
    <div class="section-card-title">Relation Client</div>
    <div class="kv-grid-2col">
      <div class="kv-row">
        <div class="kv-label">Partenaire depuis</div>
        <div class="kv-value">
          {{ $client->partner_since ? \Carbon\Carbon::parse($client->partner_since)->format('d M Y') : '—' }}
        </div>
      </div>
      <div class="kv-row">
        <div class="kv-label">Score Satisfaction</div>
        <div class="kv-value">
          @if($client->satisfaction_score)
            <span style="font-weight: 700; color: {{ $client->satisfaction_score >= 8 ? '#15803d' : ($client->satisfaction_score >= 5 ? '#a16207' : '#b91c1c') }}">
              {{ $client->satisfaction_score }}/10
            </span>
          @else
            —
          @endif
        </div>
      </div>
      <div class="kv-row">
        <div class="kv-label">Responsable Compte</div>
        <div class="kv-value">{{ $client->accountManager ? $client->accountManager->full_name : 'Non assigné' }}</div>
      </div>
      <div class="kv-row">
        <div class="kv-label">Segment</div>
        <div class="kv-value capitalize">{{ $client->client_segment ?? '—' }}</div>
      </div>
    </div>
  </div>

@endsection
