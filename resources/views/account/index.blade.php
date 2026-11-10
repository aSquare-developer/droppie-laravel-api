@extends('layouts.workspace')

@section('title', 'Account - Droppie')

@section('content')
    <section class="page-narrow">
        <section class="panel-section">
            <div class="section-title">
                <div>
                    <h2>Profile</h2>
                    <p>Driver and vehicle details used for trips and PDF reports.</p>
                </div>
            </div>
            <form method="POST" action="{{ route('profile.update') }}" class="stacked-form profile-form">
                @csrf
                @method('PATCH')
                <div class="profile-form-grid">
                    <label>
                        <span>First name</span>
                        <input name="name" type="text" autocomplete="given-name" required maxlength="255" value="{{ old('name', $profile?->first_name) }}">
                    </label>
                    <label>
                        <span>Last name</span>
                        <input name="last_name" type="text" autocomplete="family-name" maxlength="255" value="{{ old('last_name', $profile?->last_name) }}">
                    </label>
                    <label>
                        <span>Email</span>
                        <input type="email" autocomplete="email" readonly aria-readonly="true" class="readonly-field" value="{{ $user->email }}">
                    </label>
                    <label>
                        <span>Company name</span>
                        <input name="company_name" type="text" autocomplete="organization" maxlength="255" value="{{ old('company_name', $profile?->company_name) }}">
                    </label>
                    <label>
                        <span>Vehicle registration number</span>
                        <input name="car_registration_number" type="text" maxlength="50" value="{{ old('car_registration_number', $vehicle?->registration_number) }}">
                    </label>
                    <label>
                        <span>Vehicle make and model</span>
                        <input name="car_make_model" type="text" maxlength="255" value="{{ old('car_make_model', $vehicle?->make_model) }}">
                    </label>
                    <label>
                        <span>Vehicle mileage</span>
                        <input name="car_mileage" type="number" min="0" step="0.1" inputmode="decimal" value="{{ old('car_mileage', $vehicle?->odometer_km) }}">
                        <small class="field-hint">Start reading of the next PDF report. Each report moves it to its end reading.</small>
                    </label>
                    <label>
                        <span>Country of residence</span>
                        <input name="country" type="text" autocomplete="country-name" maxlength="100" value="{{ old('country', $profile?->country) }}">
                    </label>
                </div>
                <button class="secondary-action" type="submit">Save profile</button>
            </form>
        </section>
    </section>
@endsection
