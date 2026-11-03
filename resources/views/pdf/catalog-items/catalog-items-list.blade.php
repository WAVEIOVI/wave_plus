@extends('pdf.layouts.pdf-horizontal')

@section('title', 'Catalog Items Directory')
@section('report-title', 'Catalog Items List')

@section('content')
  <table class="data-table">
    <thead>
      <tr>
        <th style="width: 4%; text-align: center;">#</th>
        <th style="width: 12%;">Code</th>
        <th style="width: 28%;">Name</th>
        <th style="width: 12%;">Type</th>
        <th style="width: 16%;">Category</th>
        <th style="width: 10%;">Base Price</th>
        <th style="width: 8%;">Status</th>
        <th style="width: 10%;">Created</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($catalogItems as $index => $item)
        <tr>
          <td>{{ $index + 1 }}</td>
          <td style="font-weight: 600;">{{ $item->code }}</td>
          <td>{{ $item->name['en'] ?? $item->name ?? '—' }}</td>
          <td style="text-transform: capitalize;">
            @php
              $typeLabels = [
                'product' => 'Product',
                'consumable' => 'Consumable',
                'eq_servicing' => 'Equipment Servicing',
              ];
            @endphp
            {{ $typeLabels[$item->item_type] ?? $item->item_type }}
          </td>
          <td>{{ $item->category ? ($item->category->name['en'] ?? $item->category->name ?? '—') : '—' }}</td>
          <td>{{ $item->base_price ? number_format($item->base_price, 2) : '—' }}</td>
          <td>
            @php
              $statusMap = [
                'active' => 'success',
                'draft' => 'warning',
                'discontinued' => 'error',
              ];
              $variant = $statusMap[$item->status] ?? 'info';
            @endphp
            <span class="badge badge--{{ $variant }}">{{ $item->status ?? '—' }}</span>
          </td>
          <td class="text-mono">
            {{ $item->created_at ? \Carbon\Carbon::parse($item->created_at)->format('d M Y') : '—' }}
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="8" style="text-align: center; padding: 40px 10px; color: var(--text-muted);">
            No catalog items found in the system.
          </td>
        </tr>
      @endforelse
    </tbody>
  </table>

  <div class="summary-footer">
    Total Records: <strong>{{ count($catalogItems) }}</strong> catalog item(s)
  </div>
@endsection
