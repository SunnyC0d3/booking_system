<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="x-proxy-token" content="{{ encrypt(config('proxy_key')) }}">
        <title>Payment Page</title>
        @viteReactRefresh
        @vite(['resources/css/app.css'])
        @vite(['resources/js/demo/App.jsx'])
    </head>
    <body>
        <div id="app"></div>
    </body>
</html>
