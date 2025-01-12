<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title')</title>
    @vite(['resources/css/app.css'])
</head>
<body class="flex items-center justify-center min-h-screen bg-gradient-to-r from-blue-100 to-blue-300 dark:from-gray-800 dark:to-gray-900 text-gray-800 dark:text-gray-100 antialiased">
    <div class="text-center max-w-2xl bg-white dark:bg-gray-800 shadow-lg rounded-lg p-8">
        <div class="flex justify-center items-center space-x-4">
            <div class="text-5xl font-bold text-blue-600 dark:text-blue-400">
                @yield('code')
            </div>
            <div class="h-12 border-r-2 border-gray-300 dark:border-gray-600"></div>
            <div class="text-lg uppercase tracking-wide">
                @yield('message')
            </div>
        </div>
    </div>
</body>
</html>
