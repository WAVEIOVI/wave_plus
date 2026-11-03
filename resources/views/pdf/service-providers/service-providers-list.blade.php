@extends('pdf.layouts.pdf-horizontal')

@section('title', 'Service Providers Directory')
@section('report-title', 'Business Partners — Service Providers List')

@section('content')
  @php
    $records = $serviceProviders ?? $service_providers ?? $suppliers ?? collect();
  @endphp
  <table class="data-table">
    <thead>
      <tr>
        <th style="width: 4%; text-align: center;">#</th>
        <th style="width: 14%;">Provider ID</th>
        <th style="width: 28%;">Legal Name</th>
        <th style="width: 14%;">Industry</th>
        <th style="width: 14%;">Category / Tier</th>
        <th style="width: 12%;">Status</th>
        <th style="width: 14%;">Partner Since</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($records as $index => $provider)
        <tr>
          <td>{{ $index + 1 }}</td>
          <td style="font-weight: 600;">{{ $provider->service_provider_id ?? $provider->supplier_id ?? '—' }}</td>
          <td>{{ $provider->legal_name }}</td>
          <td style="text-transform: capitalize;">{{ $provider->industry ?? '—' }}</td>
          <td style="text-transform: capitalize;">{{ $provider->service_provider_category ?? $provider->provider_tier ?? '—' }}</td>
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
              $variant = $statusMap[$provider->status] ?? 'info';
            @endphp
            <span class="badge badge--{{ $variant }}">{{ $provider->status ?? '—' }}</span>
          </td>
          <td class="text-mono">
            {{ $provider->partner_since ? \Carbon\Carbon::parse($provider->partner_since)->format('d M Y') : '—' }}
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="7" style="text-align: center; padding: 40px 10px; color: var(--text-muted);">
            No service providers found in the system.
          </td>
        </tr>
      @endforelse
    </tbody>
  </table>

  <div class="summary-footer">
    Total Records: <strong>{{ count($records) }}</strong> service provider(s)
  </div>
@endsection
