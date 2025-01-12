<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title')</title>
    @vite(['resources/css/app.css'])
</head>
<body class="flex items-center justify-center min-h-screen bg-gradient-to-br from-blue-50 to-blue-200 dark:from-gray-800 dark:to-gray-900 text-gray-800 dark:text-gray-100 antialiased">
    <div class="text-center max-w-3xl">
        <div class="text-4xl font-extrabold tracking-wide text-blue-600 dark:text-blue-400">
            @yield('title')
        </div>
        <div class="mt-6 text-lg text-gray-600 dark:text-gray-300">
            @yield('message')
        </div>
    </div>
</body>
</html>
