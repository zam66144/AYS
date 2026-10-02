<?php
require_once 'config.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// If already logged in, redirect to home
if (isset($_SESSION['client_id'])) {
    header("Location: index.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validation
    $errors = [];

    if (empty($name)) $errors[] = 'Full name is required.';
    if (empty($email)) $errors[] = 'Email address is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format.';
    
    // Password Policy: Min 8 chars, at least 1 number, at least 1 special character
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least 1 number.';
    }
    if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
        $errors[] = 'Password must contain at least 1 special character (e.g., !@#$%^&*).';
    }

    if (empty($errors)) {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM clients WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'This email is already registered. Please login.';
        } else {
            // Hash password and insert
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO clients (name, email, password) VALUES (?, ?, ?)");
            
            if ($stmt->execute([$name, $email, $hashedPassword])) {
                $success = 'Account created successfully! You can now login.';
                // Auto login after registration (optional)
                $userId = $pdo->lastInsertId();
                $_SESSION['client_id'] = $userId;
                $_SESSION['client_name'] = $name;
                $_SESSION['client_email'] = $email;
                header("Refresh: 1; url=index.php");
            } else {
                $error = 'Something went wrong. Please try again.';
            }
        }
    } else {
        $error = implode('<br>', $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - LUXE SCENT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;600;700;800;900&family=Cormorant+Garamond:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root { 
            --accent-gold: #d4af37; 
            --accent-gold-hover: #f7d26b;
            --accent-gold-light: #fff8dc;
            --primary-dark: #0a192f; 
            --primary-light: #112240;
            --primary-darker: #050e1a;
            --text-muted: #8892b0;
            --text-light: #a0b8cc;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { height: 100%; }
        
        body { 
            font-family: 'Inter', sans-serif; 
            background: var(--primary-darker);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            overflow-x: hidden;
            position: relative;
        }
        
        .gold-text { color: var(--accent-gold) !important; }
        
        /* ========================== */
        /* ANIMATED BACKGROUND         */
        /* ========================== */
        .bg-layer {
            position: fixed;
            inset: 0;
            z-index: 0;
            overflow: hidden;
            pointer-events: none;
        }
        
        .bg-gradient {
            position: absolute;
            inset: 0;
            background: 
                radial-gradient(ellipse at 20% 20%, rgba(212, 175, 55, 0.15) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 80%, rgba(212, 175, 55, 0.1) 0%, transparent 50%),
                radial-gradient(ellipse at 50% 50%, #112240 0%, #0a192f 50%, #050e1a 100%);
        }
        
        .bg-pattern {
            position: absolute;
            inset: 0;
            background-image: 
                linear-gradient(rgba(212, 175, 55, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(212, 175, 55, 0.03) 1px, transparent 1px);
            background-size: 60px 60px;
            animation: patternMove 40s linear infinite;
        }
        
        @keyframes patternMove {
            0% { transform: translate(0, 0); }
            100% { transform: translate(60px, 60px); }
        }
        
        .glow-orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.5;
            animation: orbFloat 20s ease-in-out infinite;
        }
        
        .glow-orb.orb-1 {
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(212, 175, 55, 0.4) 0%, transparent 70%);
            top: -10%;
            left: -10%;
            animation-delay: 0s;
        }
        
        .glow-orb.orb-2 {
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(100, 130, 200, 0.3) 0%, transparent 70%);
            bottom: -15%;
            right: -10%;
            animation-delay: -7s;
        }
        
        .glow-orb.orb-3 {
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(212, 175, 55, 0.25) 0%, transparent 70%);
            top: 40%;
            right: 20%;
            animation-delay: -14s;
        }
        
        @keyframes orbFloat {
            0%, 100% { 
                transform: translate(0, 0) scale(1); 
                opacity: 0.5;
            }
            33% { 
                transform: translate(60px, -40px) scale(1.15); 
                opacity: 0.7;
            }
            66% { 
                transform: translate(-40px, 50px) scale(0.9); 
                opacity: 0.4;
            }
        }
        
        .particles-container {
            position: absolute;
            inset: 0;
            overflow: hidden;
        }
        
        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: var(--accent-gold);
            border-radius: 50%;
            box-shadow: 0 0 15px rgba(212, 175, 55, 0.9);
            opacity: 0;
            animation: particleRise 12s linear infinite;
        }
        
        @keyframes particleRise {
            0% {
                opacity: 0;
                transform: translateY(100vh) scale(0);
            }
            10% {
                opacity: 1;
                transform: translateY(90vh) scale(1);
            }
            90% {
                opacity: 0.8;
            }
            100% {
                opacity: 0;
                transform: translateY(-10vh) scale(0.5);
            }
        }
        
        .particle:nth-child(1) { left: 5%; animation-delay: 0s; }
        .particle:nth-child(2) { left: 15%; animation-delay: -1s; width: 3px; height: 3px; }
        .particle:nth-child(3) { left: 25%; animation-delay: -2s; width: 5px; height: 5px; }
        .particle:nth-child(4) { left: 35%; animation-delay: -3s; }
        .particle:nth-child(5) { left: 45%; animation-delay: -4s; width: 3px; height: 3px; }
        .particle:nth-child(6) { left: 55%; animation-delay: -5s; width: 6px; height: 6px; }
        .particle:nth-child(7) { left: 65%; animation-delay: -6s; }
        .particle:nth-child(8) { left: 75%; animation-delay: -7s; width: 4px; height: 4px; }
        .particle:nth-child(9) { left: 85%; animation-delay: -8s; width: 3px; height: 3px; }
        .particle:nth-child(10) { left: 95%; animation-delay: -9s; width: 5px; height: 5px; }
        .particle:nth-child(11) { left: 10%; animation-delay: -10s; }
        .particle:nth-child(12) { left: 30%; animation-delay: -11s; width: 4px; height: 4px; }
        .particle:nth-child(13) { left: 50%; animation-delay: -6.5s; width: 3px; height: 3px; }
        .particle:nth-child(14) { left: 70%; animation-delay: -2.5s; width: 6px; height: 6px; }
        .particle:nth-child(15) { left: 90%; animation-delay: -4.5s; width: 4px; height: 4px; }
        .particle:nth-child(16) { left: 20%; animation-delay: -8.5s; width: 5px; height: 5px; }
        .particle:nth-child(17) { left: 40%; animation-delay: -3.5s; width: 3px; height: 3px; }
        .particle:nth-child(18) { left: 60%; animation-delay: -7.5s; }
        .particle:nth-child(19) { left: 80%; animation-delay: -1.5s; width: 4px; height: 4px; }
        .particle:nth-child(20) { left: 100%; animation-delay: -5.5s; width: 5px; height: 5px; }
        
        /* ========================== */
        /* HEADER                      */
        /* ========================== */
        .header-luxe {
            position: relative;
            z-index: 10;
            padding: 20px 0;
            background: rgba(10, 25, 47, 0.4);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(212, 175, 55, 0.2);
            animation: headerSlideDown 1s cubic-bezier(0.22, 1, 0.36, 1);
        }
        
        @keyframes headerSlideDown {
            from { transform: translateY(-100%); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .brand-logo {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.8rem;
            font-weight: 700;
            letter-spacing: 2px;
            color: #fff;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .brand-logo:hover { 
            color: var(--accent-gold);
            letter-spacing: 3px;
        }
        
        .btn-back {
            background: transparent;
            border: 1px solid rgba(212, 175, 55, 0.4);
            color: rgba(255, 255, 255, 0.8);
            padding: 8px 20px;
            border-radius: 50px;
            font-size: 0.85rem;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-back:hover {
            background: var(--accent-gold);
            color: var(--primary-dark);
            border-color: var(--accent-gold);
            transform: translateX(-4px);
            box-shadow: 0 8px 25px rgba(212, 175, 55, 0.3);
        }
        
        /* ========================== */
        /* MAIN CONTENT                */
        /* ========================== */
        .main-wrapper {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            z-index: 5;
            padding: 40px 20px;
        }
        
        /* ========================== */
        /* AUTH CARD - GLASS MORPHISM  */
        /* ========================== */
        .auth-card {
            background: rgba(10, 25, 47, 0.6);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            border-radius: 24px;
            padding: 50px 45px;
            max-width: 480px;
            width: 100%;
            border: 1px solid rgba(212, 175, 55, 0.25);
            box-shadow: 
                0 25px 60px rgba(0, 0, 0, 0.5),
                0 0 0 1px rgba(212, 175, 55, 0.1),
                inset 0 1px 0 rgba(255, 255, 255, 0.05);
            position: relative;
            overflow: hidden;
            animation: cardEntrance 1.2s cubic-bezier(0.22, 1, 0.36, 1) 0.3s both;
            transition: all 0.5s ease;
        }
        
        .auth-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--accent-gold), transparent);
            animation: cardShimmer 4s ease-in-out infinite;
        }
        
        @keyframes cardShimmer {
            0% { left: -100%; }
            50%, 100% { left: 100%; }
        }
        
        @keyframes cardEntrance {
            0% {
                opacity: 0;
                transform: translateY(60px) scale(0.9);
                filter: blur(10px);
            }
            100% {
                opacity: 1;
                transform: translateY(0) scale(1);
                filter: blur(0);
            }
        }
        
        .auth-card:hover {
            border-color: rgba(212, 175, 55, 0.5);
            box-shadow: 
                0 30px 70px rgba(0, 0, 0, 0.6),
                0 0 0 1px rgba(212, 175, 55, 0.2),
                0 0 60px rgba(212, 175, 55, 0.15),
                inset 0 1px 0 rgba(255, 255, 255, 0.08);
            transform: translateY(-5px);
        }
        
        /* ========================== */
        /* FORM HEADER WITH CROWN      */
        /* ========================== */
        .crown-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--accent-gold), #b8941f);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            font-size: 2rem;
            color: var(--primary-dark);
            box-shadow: 
                0 10px 30px rgba(212, 175, 55, 0.4),
                0 0 0 8px rgba(212, 175, 55, 0.1);
            animation: crownPulse 3s ease-in-out infinite;
            position: relative;
        }
        
        .crown-icon::before {
            content: '';
            position: absolute;
            inset: -12px;
            border-radius: 50%;
            border: 2px solid rgba(212, 175, 55, 0.3);
            animation: crownRing 3s ease-out infinite;
        }
        
        .crown-icon::after {
            content: '';
            position: absolute;
            inset: -12px;
            border-radius: 50%;
            border: 2px solid rgba(212, 175, 55, 0.3);
            animation: crownRing 3s ease-out infinite;
            animation-delay: 1.5s;
        }
        
        @keyframes crownPulse {
            0%, 100% { 
                box-shadow: 
                    0 10px 30px rgba(212, 175, 55, 0.4),
                    0 0 0 8px rgba(212, 175, 55, 0.1);
            }
            50% { 
                box-shadow: 
                    0 15px 40px rgba(212, 175, 55, 0.6),
                    0 0 0 12px rgba(212, 175, 55, 0.05);
            }
        }
        
        @keyframes crownRing {
            0% {
                transform: scale(1);
                opacity: 0.8;
            }
            100% {
                transform: scale(1.5);
                opacity: 0;
            }
        }
        
        .auth-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2.4rem;
            font-weight: 700;
            text-align: center;
            color: #fff;
            letter-spacing: 3px;
            margin-bottom: 8px;
            background: linear-gradient(135deg, #fff 0%, var(--accent-gold) 50%, #fff 100%);
            background-size: 200% auto;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: titleShine 4s ease-in-out infinite;
        }
        
        @keyframes titleShine {
            0%, 100% { background-position: 0% center; }
            50% { background-position: 100% center; }
        }
        
        .auth-subtitle {
            text-align: center;
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.8rem;
            letter-spacing: 4px;
            text-transform: uppercase;
            margin-bottom: 35px;
            font-weight: 300;
        }
        
        .auth-subtitle::before,
        .auth-subtitle::after {
            content: '◆';
            color: var(--accent-gold);
            margin: 0 10px;
            font-size: 0.6rem;
        }
        
        /* ========================== */
        /* FORM FIELDS - FIXED         */
        /* ========================== */
        .input-group-luxe {
            position: relative;
            margin-bottom: 22px;
            padding-top: 26px; /* Space for label above input */
            animation: inputSlideIn 0.8s cubic-bezier(0.22, 1, 0.36, 1) both;
        }
        
        /* 3 boxes animation delays for register page */
        .input-group-luxe:nth-child(1) { animation-delay: 0.6s; }
        .input-group-luxe:nth-child(2) { animation-delay: 0.75s; }
        .input-group-luxe:nth-child(3) { animation-delay: 0.9s; }
        
        @keyframes inputSlideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .input-icon {
            position: absolute;
            left: 20px;
            top: 26px; /* Matches padding-top of parent */
            bottom: 0;
            height: calc(100% - 26px); /* Icon aligns with input box height only */
            display: flex;
            align-items: center;
            color: rgba(212, 175, 55, 0.6);
            font-size: 1.1rem;
            transition: all 0.3s ease;
            z-index: 2;
            pointer-events: none;
        }
        
        .form-control-luxe {
            width: 100%;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(212, 175, 55, 0.2);
            border-radius: 14px;
            padding: 16px 20px 16px 55px;
            color: #fff;
            font-size: 0.95rem;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
            outline: none;
        }
        
        .form-control-luxe::placeholder {
            color: rgba(255, 255, 255, 0.3);
        }
        
        .form-control-luxe:focus {
            background: rgba(255, 255, 255, 0.08);
            border-color: var(--accent-gold);
            box-shadow: 
                0 0 0 4px rgba(212, 175, 55, 0.1),
                0 8px 25px rgba(212, 175, 55, 0.15);
        }
        
        .form-control-luxe:focus ~ .input-icon {
            color: var(--accent-gold);
        }
        
        .form-label-luxe {
            position: absolute;
            top: 0;
            left: 0;
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.75rem;
            font-weight: 500;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 0;
            display: block;
            line-height: 1;
        }
        
        /* ========================== */
        /* SUBMIT BUTTON               */
        /* ========================== */
        .btn-gold-luxe {
            width: 100%;
            background: linear-gradient(135deg, var(--accent-gold) 0%, #b8941f 100%);
            color: var(--primary-dark);
            font-weight: 700;
            font-size: 0.95rem;
            letter-spacing: 2px;
            text-transform: uppercase;
            padding: 16px 28px;
            border-radius: 14px;
            border: none;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            transition: all 0.4s ease;
            margin-top: 10px;
            box-shadow: 
                0 10px 30px rgba(212, 175, 55, 0.3),
                inset 0 1px 0 rgba(255, 255, 255, 0.3);
            animation: btnEntrance 0.8s cubic-bezier(0.22, 1, 0.36, 1) 1.05s both;
        }
        
        @keyframes btnEntrance {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .btn-gold-luxe::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            transition: left 0.6s ease;
        }
        
        .btn-gold-luxe:hover {
            transform: translateY(-3px);
            box-shadow: 
                0 15px 40px rgba(212, 175, 55, 0.5),
                inset 0 1px 0 rgba(255, 255, 255, 0.4);
            background: linear-gradient(135deg, var(--accent-gold-hover) 0%, var(--accent-gold) 100%);
        }
        
        .btn-gold-luxe:hover::before {
            left: 100%;
        }
        
        .btn-gold-luxe:active {
            transform: translateY(-1px);
        }
        
        /* ========================== */
        /* DIVIDER                     */
        /* ========================== */
        .divider-luxe {
            display: flex;
            align-items: center;
            gap: 15px;
            margin: 30px 0 20px;
            animation: fadeIn 1s ease 1.15s both;
        }
        
        .divider-luxe::before,
        .divider-luxe::after {
            content: '';
            flex: 1;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(212, 175, 55, 0.3), transparent);
        }
        
        .divider-luxe span {
            color: rgba(255, 255, 255, 0.4);
            font-size: 0.7rem;
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        /* ========================== */
        /* FOOTER LINKS                */
        /* ========================== */
        .auth-footer-link {
            text-align: center;
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.85rem;
            animation: fadeIn 1s ease 1.25s both;
        }
        
        .auth-footer-link a {
            color: var(--accent-gold);
            text-decoration: none;
            font-weight: 600;
            position: relative;
            transition: all 0.3s ease;
        }
        
        .auth-footer-link a::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 1px;
            background: var(--accent-gold);
            transition: width 0.3s ease;
        }
        
        .auth-footer-link a:hover::after {
            width: 100%;
        }
        
        /* ========================== */
        /* ALERTS                      */
        /* ========================== */
        .alert-luxe {
            padding: 14px 20px;
            border-radius: 12px;
            font-size: 0.85rem;
            margin-bottom: 22px;
            animation: alertShake 0.5s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        @keyframes alertShake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-6px); }
            75% { transform: translateX(6px); }
        }
        
        .alert-luxe.danger {
            background: rgba(220, 53, 69, 0.15);
            border: 1px solid rgba(220, 53, 69, 0.4);
            color: #ff6b7d;
        }
        
        .alert-luxe.success {
            background: rgba(34, 197, 94, 0.15);
            border: 1px solid rgba(34, 197, 94, 0.4);
            color: #4ade80;
        }
        
        /* ========================== */
        /* PASSWORD REQUIREMENTS       */
        /* ========================== */
        .password-requirements {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.4);
            margin-top: 10px;
            padding-left: 0;
            list-style: none;
        }
        
        .password-requirements li { 
            margin-bottom: 4px; 
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .password-requirements .valid { color: #4ade80; }
        .password-requirements .invalid { color: #ff6b7d; }
        
        /* ========================== */
        /* PREMIUM FOOTER              */
        /* ========================== */
        .footer {
            background: rgba(5, 14, 26, 0.95);
            backdrop-filter: blur(20px);
            color: var(--text-muted);
            border-top: 1px solid rgba(212, 175, 55, 0.2);
            padding: 50px 0 25px;
            position: relative;
            z-index: 10;
            margin-top: auto;
        }
        
        .footer h6 {
            color: #fff;
            font-weight: 700;
            margin-bottom: 20px;
            font-size: 1rem;
            letter-spacing: 2px;
            text-transform: uppercase;
            position: relative;
            padding-bottom: 12px;
        }
        
        .footer h6::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 30px;
            height: 2px;
            background: var(--accent-gold);
        }
        
        .footer ul { padding-left: 0; }
        .footer ul li { list-style: none; margin-bottom: 10px; }
        .footer ul li a {
            color: var(--text-muted);
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.85rem;
        }
        .footer ul li a:hover {
            color: var(--accent-gold);
            transform: translateX(4px);
        }
        .footer .brand-desc {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 15px;
            line-height: 1.7;
        }
        .footer .social-links {
            display: flex;
            gap: 12px;
            margin-top: 10px;
        }
        .footer .social-links a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(212, 175, 55, 0.2);
            border-radius: 50%;
            color: var(--text-light);
            font-size: 1.1rem;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        .footer .social-links a:hover {
            background: var(--accent-gold);
            color: var(--primary-dark);
            transform: translateY(-4px);
            box-shadow: 0 10px 25px rgba(212, 175, 55, 0.3);
        }
        .footer .footer-divider { 
            border-top: 1px solid rgba(212, 175, 55, 0.1); 
            margin: 35px 0 20px; 
        }
        .footer .copyright {
            text-align: center;
            font-size: 1rem;
            color: var(--text-muted);
            letter-spacing: 1px;
        }
        .footer .copyright i { color: var(--accent-gold); margin-right: 6px; }
        
        /* ========================== */
        /* RESPONSIVE                  */
        /* ========================== */
        @media (max-width: 768px) {
            .auth-card {
                padding: 40px 30px;
                margin: 20px;
            }
            
            .auth-title { font-size: 1.9rem; }
            
            .crown-icon {
                width: 60px;
                height: 60px;
                font-size: 1.6rem;
            }
            
            .brand-logo { font-size: 1.4rem; }
            
            .footer { 
                padding: 35px 0 20px; 
                text-align: center; 
            }
            .footer h6::after {
                left: 50%;
                transform: translateX(-50%);
            }
            .footer .social-links { justify-content: center; }
            
            .glow-orb { filter: blur(60px); }
            .glow-orb.orb-1 { width: 300px; height: 300px; }
            .glow-orb.orb-2 { width: 350px; height: 350px; }
            .glow-orb.orb-3 { width: 250px; height: 250px; }
        }
        
        @media (max-width: 480px) {
            .auth-card {
                padding: 30px 20px;
                border-radius: 20px;
            }
            
            .form-control-luxe {
                padding: 14px 16px 14px 48px;
                font-size: 0.9rem;
            }
            
            .input-icon { left: 16px; }
            
            .btn-gold-luxe {
                padding: 14px 20px;
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body>

<!-- ========================== -->
<!-- ANIMATED BACKGROUND         -->
<!-- ========================== -->
<div class="bg-layer">
    <div class="bg-gradient"></div>
    <div class="bg-pattern"></div>
    
    <div class="glow-orb orb-1"></div>
    <div class="glow-orb orb-2"></div>
    <div class="glow-orb orb-3"></div>
    
    <div class="particles-container">
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
    </div>
</div>

<!-- ========================== -->
<!-- HEADER                      -->
<!-- ========================== -->
<header class="header-luxe">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center">
            <a href="index.php" class="brand-logo">
                Mr.<span class="gold-text">AYS</span>
            </a>
            <a href="index.php" class="btn-back">
                <i class="bi bi-arrow-left"></i> Back to Store
            </a>
        </div>
    </div>
</header>

<!-- ========================== -->
<!-- MAIN CONTENT                -->
<!-- ========================== -->
<div class="main-wrapper">
    <div class="container d-flex justify-content-center">
        <div class="auth-card">
            
            <!-- Crown Icon -->
            <div class="crown-icon">
                <i class="bi bi-person-plus"></i>
            </div>
            
            <!-- Title -->
            <h2 class="auth-title">Create Account</h2>
            <p class="auth-subtitle">Join The Luxury</p>
            
            <!-- Alerts -->
            <?php if($error): ?>
                <div class="alert-luxe danger">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="alert-luxe success">
                    <i class="bi bi-check-circle-fill"></i>
                    <span><?php echo $success; ?></span>
                </div>
            <?php endif; ?>

            <!-- Form -->
            <form method="POST" action="">
                <!-- Box 1: Full Name -->
                <div class="input-group-luxe">
                    <label class="form-label-luxe">Full Name</label>
                    <i class="bi bi-person input-icon"></i>
                    <input type="text" name="name" class="form-control-luxe" placeholder="Enter your full name" required>
                </div>
                
                <!-- Box 2: Email -->
                <div class="input-group-luxe">
                    <label class="form-label-luxe">Email Address</label>
                    <i class="bi bi-envelope input-icon"></i>
                    <input type="email" name="email" class="form-control-luxe" placeholder="your@email.com" required>
                </div>
                
                <!-- Box 3: Password -->
                <div class="input-group-luxe">
                    <label class="form-label-luxe">Password</label>
                    <i class="bi bi-shield-lock input-icon"></i>
                    <input type="password" id="passwordInput" name="password" class="form-control-luxe" placeholder="Create a strong password" required>
                    <ul class="password-requirements">
                        <li id="reqLength">❌ At least 8 characters</li>
                        <li id="reqNumber">❌ At least 1 number</li>
                        <li id="reqSpecial">❌ At least 1 special character (!@#$%^&*)</li>
                    </ul>
                </div>
                
                <button type="submit" class="btn-gold-luxe">
                    <i class="bi bi-person-plus me-2"></i>Create Account
                </button>
            </form>
            
            <!-- Divider -->
            <div class="divider-luxe">
                <span>Already a Member?</span>
            </div>
            
            <!-- Login Link -->
            <div class="auth-footer-link">
                Already have an account? <a href="login.php">Sign In</a>
            </div>
        </div>
    </div>
</div>

<!-- ========================== -->
<!-- PREMIUM FOOTER              -->
<!-- ========================== -->
<footer class="footer">
    <div class="container">
        <div class="row g-4">
            <!-- Brand -->
            <div class="col-lg-4 col-md-6">
                <h6 class="gold-text">Mr.AYS</h6>
                <p class="brand-desc">
                    Premium luxury products since 2026. Discover our exclusive range of perfumes, testers, watches, and premium eyewear.
                </p>
                <div class="social-links">
                    <a href="https://www.facebook.com/profile.php?id=61593054195065&rdid=B8vJjskA9o9Ho0jJ&share_url=https%3A%2F%2Fwww.facebook.com%2Fshare%2F1LjNSGnjJx%2F#"><i class="bi bi-facebook"></i></a>
                    <a href="https://www.instagram.com/mr.ays_officiall?stkn=MWUzbGM1dHdxNXFkcQ=="><i class="bi bi-instagram"></i></a>
                    <a href="#"><i class="bi bi-twitter-x"></i></a>
                    <a href="https://wa.me/923263368118?text=Hello%20AYS%2C%20I%20want%20to%20know%20more%20about%20your%20perfumes." target="_blank" rel="noopener noreferrer">
                    <i class="bi bi-whatsapp"></i>
                    </a>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="col-lg-4 col-md-6">
                <h6>Quick Links</h6>
                <ul>
                    <li><a href="index.php"><i class="bi bi-chevron-right"></i> Shop Now</a></li>
                    <li><a href="orders.php"><i class="bi bi-chevron-right"></i> My Orders</a></li>
                    <li><a href="wishlist.php"><i class="bi bi-chevron-right"></i> My Wishlist</a></li>
                    <li><a href="account.php"><i class="bi bi-chevron-right"></i> My Account</a></li>
                </ul>
            </div>

            <!-- Policies -->
            <div class="col-lg-4 col-md-6">
                <h6>Policies</h6>
                <ul>
                    <li><a href="#"><i class="bi bi-chevron-right"></i> Return Policy</a></li>
                    <li><a href="#"><i class="bi bi-chevron-right"></i> Privacy Policy</a></li>
                    <li><a href="#"><i class="bi bi-chevron-right"></i> Terms &amp; Conditions</a></li>
                    <li><a href="#"><i class="bi bi-chevron-right"></i> Shipping Info</a></li>
                </ul>
            </div>
        </div>

        <!-- Divider -->
        <div class="footer-divider"></div>

        <!-- Copyright -->
        <div class="copyright">
            &copy; 2026 AYS. All Rights Reserved. powered by <a href="https://zam2.vercel.app" target="_blank" rel="noopener noreferrer" style="color: #ffcc00; text-decoration: none; font-weight: bold;">ZAM Digital Agency</a>
        </div>
    </div>
</footer>

<script>
    // Real-time password validation
    document.getElementById('passwordInput').addEventListener('input', function() {
        const password = this.value;
        const lengthReq = document.getElementById('reqLength');
        const numberReq = document.getElementById('reqNumber');
        const specialReq = document.getElementById('reqSpecial');

        // Check length
        if (password.length >= 8) {
            lengthReq.innerHTML = '✅ At least 8 characters';
            lengthReq.className = 'valid';
        } else {
            lengthReq.innerHTML = '❌ At least 8 characters';
            lengthReq.className = 'invalid';
        }

        // Check number
        if (/\d/.test(password)) {
            numberReq.innerHTML = '✅ At least 1 number';
            numberReq.className = 'valid';
        } else {
            numberReq.innerHTML = '❌ At least 1 number';
            numberReq.className = 'invalid';
        }

        // Check special character
        if (/[^a-zA-Z0-9]/.test(password)) {
            specialReq.innerHTML = '✅ At least 1 special character (!@#$%^&*)';
            specialReq.className = 'valid';
        } else {
            specialReq.innerHTML = '❌ At least 1 special character (!@#$%^&*)';
            specialReq.className = 'invalid';
        }
    });
</script>

</body>
</html>