@extends('pdf.layouts.pdf-horizontal')

@section('title', 'Suppliers Directory')
@section('report-title', 'Business Partners — Suppliers List')

@section('content')
  <table class="data-table">
    <thead>
      <tr>
        <th style="width: 4%; text-align: center;">#</th>
        <th style="width: 12%;">Supplier ID</th>
        <th style="width: 28%;">Legal Name</th>
        <th style="width: 14%;">Industry</th>
        <th style="width: 12%;">Category</th>
        <th style="width: 10%;">Status</th>
        <th style="width: 12%;">Partner Since</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($suppliers as $index => $supplier)
        <tr>
          <td>{{ $index + 1 }}</td>
          <td style="font-weight: 600;">{{ $supplier->supplier_id }}</td>
          <td>{{ $supplier->legal_name }}</td>
          <td style="text-transform: capitalize;">{{ $supplier->industry ?? '—' }}</td>
          <td style="text-transform: capitalize;">{{ $supplier->supplier_category ?? '—' }}</td>
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
              $variant = $statusMap[$supplier->status] ?? 'info';
            @endphp
            <span class="badge badge--{{ $variant }}">{{ $supplier->status ?? '—' }}</span>
          </td>
          <td class="text-mono">
            {{ $supplier->partner_since ? \Carbon\Carbon::parse($supplier->partner_since)->format('d M Y') : '—' }}
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="7" style="text-align: center; padding: 40px 10px; color: var(--text-muted);">
            No suppliers found in the system.
          </td>
        </tr>
      @endforelse
    </tbody>
  </table>

  <div class="summary-footer">
    Total Records: <strong>{{ count($suppliers) }}</strong> supplier(s)
  </div>
@endsection
