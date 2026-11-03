@extends('pdf.layouts.pdf-vertical')

@section('title', 'Supplier Record')
@section('report-title', 'Supplier Details — ' . $supplier->supplier_id)

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
    $variant = $statusMap[$supplier->status] ?? 'info';
    $primaryContact = $supplier->contacts?->where('is_primary', true)->first() ?? $supplier->contacts?->first();
    $initials = strtoupper(substr($supplier->legal_name ?? 'C', 0, 2));
  @endphp

  {{-- ── Profile Header ── --}}
  <div class="profile-header">
    <div class="profile-header-inner">
      <div class="profile-avatar">{{ $initials }}</div>
      <div class="profile-info">
        <div class="profile-name">{{ $supplier->legal_name }}</div>
        <div class="profile-subtitle">{{ $supplier->primary_email ?? ($primaryContact->email ?? 'No email provided') }}</div>
        <div class="profile-badges">
          <span class="badge badge--{{ $variant }}" style="text-transform: capitalize;">
            {{ $supplier->status ?? 'Unknown' }}
          </span>
          @if($supplier->supplier_category)
          <span class="badge badge--primary" style="text-transform: capitalize;">
            {{ str_replace('_', ' ', $supplier->supplier_category) }}
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
      <div class="kv-label">Supplier ID</div>
      <div class="kv-value"><strong>{{ $supplier->supplier_id }}</strong></div>
    </div>
    <div class="kv-row">
      <div class="kv-label">Legal Name</div>
      <div class="kv-value">{{ $supplier->legal_name }}</div>
    </div>
    <div class="kv-row">
      <div class="kv-label">Commercial Name</div>
      <div class="kv-value">{{ is_array($supplier->commercial_name) ? ($supplier->commercial_name[app()->getLocale()] ?? collect($supplier->commercial_name)->first()) : ($supplier->commercial_name ?? '—') }}</div>
    </div>
    <div class="kv-row">
      <div class="kv-label">Industry</div>
      <div class="kv-value" style="text-transform: capitalize;">{{ $supplier->industry ?? '—' }}</div>
    </div>
    <div class="kv-row">
      <div class="kv-label">Company Size</div>
      <div class="kv-value" style="text-transform: capitalize;">{{ $supplier->company_size ?? '—' }}</div>
    </div>
    <div class="kv-row">
      <div class="kv-label">Legal Status</div>
      <div class="kv-value" style="text-transform: capitalize;">{{ $supplier->legal_status ?? '—' }}</div>
    </div>
  </div>

  {{-- ── Registration & Tax ── --}}
  <div class="section-card">
    <div class="section-card-title">Registration &amp; Tax Information</div>
    <div class="kv-row">
      <div class="kv-label">Tax ID</div>
      <div class="kv-value">{{ $supplier->tax_id ?? '—' }}</div>
    </div>
    <div class="kv-row">
      <div class="kv-label">RNE ID</div>
      <div class="kv-value">{{ $supplier->rne_id ?? '—' }}</div>
    </div>
    <div class="kv-row">
      <div class="kv-label">Year Founded</div>
      <div class="kv-value">{{ $supplier->founding_year ?? '—' }}</div>
    </div>
  </div>

  {{-- ── Financial Data ── --}}
  <div class="section-card">
    <div class="section-card-title">Financial Data</div>
    <div class="kv-row">
      <div class="kv-label">Credit Limit</div>
      <div class="kv-value">{{ $supplier->credit_limit ? number_format($supplier->credit_limit, 3) : '0.000' }}</div>
    </div>
    <div class="kv-row">
      <div class="kv-label">Payment Terms</div>
      <div class="kv-value" style="text-transform: capitalize;">{{ str_replace('_', ' ', $supplier->payment_terms ?? '—') }}</div>
    </div>
    <div class="kv-row">
      <div class="kv-label">Default Currency</div>
      <div class="kv-value">{{ $supplier->default_currency ?? 'TND' }}</div>
    </div>
    <div class="kv-row">
      <div class="kv-label">Discount Rate</div>
      <div class="kv-value">{{ $supplier->discount_rate ? $supplier->discount_rate . '%' : '0.00%' }}</div>
    </div>
  </div>

  {{-- ── Primary Contact & Web ── --}}
  <div class="section-card">
    <div class="section-card-title">Primary Contact &amp; Web</div>
    <div class="kv-row">
      <div class="kv-label">Primary Phone</div>
      <div class="kv-value">{{ $supplier->primary_phone ?? '—' }}</div>
    </div>
    <div class="kv-row">
      <div class="kv-label">Primary Email</div>
      <div class="kv-value">{{ $supplier->primary_email ?? '—' }}</div>
    </div>
    <div class="kv-row">
      <div class="kv-label">Website</div>
      <div class="kv-value">{{ $supplier->website ?? '—' }}</div>
    </div>
  </div>

  {{-- ── Addresses ── --}}
  @if($supplier->addresses && $supplier->addresses->isNotEmpty())
    <div class="section-card">
      <div class="section-card-title">Addresses ({{ $supplier->addresses->count() }})</div>
      @foreach($supplier->addresses as $index => $address)
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
  @if($supplier->contacts && $supplier->contacts->isNotEmpty())
    <div class="section-card">
      <div class="section-card-title">Key Contacts ({{ $supplier->contacts->count() }})</div>
      @foreach($supplier->contacts as $index => $contact)
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

  {{-- ── Supplier Status & Relationship ── --}}
  <div class="section-card">
    <div class="section-card-title">Supplier Status &amp; Relationship</div>
    <div class="kv-row">
      <div class="kv-label">Partner Since</div>
      <div class="kv-value">{{ $supplier->partner_since ? \Carbon\Carbon::parse($supplier->partner_since)->format('d M Y') : '—' }}</div>
    </div>
    <div class="kv-row">
      <div class="kv-label">Account Manager</div>
      <div class="kv-value">{{ $supplier->accountManager ? $supplier->accountManager->full_name : 'Unassigned' }}</div>
    </div>
    <div class="kv-row">
      <div class="kv-label">Satisfaction Score</div>
      <div class="kv-value">
        @if($supplier->satisfaction_score)
          <span style="font-weight: 700; color: {{ $supplier->satisfaction_score >= 8 ? '#15803d' : ($supplier->satisfaction_score >= 5 ? '#a16207' : '#b91c1c') }}">
            {{ $supplier->satisfaction_score }}/10
          </span>
        @else
          —
        @endif
      </div>
    </div>
  </div>
@endsection
