@extends('install.layout')

@section('title', 'Administrator')

@section('content')
    <h1>First administrator</h1>
    <p class="lead">Creates a real ARK admin user with existing roles and permissions. Password is never written to installer draft files.</p>

    <form method="post" action="{{ route('install.admin.store') }}">
        @csrf
        <label for="admin_name">Name</label>
        <input id="admin_name" name="admin_name" required value="{{ old('admin_name', $draft['admin_name'] ?? '') }}">

        <label for="admin_email">Email</label>
        <input id="admin_email" name="admin_email" type="email" required value="{{ old('admin_email', $draft['admin_email'] ?? '') }}" autocomplete="username">

        <label for="password">Password</label>
        <input id="password" name="password" type="password" required autocomplete="new-password">

        <label for="password_confirmation">Confirm password</label>
        <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">

        <label style="display:flex;gap:.5rem;align-items:center;font-weight:500;margin-top:1rem;">
            <input type="checkbox" name="create_workstation" value="1" @checked(old('create_workstation', $draft['create_workstation'] ?? true)) style="width:auto">
            Create a default workstation named “Main Shop”
        </label>
        <p class="hint">Workstations are ARK’s physical-place concept. Login works without one; this is a convenient default.</p>

        <div class="actions">
            <a class="btn btn-secondary" href="{{ route('install.shop') }}">Back</a>
            <button class="btn btn-primary" type="submit">Continue</button>
        </div>
    </form>
@endsection
