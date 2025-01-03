@extends('layout')

@section('content')
<form id="loginForm">
    @csrf
    <div clas="form-input">
        <div id="error-global" class="error"></div>
    </div>
    <div class="form-input">
        <label for="email">Email</label>
        <br>
        <input type="email" id="email" name="email" placeholder="Enter email" value="{{old('email')}}">
        <div id="error-email" class="error"></div>
    </div>

    <div class="form-input">
        <label for="password">Password</label>
        <br>
        <input type="password" id="password" name="password" placeholder="Enter password">
        <div id="error-password" class="error"></div>
    </div>
    <br>
    <button type="submit">Login</button>
    <br>
    Don't have an account? <a href="{{route('register')}}">Register here</a>
</form>

@vite('resources/js/app.js')

@endsection