<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Turnover Status - SK One Portal</title>
    <link rel="stylesheet" href="{{ url('/modules/turnover/css/turnover.css') }}?v={{ $cssVersion }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="turnover-waiting-body">
    <main class="turnover-waiting-page">
        <div class="turnover-waiting-card">
            <div class="turnover-waiting-icon"><i class="fas fa-clock"></i></div>
            <h1>Turnover Status</h1>
            <p>Your account has been created successfully.</p>
            <p>Please wait until the previous Federation President or Vice President completes the turnover process.</p>
            <p class="turnover-waiting-note">No other system access is available until turnover is completed.</p>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn-secondary-modern">Logout</button>
            </form>
        </div>
    </main>
</body>
</html>
