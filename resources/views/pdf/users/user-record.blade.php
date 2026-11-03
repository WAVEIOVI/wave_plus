@extends('pdf.layouts.pdf-vertical')

@section('title', 'User Record')
@section('report-title', 'User Details')

@section('content')
  @php
    $statusMap = [
      'active'    => 'success',
      'inactive'  => 'secondary',
      'pending'   => 'warning',
      'suspended' => 'error',
    ];
    $variant = $statusMap[$user->account_status] ?? 'info';

    $initials = '';
    if ($user->first_name || $user->last_name) {
      $initials = strtoupper(substr($user->first_name ?? '', 0, 1) . substr($user->last_name ?? '', 0, 1));
    } else {
      $initials = strtoupper(substr($user->name ?? 'U', 0, 2));
    }
  @endphp

  <div class="record-card">
    <div class="record-header">
      <div class="avatar">{{ $initials }}</div>
      <div class="name">{{ $user->full_name }}</div>
      <div class="email">{{ $user->email }}</div>
      <div class="badges">
        <span class="badge badge--{{ $variant }}">
          {{ $user->account_status ?? 'Unknown' }}
        </span>
        <span class="badge badge--secondary">
          {{ $user->getRoleNames()->first() ?? 'No Role' }}
        </span>
      </div>
    </div>

    <div class="record-body">
      <div class="record-section-title">Personal Information</div>
      <div class="record-grid">
        <div class="record-row">
          <div class="record-label">Full Name</div>
          <div class="record-value">{{ $user->full_name }}</div>
        </div>
        <div class="record-row">
          <div class="record-label">First Name</div>
          <div class="record-value">{{ $user->first_name ?? '—' }}</div>
        </div>
        <div class="record-row">
          <div class="record-label">Last Name</div>
          <div class="record-value">{{ $user->last_name ?? '—' }}</div>
        </div>
        <div class="record-row">
          <div class="record-label">Date of Birth</div>
          <div class="record-value">{{ $user->date_of_birth ? \Carbon\Carbon::parse($user->date_of_birth)->format('d M Y') : '—' }}</div>
        </div>
        <div class="record-row">
          <div class="record-label">Gender</div>
          <div class="record-value" style="text-transform: capitalize;">{{ $user->gender ?? '—' }}</div>
        </div>
      </div>

      <div class="record-section-title">Account Information</div>
      <div class="record-grid">
        <div class="record-row">
          <div class="record-label">User Type</div>
          <div class="record-value" style="text-transform: capitalize;">{{ $user->user_type ?? '—' }}</div>
        </div>
        <div class="record-row">
          <div class="record-label">Active</div>
          <div class="record-value">{{ $user->is_active ? 'Yes' : 'No' }}</div>
        </div>
        <div class="record-row">
          <div class="record-label">Can Login</div>
          <div class="record-value">{{ $user->can_login ? 'Yes' : 'No' }}</div>
        </div>
        <div class="record-row">
          <div class="record-label">Suspended</div>
          <div class="record-value">{{ $user->is_suspended ? 'Yes' : 'No' }}</div>
        </div>
        @if ($user->is_suspended && $user->suspension_reason)
        <div class="record-row">
          <div class="record-label">Suspension Reason</div>
          <div class="record-value" style="color: #b91c1c;">{{ $user->suspension_reason }}</div>
        </div>
        @endif
        <div class="record-row">
          <div class="record-label">Role(s)</div>
          <div class="record-value">{{ $user->getRoleNames()->implode(', ') ?: '—' }}</div>
        </div>
        <div class="record-row">
          <div class="record-label">Account Created</div>
          <div class="record-value">{{ $user->created_at?->format('d M Y, h:i A') }}</div>
        </div>
      </div>
    </div>
  </div>
@endsection
