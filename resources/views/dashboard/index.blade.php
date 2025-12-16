@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<section class="dash-stage">
  <div class="vo-pattern"></div>

  <div class="dash-content">
    <div class="dash-header">
      <div class="dash-greeting">
        <div class="greet-line">Hey Vikas,</div>
        <div class="greet-line">Good Morning!</div>
      </div>
      <button class="create-task solid">+ Create Task</button>
    </div>

    <div class="dash-tasks">
      <h2>Tasks For The Day!</h2>
      <div class="task-list">
        <div class="task-row">
          <span class="task-checkbox" aria-hidden="true"></span>
          <div class="task-pill"></div>
        </div>
        <div class="task-row">
          <span class="task-checkbox" aria-hidden="true"></span>
          <div class="task-pill"></div>
        </div>
        <div class="task-row">
          <span class="task-checkbox" aria-hidden="true"></span>
          <div class="task-pill"></div>
        </div>
        <div class="task-row">
          <span class="task-checkbox" aria-hidden="true"></span>
          <div class="task-pill"></div>
        </div>
      </div>
      <div class="task-actions">
        <button class="pill-btn">View All</button>
      </div>
    </div>

    <div class="punch-actions">
      <button class="pill-btn">Punch In</button>
      <button class="pill-btn">Punch Out</button>
    </div>
  </div>
</section>
@endsection
