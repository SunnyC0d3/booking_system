@extends('layout')

@section('content')

<form id="registerForm">
    @csrf
    <div clas="form-input">
        <div id="error-global" class="error"></div>
    </div>
    <div class="form-input">
        <label for="email">Email</label><br>
        <input type="email" id="email" name="email" placeholder="Enter email" value="{{old('email')}}">
        <div id="error-email" class="error"></div>
    </div>

    <div class="form-input">
        <label for="name">Username</label><br>
        <input type="text" id="name" name="name" placeholder="Enter Username" value="{{old('name')}}">
        <div id="error-name" class="error"></div>
    </div>

    <div class="form-input">
        <label for="password">Password</label><br>
        <input type="password" id="password" name="password" placeholder="Enter password" value="{{old('password')}}">
        <div id="error-password" class="error"></div>
    </div>

    <div class="form-input">
        <label for="password_confirmation">Confirm Password</label><br>
        <input type="password" id="password_confirmation" name="password_confirmation" placeholder="Confirm password">
    </div>

    <br>
    <button type="submit">Register</button>
    <br>
    Already have an account? <a href="{{route('login')}}">Login here</a>
</form>

@vite('resources/js/app.js')

@endsection