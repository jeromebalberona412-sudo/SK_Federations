<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.favicon')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>No Internet Connection - SK Federations</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ url('/shared/css/loading.css') }}">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #213F99 0%, #d0242b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 24px;
            padding: 60px 40px;
            max-width: 450px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: fadeIn 0.5s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .icon {
            width: 160px;
            height: 160px;
            margin: 0 auto 32px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .icon svg {
            width: 140px;
            height: 140px;
            filter: drop-shadow(0 8px 24px rgba(239, 68, 68, 0.25));
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0px);
            }
            50% {
                transform: translateY(-15px);
            }
        }

        h1 {
            font-size: 36px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 12px;
        }

        p {
            font-size: 16px;
            color: #64748b;
            margin-bottom: 8px;
        }

        .subtitle {
            font-size: 15px;
            color: #94a3b8;
            margin-bottom: 36px;
        }

        .button {
            background: linear-gradient(135deg, #213F99 0%, #d0242b 100%);
            color: white;
            padding: 16px 40px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 16px rgba(33, 63, 153, 0.3);
        }

        .button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 24px rgba(33, 63, 153, 0.4);
        }

        @media (max-width: 640px) {
            .container { padding: 50px 30px; }
            .icon { width: 140px; height: 140px; }
            .icon svg { width: 120px; height: 120px; }
            h1 { font-size: 32px; }
        }

        @media (max-width: 480px) {
            .container { padding: 40px 25px; }
            .icon { width: 120px; height: 120px; }
            .icon svg { width: 100px; height: 100px; }
            h1 { font-size: 28px; }
            p { font-size: 14px; }
            .button { padding: 12px 32px; font-size: 14px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M2 8.5C5.5 5 10 3 12 3C14 3 18.5 5 22 8.5" stroke="#ef4444" stroke-width="2" stroke-linecap="round"/>
                <path d="M5 12C7.5 9.5 9.5 8.5 12 8.5C14.5 8.5 16.5 9.5 19 12" stroke="#ef4444" stroke-width="2" stroke-linecap="round"/>
                <path d="M8.5 15.5C10 14 11 13.5 12 13.5C13 13.5 14 14 15.5 15.5" stroke="#ef4444" stroke-width="2" stroke-linecap="round"/>
                <circle cx="12" cy="19" r="1.5" fill="#ef4444"/>
                <line x1="3" y1="21" x2="21" y2="3" stroke="#ef4444" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
        </div>

        <h1>Oops!</h1>
        <p>No internet connection found.</p>
        <p class="subtitle">Check your connection.</p>

        <button onclick="retryConnection()" class="button">Try Again</button>
    </div>

    <script src="{{ url('/shared/js/loading.js') }}"></script>
    <script>
        function retryConnection() {
            if (navigator.onLine) {
                NetworkNotification.showOnline();
                setTimeout(() => {
                    LoadingScreen.show('Reconnecting', 'Please wait...');
                    setTimeout(() => window.location.reload(), 500);
                }, 1000);
            } else {
                alert('Still offline. Please try again.');
            }
        }

        window.addEventListener('online', () => {
            NetworkNotification.showOnline();
            setTimeout(() => {
                LoadingScreen.show('Reconnecting', 'Please wait...');
                setTimeout(() => window.location.reload(), 500);
            }, 1000);
        });

        setInterval(() => {
            if (navigator.onLine) {
                fetch('/', { method: 'HEAD', cache: 'no-cache' })
                    .then(() => {
                        NetworkNotification.showOnline();
                        setTimeout(() => {
                            LoadingScreen.show('Reconnecting', 'Please wait...');
                            setTimeout(() => window.location.reload(), 500);
                        }, 1000);
                    })
                    .catch(() => {});
            }
        }, 3000);
    </script>
</body>
</html>
