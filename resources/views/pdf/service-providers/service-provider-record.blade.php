@extends('pdf.layouts.pdf-vertical')

@php
  $service_provider = $service_provider ?? $serviceProvider;
@endphp

@section('title', 'Service Provider Record')
@section('report-title', 'Service Provider Details — ' . $service_provider->service_provider_id)

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
    $variant = $statusMap[$service_provider->status] ?? 'info';
    $primaryContact = $service_provider->contacts?->where('is_primary', true)->first() ?? $service_provider->contacts?->first();
    $initials = strtoupper(substr($service_provider->legal_name ?? 'C', 0, 2));
  @endphp

  {{-- ── Profile Header ── --}}
  <div class="profile-header">
    <div class="profile-header-inner">
      <div class="profile-avatar">{{ $initials }}</div>
      <div class="profile-info">
        <div class="profile-name">{{ $service_provider->legal_name }}</div>
        <div class="profile-subtitle">{{ $service_provider->primary_email ?? ($primaryContact->email ?? 'No email provided') }}</div>
        <div class="profile-badges">
          <span class="badge badge--{{ $variant }}" style="text-transform: capitalize;">
            {{ $service_provider->status ?? 'Unknown' }}
          </span>
          @if($service_provider->service_provider_category)
          <span class="badge badge--primary" style="text-transform: capitalize;">
            {{ str_replace('_', ' ', $service_provider->service_provider_category) }}
          </span>
          @endif
        </div>
      </div>
    </div>
  </div>

  {{-- ── Corporate Information ── --}}
  <div class="section-card">
    <div class="section-card-title">Corporate Information</div>
    <div class="kv-row">
      <div class="kv-label">Service Provider ID</div>
      <div class="kv-value"><strong>{{ $service_provider->service_provider_id }}</strong></div>
    </div>
    <div class="kv-row">
      <div class="kv-label">Legal Name</div>
      <div class="kv-value">{{ $service_provider->legal_name }}</div>
    </div>
    <div class="kv-row">
      <div class="kv-label">Commercial Name</div>
      <div class="kv-value">{{ is_array($service_provider->commercial_name) ? ($service_provider->commercial_name[app()->getLocale()] ?? collect($service_provider->commercial_name)->first()) : ($service_provider->commercial_name ?? '—') }}</div>
    </div>
    <div class="kv-row">
      <div class="kv-label">Industry</div>
      <div class="kv-value" style="text-transform: capitalize;">{{ $service_provider->industry ?? '—' }}</div>
    </div>
    <div class="kv-row">
      <div class="kv-label">Company Size</div>
      <div class="kv-value" style="text-transform: capitalize;">{{ $service_provider->company_size ?? '—' }}</div>
    </div>
    <div class="kv-row">
      <div class="kv-label">Legal Status</div>
      <div class="kv-value" style="text-transform: capitalize;">{{ $service_provider->legal_status ?? '—' }}</div>
    </div>
  </div>

  {{-- ── Registration & Tax ── --}}
  <div class="section-card">
    <div class="section-card-title">Registration &amp; Tax Information</div>
    <div class="kv-row">
      <div class="kv-label">Tax ID</div>
      <div class="kv-value">{{ $service_provider->tax_id ?? '—' }}</div>
    </div>
    <div class="kv-row">
      <div class="kv-label">RNE ID</div>
      <div class="kv-value">{{ $service_provider->rne_id ?? '—' }}</div>
    </div>
    <div class="kv-row">
      <div class="kv-label">Year Founded</div>
      <div class="kv-value">{{ $service_provider->founding_year ?? '—' }}</div>
    </div>
  </div>

  {{-- ── Financial Data ── --}}
  <div class="section-card">
    <div class="section-card-title">Financial Data</div>
    <div class="kv-row">
      <div class="kv-label">Credit Limit</div>
      <div class="kv-value">{{ $service_provider->credit_limit ? number_format($service_provider->credit_limit, 3) : '0.000' }}</div>
    </div>
    <div class="kv-row">
      <div class="kv-label">Payment Terms</div>
      <div class="kv-value" style="text-transform: capitalize;">{{ str_replace('_', ' ', $service_provider->payment_terms ?? '—') }}</div>
    </div>
    <div class="kv-row">
      <div class="kv-label">Default Currency</div>
      <div class="kv-value">{{ $service_provider->default_currency ?? 'TND' }}</div>
    </div>
    <div class="kv-row">
      <div class="kv-label">Discount Rate</div>
      <div class="kv-value">{{ $service_provider->discount_rate ? $service_provider->discount_rate . '%' : '0.00%' }}</div>
    </div>
  </div>

  {{-- ── Primary Contact & Web ── --}}
  <div class="section-card">
    <div class="section-card-title">Primary Contact &amp; Web</div>
    <div class="kv-row">
      <div class="kv-label">Primary Phone</div>
      <div class="kv-value">{{ $service_provider->primary_phone ?? '—' }}</div>
    </div>
    <div class="kv-row">
      <div class="kv-label">Primary Email</div>
      <div class="kv-value">{{ $service_provider->primary_email ?? '—' }}</div>
    </div>
    <div class="kv-row">
      <div class="kv-label">Website</div>
      <div class="kv-value">{{ $service_provider->website ?? '—' }}</div>
    </div>
  </div>

  {{-- ── Addresses ── --}}
  @if($service_provider->addresses && $service_provider->addresses->isNotEmpty())
    <div class="section-card">
      <div class="section-card-title">Addresses ({{ $service_provider->addresses->count() }})</div>
      @foreach($service_provider->addresses as $index => $address)
        <div class="kv-row" style="{{ $index > 0 ? 'border-top: 2px solid var(--surface-2);' : '' }}">
          <div class="kv-label" style="color: var(--brand-navy); line-height: 1.7; flex-direction: column; align-items: flex-start;">
            <span>{{ \Illuminate\Support\Str::title(str_replace('_', ' ', $address->address_type ?? 'Location')) }}</span>
            @if($address->is_primary)
              <span class="badge badge--info" style="margin-top: 3px; font-size: 6pt;">Primary</span>
            @endif
            @if($address->is_billing)
              <span class="badge badge--secondary" style="margin-top: 3px; font-size: 6pt;">Billing</span>
            @endif
          </div>
          <div class="kv-value" style="line-height: 1.6;">
            {{ $address->street_address ?? $address->address_line_1 }}<br>
            @if($address->address_line_2) {{ $address->address_line_2 }}<br> @endif
            {{ collect([$address->city, $address->state, $address->postal_code ?? $address->postcode])->filter()->join(', ') }}<br>
            <span style="color: var(--text-secondary);">{{ $address->country }}</span>
          </div>
        </div>
      @endforeach
    </div>
  @endif

  {{-- ── Key Contacts ── --}}
  @if($service_provider->contacts && $service_provider->contacts->isNotEmpty())
    <div class="section-card">
      <div class="section-card-title">Key Contacts ({{ $service_provider->contacts->count() }})</div>
      @foreach($service_provider->contacts as $index => $contact)
        <div class="kv-row" style="{{ $index > 0 ? 'border-top: 2px solid var(--surface-2);' : '' }}">
          <div class="kv-label" style="color: var(--brand-navy); line-height: 1.7; flex-direction: column; align-items: flex-start;">
            <span>{{ $contact->first_name }} {{ $contact->last_name }}</span>
            @if($contact->is_primary)
              <span class="badge badge--info" style="margin-top: 3px; font-size: 6pt;">Primary</span>
            @endif
          </div>
          <div class="kv-value" style="line-height: 1.5;">
            <div>
              <strong style="font-size: 9.5pt;">{{ $contact->position ?? '—' }}</strong>
              @if($contact->department) <span style="color: var(--text-secondary); font-size: 9pt;">({{ $contact->department }})</span> @endif
            </div>
            <div style="font-size: 8.5pt; color: var(--text-secondary); margin-top: 4px;">
              @if($contact->email) <span style="display:inline-block; margin-right:8px;">✉ {{ $contact->email }}</span> @endif
              @if($contact->phone) <span style="display:inline-block;">☎ {{ $contact->phone }}</span> @endif
            </div>
          </div>
        </div>
      @endforeach
    </div>
  @endif

  {{-- ── Service Provider Status & Relationship ── --}}
  <div class="section-card">
    <div class="section-card-title">Service Provider Status &amp; Relationship</div>
    <div class="kv-row">
      <div class="kv-label">Partner Since</div>
      <div class="kv-value">{{ $service_provider->partner_since ? \Carbon\Carbon::parse($service_provider->partner_since)->format('d M Y') : '—' }}</div>
    </div>
    <div class="kv-row">
      <div class="kv-label">Account Manager</div>
      <div class="kv-value">{{ $service_provider->accountManager ? $service_provider->accountManager->full_name : 'Unassigned' }}</div>
    </div>
    <div class="kv-row">
      <div class="kv-label">Satisfaction Score</div>
      <div class="kv-value">
        @if($service_provider->satisfaction_score)
          <span style="font-weight: 700; color: {{ $service_provider->satisfaction_score >= 8 ? '#15803d' : ($service_provider->satisfaction_score >= 5 ? '#a16207' : '#b91c1c') }}">
            {{ $service_provider->satisfaction_score }}/10
          </span>
        @else
          —
        @endif
      </div>
    </div>
  </div>
@endsection
