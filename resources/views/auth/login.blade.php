@extends('layout')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-gray-100 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <div>
            <h2 class="text-center text-3xl font-extrabold text-gray-900">
                Login to Your Account
            </h2>
            <p class="mt-2 text-center text-sm text-gray-600">
                Or 
                <a href="{{ route('register') }}" class="font-medium text-indigo-600 hover:text-indigo-500">
                    create a new account
                </a>
            </p>
        </div>
        {{-- Login Form --}}
        <form id="loginForm" method="POST" action="{{ route('auth.login') }}" class="mt-8 space-y-6">
            @csrf
            {{-- Global Error --}}
            @if($errors->has('global'))
                <div id="error-global" class="bg-red-100 text-red-600 p-3 rounded-md">
                    {{ $errors->first('global') }}
                </div>
            @endif

            {{-- Email Field --}}
            <div class="rounded-md shadow-sm -space-y-px">
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}"
                        placeholder="Enter your email"
                        class="appearance-none rounded-md relative block w-full px-3 py-2 border border-gray-300 
                        placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    @error('email')
                        <div id="error-email" class="text-sm text-red-600">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            {{-- Password Field --}}
            <div class="rounded-md shadow-sm">
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password"
                        class="appearance-none rounded-md relative block w-full px-3 py-2 border border-gray-300 
                        placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    @error('password')
                        <div id="error-password" class="text-sm text-red-600">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            {{-- Submit Button --}}
            <div>
                <button type="submit" 
                    class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium 
                    rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Login
                </button>
            </div>
        </form>

        {{-- OAuth Login Options --}}
        <div class="mt-6 space-y-4">
            <p class="text-center text-sm text-gray-500">Or sign in with</p>
            <div class="flex items-center justify-center space-x-3">
                <a href="#" class="flex items-center justify-center w-1/3 px-4 py-2 border border-gray-300 rounded-lg shadow-sm text-gray-700 bg-white hover:bg-gray-50">
                    <img src="/images/google-icon.svg" alt="Google" class="h-5 w-5 mr-2">
                    Google
                </a>
                <a href="#" class="flex items-center justify-center w-1/3 px-4 py-2 border border-gray-300 rounded-lg shadow-sm text-gray-700 bg-white hover:bg-gray-50">
                    <img src="/images/github-icon.svg" alt="GitHub" class="h-5 w-5 mr-2">
                    GitHub
                </a>
                <a href="#" class="flex items-center justify-center w-1/3 px-4 py-2 border border-gray-300 rounded-lg shadow-sm text-gray-700 bg-white hover:bg-gray-50">
                    <img src="/images/facebook-icon.svg" alt="Facebook" class="h-5 w-5 mr-2">
                    Facebook
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
