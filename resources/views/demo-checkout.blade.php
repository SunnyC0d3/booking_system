<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Payment Page</title>
        @viteReactRefresh
        @vite(['resources/css/app.css'])
        @vite(['resources/js/App.jsx'])
    </head>
    <body>
        <div id="app"
             data-order-id="{{ $orderId }}"
             data-order-items="{{ $orderItems }}
        "></div>
    </body>
</html>
