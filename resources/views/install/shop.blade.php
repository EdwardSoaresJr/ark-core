@extends('install.layout')

@section('title', 'Shop')

@section('content')
    <h1>Shop</h1>
    <p class="lead">Identity is stored in ARK’s shop settings authority — not a second configuration system.</p>

    <form method="post" action="{{ route('install.shop.store') }}">
        @csrf
        <label for="shop_name">Shop name</label>
        <input id="shop_name" name="shop_name" required value="{{ old('shop_name', $draft['shop_name'] ?? '') }}">

        <label for="shop_timezone">Timezone</label>
        <select id="shop_timezone" name="shop_timezone" required>
            @foreach ($timezones as $tz)
                <option value="{{ $tz }}" @selected(old('shop_timezone', $draft['shop_timezone'] ?? 'America/Denver') === $tz)>{{ $tz }}</option>
            @endforeach
        </select>

        <div class="row">
            <div>
                <label for="phone">Phone</label>
                <input id="phone" name="phone" value="{{ old('phone', $draft['phone'] ?? '') }}">
            </div>
            <div>
                <label for="email">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email', $draft['email'] ?? '') }}">
            </div>
        </div>

        <label for="address_line_1">Address</label>
        <input id="address_line_1" name="address_line_1" value="{{ old('address_line_1', $draft['address_line_1'] ?? '') }}">

        <div class="row">
            <div>
                <label for="city">City</label>
                <input id="city" name="city" value="{{ old('city', $draft['city'] ?? '') }}">
            </div>
            <div>
                <label for="state">State</label>
                <input id="state" name="state" value="{{ old('state', $draft['state'] ?? '') }}">
            </div>
        </div>

        <label for="postal_code">Postal code</label>
        <input id="postal_code" name="postal_code" value="{{ old('postal_code', $draft['postal_code'] ?? '') }}">

        <div class="actions">
            <a class="btn btn-secondary" href="{{ route('install.database') }}">Back</a>
            <button class="btn btn-primary" type="submit">Continue</button>
        </div>
    </form>
@endsection
