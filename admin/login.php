<?php
session_start();
if (isset($_SESSION['admin_logged']) && $_SESSION['admin_logged'] === true) {
    header('Location: index.php');
    exit;
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    if ($username === 'admin' && $password === 'luxe123') {
        $_SESSION['admin_logged'] = true;
        header('Location: index.php');
        exit;
    } else {
        $error = 'Invalid credentials';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - LUXE SCENT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/admin/css/admin.css">
    <style>
        body { 
            background: var(--bg-primary); 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: 100vh; 
            margin: 0;
            transition: background 0.3s ease;
        }
        .login-container { 
            background: var(--bg-card); 
            border-radius: 24px; 
            padding: 40px; 
            max-width: 400px; 
            width: 90%; 
            box-shadow: var(--shadow); 
            border: 2px solid var(--gold);
            transition: background 0.3s ease;
        }
        .login-container input { 
            border-radius: 40px; 
            padding: 14px 20px; 
            border: 2px solid var(--border-color);
            background: var(--bg-secondary);
            color: var(--text-primary);
        }
        .login-container input:focus { 
            border-color: var(--gold); 
            box-shadow: 0 0 0 4px rgba(212,175,55,0.1);
        }
        .login-container input::placeholder { color: var(--text-muted); }
        .btn-gold { width: 100%; padding: 14px; }
        .alert-danger { background: var(--danger); border: none; border-radius: 40px; color: white; padding: 10px 20px; }
        .theme-toggle-top {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--bg-card);
            border: 2px solid var(--gold);
            color: var(--gold);
            border-radius: 50px;
            padding: 8px 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 1000;
        }
        .theme-toggle-top:hover {
            background: var(--gold);
            color: #0a1a2e;
        }
    </style>
</head>
<body>
    <button class="theme-toggle-top" onclick="toggleTheme()">
        <i class="bi bi-moon-fill"></i>
    </button>
    
    <div class="login-container">
        <div class="text-center mb-4">
            <i class="bi bi-shield-lock" style="font-size:3rem; color:var(--gold);"></i>
            <h2 class="mt-2" style="color:var(--text-primary);">Admin <span style="color:var(--gold);">Access</span></h2>
            <p style="color:var(--text-secondary); font-size:0.9rem;">Secure panel for order management</p>
        </div>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
        <form method="POST" autocomplete="off">
            <input type="text" name="username" class="form-control mb-3" placeholder="Enter username" required autocomplete="off">
            <input type="password" name="password" class="form-control mb-3" placeholder="Enter password" required autocomplete="new-password">
            <button type="submit" class="btn-gold"><i class="bi bi-box-arrow-in-right me-2"></i>Login</button>
        </form>
        <p class="text-center mt-3"><a href="../index.php" style="color:var(--text-muted); text-decoration:none;"><i class="bi bi-arrow-left me-1"></i>Back to Website</a></p>
    </div>

    <script>
        function toggleTheme() {
            const html = document.documentElement;
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('admin_theme', newTheme);
            
            const icon = document.querySelector('.theme-toggle-top i');
            if (icon) {
                icon.className = newTheme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
            }
        }

        function loadTheme() {
            const savedTheme = localStorage.getItem('admin_theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
            const icon = document.querySelector('.theme-toggle-top i');
            if (icon) {
                icon.className = savedTheme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
            }
        }
        loadTheme();
    </script>
</body>
</html>