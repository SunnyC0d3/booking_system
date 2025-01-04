@extends('layout')

@section('content')
<form id="loginForm" method="POST" action="{{ route('auth.login') }}">
    @csrf
    <div clas="form-input">
        <div id="error-global" class="error"></div>
    </div>
    <div class="form-input">
        <label for="email">Email</label>
        <br>
        <input type="email" id="email" name="email" placeholder="Enter email" value="{{old('email')}}">
        @error('email')
        <div id="error-email" class="error">{{ $message }}</div>
        @enderror
    </div>

    <div class="form-input">
        <label for="password">Password</label>
        <br>
        <input type="password" id="password" name="password" placeholder="Enter password">
        @error('password')
        <div id="error-password" class="error">{{ $message }}</div>
        @enderror
    </div>
    <br>
    <button type="submit">Login</button>
    <br>
    Don't have an account? <a href="{{route('register')}}">Register here</a>
</form>

@vite('resources/js/app.js')

@endsection