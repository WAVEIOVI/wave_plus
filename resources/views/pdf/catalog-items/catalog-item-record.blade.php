@extends('pdf.layouts.pdf-vertical')

@section('title', 'Catalog Item Record')
@section('report-title', 'Catalog Item Details — ' . $catalogItem->code)

@section('content')
  @php
    $statusMap = [
      'active' => 'success',
      'draft' => 'warning',
      'discontinued' => 'error',
    ];
    $variant = $statusMap[$catalogItem->status] ?? 'info';
    $typeLabels = [
      'product' => 'Product',
      'consumable' => 'Consumable',
      'eq_servicing' => 'Equipment Servicing',
    ];
    $initials = strtoupper(substr($catalogItem->code ?? 'I', 0, 2));
  @endphp

  {{-- ── Profile Header ── --}}
  <div class="profile-header">
    <div class="profile-header-inner">
      <div class="profile-avatar">{{ $initials }}</div>
      <div class="profile-info">
        <div class="profile-name">{{ $catalogItem->name['en'] ?? $catalogItem->name ?? 'Unnamed Item' }}</div>
        <div class="profile-subtitle">{{ $catalogItem->code }}</div>
        <div class="profile-badges">
          <span class="badge badge--{{ $variant }}" style="text-transform: capitalize;">
            {{ $catalogItem->status ?? 'Unknown' }}
          </span>
          <span class="badge badge--primary" style="text-transform: capitalize;">
            {{ $typeLabels[$catalogItem->item_type] ?? $catalogItem->item_type }}
          </span>
        </div>
      </div>
    </div>
  </div>

  {{-- ── Basic Information ── --}}
  <div class="section-card">
    <div class="section-card-title">Basic Information</div>
    <div class="kv-row">
      <div class="kv-label">Code</div>
      <div class="kv-value"><strong>{{ $catalogItem->code }}</strong></div>
    </div>
    <div class="kv-row">
      <div class="kv-label">Name</div>
      <div class="kv-value">{{ $catalogItem->name['en'] ?? $catalogItem->name ?? '—' }}</div>
    </div>
    <div class="kv-row">
      <div class="kv-label">Type</div>
      <div class="kv-value">{{ $typeLabels[$catalogItem->item_type] ?? $catalogItem->item_type }}</div>
    </div>
    <div class="kv-row">
      <div class="kv-label">Category</div>
      <div class="kv-value">{{ $catalogItem->category ? ($catalogItem->category->name['en'] ?? $catalogItem->category->name ?? '—') : '—' }}</div>
    </div>
    <div class="kv-row">
      <div class="kv-label">Brand</div>
      <div class="kv-value">{{ $catalogItem->brand ? ($catalogItem->brand->name['en'] ?? $catalogItem->brand->name ?? '—') : '—' }}</div>
    </div>
    <div class="kv-row">
      <div class="kv-label">Unit of Measure</div>
      <div class="kv-value">{{ $catalogItem->uom ? ($catalogItem->uom->name['en'] ?? $catalogItem->uom->name ?? '—') : '—' }}</div>
    </div>
    <div class="kv-row">
      <div class="kv-label">Base Price</div>
      <div class="kv-value">{{ $catalogItem->base_price ? number_format($catalogItem->base_price, 2) . ' TND' : '—' }}</div>
    </div>
    <div class="kv-row">
      <div class="kv-label">Status</div>
      <div class="kv-value">
        <span class="badge badge--{{ $variant }}" style="text-transform: capitalize;">
          {{ $catalogItem->status ?? 'Unknown' }}
        </span>
      </div>
    </div>
  </div>

  {{-- ── Description ── --}}
  @if($catalogItem->short_description || $catalogItem->description)
  <div class="section-card">
    <div class="section-card-title">Description</div>
    <div class="description-text">
      @if($catalogItem->short_description)
        <p><strong>Short Description:</strong> {{ $catalogItem->short_description['en'] ?? $catalogItem->short_description ?? '—' }}</p>
      @endif
      @if($catalogItem->description)
        <p><strong>Full Description:</strong> {{ $catalogItem->description['en'] ?? $catalogItem->description ?? '—' }}</p>
      @endif
    </div>
  </div>
  @endif

  {{-- ── Type-Specific Information ── --}}
  @if($catalogItem->item_type === 'product' && $catalogItem->product)
  <div class="section-card">
    <div class="section-card-title">Product Details</div>
    <div class="kv-row">
      <div class="kv-label">Weight</div>
      <div class="kv-value">{{ $catalogItem->product->weight ? $catalogItem->product->weight . ' kg' : '—' }}</div>
    </div>
    <div class="kv-row">
      <div class="kv-label">Dimensions</div>
      <div class="kv-value">
        @if($catalogItem->product->length && $catalogItem->product->width && $catalogItem->product->height)
          {{ $catalogItem->product->length }} × {{ $catalogItem->product->width }} × {{ $catalogItem->product->height }} cm
        @else
          —
        @endif
      </div>
    </div>
    <div class="kv-row">
      <div class="kv-label">Stockable</div>
      <div class="kv-value">{{ $catalogItem->is_stockable ? 'Yes' : 'No' }}</div>
    </div>
    <div class="kv-row">
      <div class="kv-label">Sellable</div>
      <div class="kv-value">{{ $catalogItem->is_sellable ? 'Yes' : 'No' }}</div>
    </div>
  </div>
  @endif

  @if($catalogItem->item_type === 'consumable' && $catalogItem->consumable)
  <div class="section-card">
    <div class="section-card-title">Consumable Details</div>
    <div class="kv-row">
      <div class="kv-label">Is Consumable</div>
      <div class="kv-value">{{ $catalogItem->consumable->is_consumable ? 'Yes' : 'No' }}</div>
    </div>
    <div class="kv-row">
      <div class="kv-label">Stockable</div>
      <div class="kv-value">{{ $catalogItem->is_stockable ? 'Yes' : 'No' }}</div>
    </div>
    <div class="kv-row">
      <div class="kv-label">Sellable</div>
      <div class="kv-value">{{ $catalogItem->is_sellable ? 'Yes' : 'No' }}</div>
    </div>
  </div>
  @endif

  @if($catalogItem->item_type === 'eq_servicing' && $catalogItem->eqServicing)
  <div class="section-card">
    <div class="section-card-title">Equipment Servicing Details</div>
    <div class="kv-row">
      <div class="kv-label">Service Type</div>
      <div class="kv-value">{{ $catalogItem->eqServicing->service_type ?? '—' }}</div>
    </div>
    <div class="kv-row">
      <div class="kv-label">Estimated Duration</div>
      <div class="kv-value">{{ $catalogItem->eqServicing->estimated_duration_hours ? $catalogItem->eqServicing->estimated_duration_hours . ' hours' : '—' }}</div>
    </div>
    <div class="kv-row">
      <div class="kv-label">Creates Intervention Record</div>
      <div class="kv-value">{{ $catalogItem->eqServicing->creates_intervention_record ? 'Yes' : 'No' }}</div>
    </div>
  </div>
  @endif

  {{-- ── Suppliers ── --}}
  @if($catalogItem->suppliers && $catalogItem->suppliers->count() > 0)
  <div class="section-card">
    <div class="section-card-title">Suppliers</div>
    @foreach($catalogItem->suppliers as $supplier)
    <div class="supplier-item">
      <div class="supplier-name">{{ $supplier->supplier->commercial_name ?? $supplier->supplier->legal_name ?? 'Unknown Supplier' }}</div>
      <div class="supplier-details">
        <span>Reference: {{ $supplier->supplier_reference ?? '—' }}</span>
        <span>Price: {{ $supplier->supplier_price ? number_format($supplier->supplier_price, 2) . ' ' . ($supplier->currency ?? 'TND') : '—' }}</span>
        <span>Lead Time: {{ $supplier->lead_time_days ? $supplier->lead_time_days . ' days' : '—' }}</span>
        @if($supplier->is_preferred)
          <span class="badge badge--success">Preferred</span>
        @endif
      </div>
    </div>
    @endforeach
  </div>
  @endif

  {{-- ── Tags & Certifications ── --}}
  @if(($catalogItem->tags && $catalogItem->tags->count() > 0) || ($catalogItem->certifications && $catalogItem->certifications->count() > 0))
  <div class="section-card">
    <div class="section-card-title">Tags & Certifications</div>
    @if($catalogItem->tags && $catalogItem->tags->count() > 0)
    <div class="kv-row">
      <div class="kv-label">Tags</div>
      <div class="kv-value">
        @foreach($catalogItem->tags as $tag)
          <span class="badge badge--info">{{ $tag->tag }}</span>
        @endforeach
      </div>
    </div>
    @endif
    @if($catalogItem->certifications && $catalogItem->certifications->count() > 0)
    <div class="kv-row">
      <div class="kv-label">Certifications</div>
      <div class="kv-value">
        @foreach($catalogItem->certifications as $cert)
          <span class="badge badge--success">{{ $cert->certification }}</span>
        @endforeach
      </div>
    </div>
    @endif
  </div>
  @endif
@endsection
