{{-- resources/views/code-executor/layouts/app.blade.php --}}
<!DOCTYPE html>
<html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title', 'Code Executor')</title>

        <script src="https://cdn.tailwindcss.com"></script>
        <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

        <style>
            [x-cloak] {
                display: none !important;
            }
        </style>

        @stack('styles')
    </head>

    <body class="bg-gray-900 text-white">
        @yield('content')
        @stack('scripts')
    </body>

</html>
