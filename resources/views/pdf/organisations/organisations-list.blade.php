@extends('pdf.layouts.pdf-horizontal')

@section('title', 'Organisations Directory')
@section('report-title', 'Business Partners — Organisations List')

@section('content')
  <table class="data-table">
    <thead>
      <tr>
        <th style="width: 4%; text-align: center;">#</th>
        <th style="width: 12%;">Organisation ID</th>
        <th style="width: 28%;">Legal Name</th>
        <th style="width: 14%;">Industry</th>
        <th style="width: 12%;">Category</th>
        <th style="width: 10%;">Status</th>
        <th style="width: 12%;">Partner Since</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($organisations as $index => $organisation)
        <tr>
          <td>{{ $index + 1 }}</td>
          <td style="font-weight: 600;">{{ $organisation->organisation_id }}</td>
          <td>{{ $organisation->legal_name }}</td>
          <td style="text-transform: capitalize;">{{ $organisation->industry ?? '—' }}</td>
          <td style="text-transform: capitalize;">{{ $organisation->organisation_category ?? '—' }}</td>
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
              $variant = $statusMap[$organisation->status] ?? 'info';
            @endphp
            <span class="badge badge--{{ $variant }}">{{ $organisation->status ?? '—' }}</span>
          </td>
          <td class="text-mono">
            {{ $organisation->partner_since ? \Carbon\Carbon::parse($organisation->partner_since)->format('d M Y') : '—' }}
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="7" style="text-align: center; padding: 40px 10px; color: var(--text-muted);">
            No organisations found in the system.
          </td>
        </tr>
      @endforelse
    </tbody>
  </table>

  <div class="summary-footer">
    Total Records: <strong>{{ count($organisations) }}</strong> organisation(s)
  </div>
@endsection
