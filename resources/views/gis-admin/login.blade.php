@extends('layouts.gis-admin')

@section('content')
<div class="gis-login-screen bg-body-tertiary">
    <div class="card gis-login-card">
        <div class="card-body p-4 p-lg-5">
            <div class="mb-4 text-center">
                <h1 class="h3 mb-1">GIS Admin</h1>
                <div class="text-secondary">Вхід до адміністративної консолі</div>
            </div>

            @if ($errors->any())
                <div class="alert alert-danger">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('gis-admin.login.submit') }}" class="d-grid gap-3">
                @csrf

                <div>
                    <label class="form-label">Email</label>
                    <input
                        type="email"
                        name="email"
                        class="form-control"
                        value="{{ old('email') }}"
                        required
                        autofocus
                    >
                </div>

                <div>
                    <label class="form-label">Password</label>
                    <input
                        type="password"
                        name="password"
                        class="form-control"
                        required
                    >
                </div>

                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="remember" value="1" id="remember">
                    <label class="form-check-label" for="remember">
                        Запам’ятати мене
                    </label>
                </div>

                <button type="submit" class="btn btn-primary">
                    Увійти
                </button>
            </form>
        </div>
    </div>
</div>
@endsection