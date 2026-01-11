@extends('layouts.app')

@section('title', 'Leaves')

@section('content')
<section class="leave-page">
  <div class="vo-pattern"></div>
  <div class="leave-shell">
    <header class="leave-head">
      <div>
        <div class="eyebrow">Leaves</div>
        <h1>Leave Management</h1>
        <p class="muted">Apply for leave and track approvals. Weekends and public holidays are excluded.</p>
      </div>
      <div class="leave-head-meta">
        @if($isAdmin)
          <a class="leave-settings-btn" href="#leave-admin" aria-label="Leave settings"></a>
        @else
          <button class="leave-settings-btn" type="button" id="leave-settings-btn" aria-label="Leave settings"></button>
        @endif
        <div class="leave-year">Year {{ $year }}</div>
        @if(session('status'))
          <div class="pill success">{{ session('status') }}</div>
        @endif
      </div>
    </header>

    @if($errors->any())
      <div class="leave-alert error">
        <div class="leave-alert-title">Please fix the following:</div>
        <ul>
          @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <div class="leave-balance-grid">
      @foreach($categories as $category)
        @php
          $balance = $balances[$category->id] ?? ['allowance' => 0, 'used' => 0, 'remaining' => 0];
        @endphp
        <div class="leave-balance-card">
          <div class="leave-balance-head">
            <div class="leave-balance-title">{{ $category->name }}</div>
            <div class="leave-balance-total">{{ $balance['remaining'] }}</div>
          </div>
          <div class="leave-balance-meta">Remaining days</div>
          <div class="leave-balance-stats">
            <div>
              <div class="leave-stat-label">Allowance</div>
              <div class="leave-stat-value">{{ $balance['allowance'] }}</div>
            </div>
            <div>
              <div class="leave-stat-label">Used</div>
              <div class="leave-stat-value">{{ $balance['used'] }}</div>
            </div>
          </div>
        </div>
      @endforeach
    </div>

    <div class="leave-grid">
      <form class="leave-card leave-request" method="POST" action="{{ route('leaves.store') }}">
        @csrf
        <div class="card-head">
          <div>
            <div class="eyebrow">Apply</div>
            <h2>Request Leave</h2>
          </div>
        </div>
        <div class="leave-form-grid">
          <label class="field">
            <span class="label">Category</span>
            <select name="category_id" required>
              @foreach($categories as $category)
                <option value="{{ $category->id }}">{{ $category->name }}</option>
              @endforeach
            </select>
          </label>
          <label class="field">
            <span class="label">Start date</span>
            <input type="date" name="start_date" required>
          </label>
          <label class="field">
            <span class="label">End date</span>
            <input type="date" name="end_date" required>
          </label>
          <label class="field field-full">
            <span class="label">Reason (optional)</span>
            <textarea name="reason" rows="3" placeholder="Add a short reason">{{ old('reason') }}</textarea>
          </label>
        </div>
        <div class="form-actions">
          <button class="pill-btn solid" type="submit">Submit Request</button>
        </div>
      </form>

      <div class="leave-card leave-guidelines">
        <div class="card-head">
          <div>
            <div class="eyebrow">Policy</div>
            <h2>Leave rules</h2>
          </div>
        </div>
        <ul class="leave-list">
          <li>Balances reset yearly by category.</li>
          <li>Requests count only working days between start and end dates.</li>
          <li>Admins approve or reject requests with notifications.</li>
        </ul>
        <div class="holiday-strip">
          <div class="holiday-title">Upcoming holidays</div>
          <div class="holiday-list">
            @forelse($holidays->take(4) as $holiday)
              <span>{{ $holiday->name }} - {{ $holiday->date->format('d M') }}</span>
            @empty
              <span class="muted">No holidays added yet.</span>
            @endforelse
          </div>
        </div>
      </div>
    </div>

    <div class="leave-history">
      <div class="card-head">
        <div>
          <div class="eyebrow">History</div>
          <h2>My Leave Requests</h2>
        </div>
      </div>
      <div class="leave-history-grid">
        @forelse($requests as $request)
          <div class="leave-history-item">
            <div>
              <div class="leave-history-title">{{ $request->category->name ?? 'Leave' }}</div>
              <div class="leave-history-dates">
                {{ $request->start_date->format('d M') }} - {{ $request->end_date->format('d M') }}
              </div>
              <div class="leave-history-meta">
                {{ $request->total_days }} day(s)
                @if($request->excluded_days)
                  • {{ $request->excluded_days }} excluded
                @endif
              </div>
              @if($request->reason)
                <div class="leave-history-reason">{{ $request->reason }}</div>
              @endif
              @if($request->status !== 'pending' && $request->approver)
                <div class="leave-history-meta">By {{ $request->approver->name }}</div>
              @endif
              @if($request->status === 'rejected' && $request->rejection_reason)
                <div class="leave-history-reject">Reason: {{ $request->rejection_reason }}</div>
              @endif
            </div>
            <div class="leave-history-status">
              <span class="status-badge {{ $request->status }}">{{ ucfirst($request->status) }}</span>
            </div>
          </div>
        @empty
          <div class="muted">No leave requests yet.</div>
        @endforelse
      </div>
    </div>

    @if($isAdmin)
      <div class="leave-admin" id="leave-admin">
        <div class="card-head">
          <div>
            <div class="eyebrow">Admin</div>
            <h2>Manage approvals</h2>
            <p class="muted">Review requests and keep leave policy updated.</p>
          </div>
        </div>
        <div class="leave-admin-grid">
          <div class="leave-card">
            <div class="card-head">
              <div>
                <div class="eyebrow">Policy</div>
                <h3>Leave allowances</h3>
              </div>
            </div>
            <form class="settings-panel-form" method="POST" action="{{ route('leaves.categories.update') }}">
              @csrf
              <div class="settings-grid leave-policy-grid">
                @foreach(($categories ?? collect()) as $category)
                  <label>
                    <span>{{ $category->name }} allowance</span>
                    <input type="hidden" name="categories[{{ $category->id }}][id]" value="{{ $category->id }}">
                    <input type="number" name="categories[{{ $category->id }}][yearly_allowance]" min="0" max="365" value="{{ $category->yearly_allowance }}">
                  </label>
                @endforeach
              </div>
              <div class="form-actions">
                <button type="submit" class="pill-btn solid">Save Allowances</button>
              </div>
            </form>
          </div>
          <div class="leave-card">
            <div class="card-head">
              <div>
                <div class="eyebrow">Policy</div>
                <h3>Public holidays</h3>
              </div>
            </div>
            <form class="leave-holiday-form" method="POST" action="{{ route('leaves.holidays.store') }}">
              @csrf
              <input type="text" name="name" placeholder="Holiday name" required>
              <input type="date" name="date" required>
              <button type="submit" class="pill-btn solid">Add Holiday</button>
            </form>
            <div class="leave-holiday-list">
              @forelse(($holidays ?? collect()) as $holiday)
                <div class="leave-holiday-item">
                  <div>
                    <div class="leave-history-title">{{ $holiday->name }}</div>
                    <div class="leave-history-meta">{{ $holiday->date->format('d M Y') }}</div>
                  </div>
                  <form method="POST" action="{{ route('leaves.holidays.delete', $holiday) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="pill-btn ghost">Remove</button>
                  </form>
                </div>
              @empty
                <div class="muted">No holidays added.</div>
              @endforelse
            </div>
          </div>
          <div class="leave-card">
            <div class="card-head">
              <div>
                <div class="eyebrow">Approvals</div>
                <h3>Pending requests</h3>
              </div>
            </div>
            <div class="leave-pending-list">
              @forelse($pending as $pendingLeave)
                <div class="leave-pending-item">
                  <div>
                    <div class="leave-history-title">{{ $pendingLeave->user->name ?? 'User' }} - {{ $pendingLeave->category->name ?? 'Leave' }}</div>
                    <div class="leave-history-dates">
                      {{ $pendingLeave->start_date->format('d M') }} - {{ $pendingLeave->end_date->format('d M') }}
                    </div>
                    <div class="leave-history-meta">
                      {{ $pendingLeave->total_days }} day(s)
                      @if($pendingLeave->excluded_days)
                        • {{ $pendingLeave->excluded_days }} excluded
                      @endif
                    </div>
                    @if($pendingLeave->reason)
                      <div class="leave-history-reason">{{ $pendingLeave->reason }}</div>
                    @endif
                  </div>
                  <div class="leave-pending-actions">
                    <form method="POST" action="{{ route('leaves.approve', $pendingLeave) }}">
                      @csrf
                      <button type="submit" class="pill-btn solid">Approve</button>
                    </form>
                    <form method="POST" action="{{ route('leaves.reject', $pendingLeave) }}" class="leave-reject-form">
                      @csrf
                      <input type="text" name="rejection_reason" placeholder="Rejection reason (optional)">
                      <button type="submit" class="pill-btn ghost">Reject</button>
                    </form>
                  </div>
                </div>
              @empty
                <div class="muted">No pending approvals.</div>
              @endforelse
            </div>
          </div>
        </div>
      </div>
    @endif
  </div>
</section>
@if(!$isAdmin)
  @push('scripts')
  <script>
    (function(){
      const btn = document.getElementById('leave-settings-btn');
      if (!btn) return;
      btn.addEventListener('click', () => {
        alert('Admin permissions required to update leave settings.');
      });
    })();
  </script>
  @endpush
@endif
@endsection
