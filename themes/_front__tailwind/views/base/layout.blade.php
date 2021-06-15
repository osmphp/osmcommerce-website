<!doctype html>
<html lang="en">
<head>
    <title>{{ $title }}</title>
    <link href="{{ $asset('styles.css') }}" rel="stylesheet">
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
</head>
    <header class="container mx-auto">
    </header>
    <div class="container mx-auto px-4 grid grid-cols-12">
        {{ $slot }}
    </div>
    <footer class="container mx-auto">

    </footer>
<script src="{{ $asset('scripts.js') }}"></script>
</body>
</html>