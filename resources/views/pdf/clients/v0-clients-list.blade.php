@extends('pdf.layouts.pdf-horizontal')

@section('title', 'Clients Directory')
@section('report-title', 'Business Partners — Clients List')

@section('content')
  <table class="data-table">
    <thead>
      <tr>
        <th style="width: 4%; text-align: center;">#</th>
        <th style="width: 12%;">Client ID</th>
        <th style="width: 28%;">Legal Name</th>
        <th style="width: 14%;">Industry</th>
        <th style="width: 12%;">Category</th>
        <th style="width: 10%;">Status</th>
        <th style="width: 12%;">Partner Since</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($clients as $index => $client)
        <tr>
          <td>{{ $index + 1 }}</td>
          <td style="font-weight: 600;">{{ $client->client_id }}</td>
          <td>{{ $client->legal_name }}</td>
          <td style="text-transform: capitalize;">{{ $client->industry ?? '—' }}</td>
          <td style="text-transform: capitalize;">{{ $client->client_category ?? '—' }}</td>
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
              $variant = $statusMap[$client->status] ?? 'info';
            @endphp
            <span class="badge badge--{{ $variant }}">{{ $client->status ?? '—' }}</span>
          </td>
          <td class="text-mono">
            {{ $client->partner_since ? \Carbon\Carbon::parse($client->partner_since)->format('d M Y') : '—' }}
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="7" style="text-align: center; padding: 40px 10px; color: var(--text-muted);">
            No clients found in the system.
          </td>
        </tr>
      @endforelse
    </tbody>
  </table>

  <div class="summary-footer">
    Total Records: <strong>{{ count($clients) }}</strong> client(s)
  </div>
@endsection
