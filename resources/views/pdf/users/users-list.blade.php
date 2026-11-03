@extends('pdf.layouts.pdf-horizontal')

@section('title', 'Users Directory')
@section('report-title', 'System Users List')

@section('content')
  <table class="data-table">
    <thead>
      <tr>
        <th style="width: 4%; text-align: center;">#</th>
        <th style="width: 23%;">Full Name</th>
        <th style="width: 22%;">Email</th>
        <th style="width: 14%;">Role</th>
        <th style="width: 10%;">Type</th>
        <th style="width: 12%;">Status</th>
        <th style="width: 15%;">Created</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($users as $index => $user)
        <tr>
          <td style="color: #94a3b8; text-align: center;">{{ $index + 1 }}</td>
          <td style="font-weight: 600; color: #0f172a;">{{ $user->full_name }}</td>
          <td style="color: #475569;">{{ $user->email }}</td>
          <td>{{ $user->getRoleNames()->first() ?? '—' }}</td>
          <td style="text-transform: capitalize; color: #475569;">{{ $user->user_type ?? '—' }}</td>
          <td>
            @php
              $statusMap = [
                'active'    => 'success',
                'inactive'  => 'secondary',
                'pending'   => 'warning',
                'suspended' => 'error',
              ];
              $variant = $statusMap[$user->account_status] ?? 'info';
            @endphp
            <span class="badge badge--{{ $variant }}">{{ $user->account_status ?? '—' }}</span>
          </td>
          <td style="color: #475569; font-family: 'DM Mono', monospace; font-size: 9.5px;">{{ $user->created_at?->format('d M Y') }}</td>
        </tr>
      @empty
        <tr>
          <td colspan="7" style="text-align: center; padding: 40px 10px; color: #94a3b8;">
            No users found in the system.
          </td>
        </tr>
      @endforelse
    </tbody>
  </table>

  <div style="margin-top: 12px; font-size: 10px; color: #94a3b8; text-align: right; font-family: 'DM Sans', sans-serif;">
    Total Records: <strong style="color: #0f172a; font-weight: 600;">{{ count($users) }}</strong> user(s)
  </div>
@endsection
