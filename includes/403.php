<!DOCTYPE html>
<html>
<head>
    <title>Access Denied</title>
    <link rel="stylesheet" href="/assets/style.css">
    <style>
        body {
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
        }
        .error-container {
            background: white;
            padding: 50px;
            border-radius: 12px;
            box-shadow: 0 5px 30px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 500px;
        }
        .error-code {
            font-size: 5em;
            font-weight: bold;
            color: #e74c3c;
            margin: 0;
        }
        .error-message {
            font-size: 1.4em;
            color: #2c3e50;
            margin: 10px 0 20px;
        }
        .error-detail {
            color: #7f8c8d;
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <p class="error-code">403</p>
        <p class="error-message">Access Denied</p>
        <p class="error-detail">
            You don't have permission to access this page.
            Please contact your administrator if you believe this is an error.
        </p>
        <a href="/" class="btn btn-primary">Go to Dashboard</a>
        <a href="/logout.php" class="btn btn-secondary">Logout</a>
    </div>
</body>
</html>
