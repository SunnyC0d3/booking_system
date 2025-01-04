<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }}</title>
    <!-- Tailwind CSS -->
    @vite('resources/css/app.css')
</head>

<body class="bg-gray-100 text-gray-900">
    <!-- Navbar -->
    <header class="bg-indigo-600 text-white">
        <div class="container mx-auto px-4 py-4 flex justify-between items-center">
            <h1 class="text-xl font-bold"><a href="#" class="hover:text-gray-200">OAuth 2.0 App</a></h1>
            <nav>
                <ul class="flex space-x-4">
                    <li><a href="{{ route('login') }}" class="hover:text-gray-200">Login</a></li>
                    <li><a href="{{ route('register') }}" class="hover:text-gray-200">Register</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <main class="min-h-screen flex items-center justify-center">
        <div class="container mx-auto px-4">
            @yield('content')
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-800 text-gray-400">
        <div class="container mx-auto px-4 py-4 text-center">
            <p>&copy; {{ date('Y') }} OAuth 2.0 App. All rights reserved.</p>
            <p class="text-sm">Powered by Laravel & Tailwind CSS</p>
        </div>
    </footer>
</body>

</html>