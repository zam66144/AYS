<?php
session_start();
require_once 'config.php';

$loggedInName = isset($_SESSION['client_name']) ? $_SESSION['client_name'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms & Conditions - LUXE SCENT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;600;700;800;900&family=Cormorant+Garamond:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-dark: #0a192f;
            --primary-light: #112240;
            --accent-gold: #d4af37;
            --accent-gold-hover: #f7d26b;
            --light-bg: #f4f6f9;
            --white: #ffffff;
            --text-muted: #8892b0;
            --text-light: #a0b8cc;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { height: 100%; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--primary-dark);
            min-height: 100vh;
            overflow-x: hidden;
            position: relative;
            color: #fff;
        }
        
        /* Background */
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
                radial-gradient(ellipse at 25% 25%, rgba(212, 175, 55, 0.15) 0%, transparent 50%),
                radial-gradient(ellipse at 75% 75%, rgba(100, 130, 200, 0.12) 0%, transparent 50%),
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
            width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(212, 175, 55, 0.4) 0%, transparent 70%);
            top: -10%; left: -10%;
        }
        
        .glow-orb.orb-2 {
            width: 600px; height: 600px;
            background: radial-gradient(circle, rgba(100, 130, 200, 0.3) 0%, transparent 70%);
            bottom: -15%; right: -10%;
            animation-delay: -7s;
        }
        
        @keyframes orbFloat {
            0%, 100% { transform: translate(0, 0) scale(1); opacity: 0.5; }
            33% { transform: translate(60px, -40px) scale(1.15); opacity: 0.7; }
            66% { transform: translate(-40px, 50px) scale(0.9); opacity: 0.4; }
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
            0% { opacity: 0; transform: translateY(100vh) scale(0); }
            10% { opacity: 1; transform: translateY(90vh) scale(1); }
            90% { opacity: 0.8; }
            100% { opacity: 0; transform: translateY(-10vh) scale(0.5); }
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
        
        /* Header */
        .header-luxe {
            position: relative;
            z-index: 10;
            padding: 20px 0;
            background: rgba(10, 25, 47, 0.5);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(212, 175, 55, 0.2);
            animation: headerSlide 1s cubic-bezier(0.22, 1, 0.36, 1);
        }
        
        @keyframes headerSlide {
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
        
        .brand-logo:hover { color: var(--accent-gold); letter-spacing: 3px; }
        .brand-logo span { color: var(--accent-gold); }
        
        .btn-back {
            background: transparent;
            border: 1px solid rgba(212, 175, 55, 0.4);
            color: rgba(255, 255, 255, 0.85);
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
        
        /* Hero */
        .page-hero {
            position: relative;
            z-index: 5;
            padding: 70px 0 40px;
            text-align: center;
        }
        
        .hero-crown {
            width: 90px;
            height: 90px;
            background: linear-gradient(135deg, var(--accent-gold), #b8941f);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            font-size: 2.5rem;
            color: var(--primary-dark);
            box-shadow: 
                0 15px 40px rgba(212, 175, 55, 0.4),
                0 0 0 10px rgba(212, 175, 55, 0.1);
            position: relative;
            animation: crownPulse 3s ease-in-out infinite;
        }
        
        .hero-crown::before,
        .hero-crown::after {
            content: '';
            position: absolute;
            inset: -12px;
            border-radius: 50%;
            border: 2px solid rgba(212, 175, 55, 0.3);
            animation: crownRing 3s ease-out infinite;
        }
        
        .hero-crown::after { animation-delay: 1.5s; }
        
        @keyframes crownPulse {
            0%, 100% { 
                box-shadow: 
                    0 15px 40px rgba(212, 175, 55, 0.4),
                    0 0 0 10px rgba(212, 175, 55, 0.1);
            }
            50% { 
                box-shadow: 
                    0 20px 50px rgba(212, 175, 55, 0.6),
                    0 0 0 15px rgba(212, 175, 55, 0.05);
            }
        }
        
        @keyframes crownRing {
            0% { transform: scale(1); opacity: 0.8; }
            100% { transform: scale(1.5); opacity: 0; }
        }
        
        .hero-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 3.2rem;
            font-weight: 700;
            letter-spacing: 3px;
            background: linear-gradient(135deg, #fff 0%, var(--accent-gold) 50%, #fff 100%);
            background-size: 200% auto;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: titleShine 4s ease-in-out infinite;
            margin-bottom: 12px;
        }
        
        @keyframes titleShine {
            0%, 100% { background-position: 0% center; }
            50% { background-position: 100% center; }
        }
        
        .hero-subtitle {
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.85rem;
            letter-spacing: 4px;
            text-transform: uppercase;
            font-weight: 300;
        }
        
        .hero-subtitle::before,
        .hero-subtitle::after {
            content: '◆';
            color: var(--accent-gold);
            margin: 0 12px;
            font-size: 0.6rem;
        }
        
        /* Content Card */
        .content-wrapper {
            position: relative;
            z-index: 5;
            padding: 30px 20px 80px;
        }
        
        .content-card {
            max-width: 1000px;
            margin: 0 auto;
            background: rgba(10, 25, 47, 0.6);
            backdrop-filter: blur(25px);
            border-radius: 24px;
            padding: 55px 50px;
            border: 1px solid rgba(212, 175, 55, 0.25);
            box-shadow: 
                0 30px 80px rgba(0, 0, 0, 0.5),
                inset 0 1px 0 rgba(255, 255, 255, 0.05);
            position: relative;
            overflow: hidden;
            animation: cardEntrance 1.2s cubic-bezier(0.22, 1, 0.36, 1) 0.2s both;
        }
        
        .content-card::before {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 100%;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--accent-gold), #fff8dc, var(--accent-gold), transparent);
            animation: cardShimmer 4s ease-in-out infinite;
        }
        
        @keyframes cardShimmer {
            0% { left: -100%; }
            50%, 100% { left: 100%; }
        }
        
        @keyframes cardEntrance {
            from { opacity: 0; transform: translateY(50px) scale(0.95); filter: blur(8px); }
            to { opacity: 1; transform: translateY(0) scale(1); filter: blur(0); }
        }
        
        .policy-section {
            margin-bottom: 40px;
            padding-bottom: 35px;
            border-bottom: 1px solid rgba(212, 175, 55, 0.15);
            animation: sectionFadeIn 0.8s cubic-bezier(0.22, 1, 0.36, 1) both;
        }
        
        .policy-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .policy-section:nth-child(1) { animation-delay: 0.4s; }
        .policy-section:nth-child(2) { animation-delay: 0.5s; }
        .policy-section:nth-child(3) { animation-delay: 0.6s; }
        .policy-section:nth-child(4) { animation-delay: 0.7s; }
        .policy-section:nth-child(5) { animation-delay: 0.8s; }
        .policy-section:nth-child(6) { animation-delay: 0.9s; }
        .policy-section:nth-child(7) { animation-delay: 1s; }
        
        @keyframes sectionFadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .section-heading {
            display: flex;
            align-items: center;
            gap: 14px;
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.6rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 20px;
            letter-spacing: 0.5px;
        }
        
        .section-heading .heading-icon {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, var(--accent-gold), #b8941f);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: var(--primary-dark);
            flex-shrink: 0;
            box-shadow: 0 8px 20px rgba(212, 175, 55, 0.3);
        }
        
        .section-heading .heading-num {
            font-size: 0.7rem;
            color: var(--accent-gold);
            background: rgba(212, 175, 55, 0.15);
            padding: 3px 10px;
            border-radius: 50px;
            letter-spacing: 1px;
            font-family: 'Inter', sans-serif;
            font-weight: 700;
            margin-left: auto;
        }
        
        .section-text {
            color: rgba(255, 255, 255, 0.75);
            font-size: 0.95rem;
            line-height: 1.9;
            letter-spacing: 0.2px;
        }
        
        .section-text strong {
            color: var(--accent-gold);
            font-weight: 700;
        }
        
        .info-box {
            background: linear-gradient(135deg, rgba(212, 175, 55, 0.08), rgba(212, 175, 55, 0.02));
            border-left: 4px solid var(--accent-gold);
            border-radius: 0 14px 14px 0;
            padding: 20px 24px;
            margin: 20px 0;
            color: rgba(255, 255, 255, 0.85);
            font-size: 0.92rem;
            line-height: 1.8;
        }
        
        .info-box i {
            color: var(--accent-gold);
            margin-right: 8px;
        }
        
        .info-box.warning {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(245, 158, 11, 0.02));
            border-left-color: #f59e0b;
        }
        
        .info-box.warning i { color: #f59e0b; }
        
        .info-box.danger {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(239, 68, 68, 0.02));
            border-left-color: #ef4444;
        }
        
        .info-box.danger i { color: #ef4444; }
        
        .steps-list {
            list-style: none;
            padding: 0;
            margin: 20px 0;
        }
        
        .steps-list li {
            display: flex;
            gap: 16px;
            padding: 14px 0;
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.92rem;
            line-height: 1.7;
            border-bottom: 1px dashed rgba(212, 175, 55, 0.15);
        }
        
        .steps-list li:last-child { border-bottom: none; }
        
        .step-number {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, var(--accent-gold), #b8941f);
            color: var(--primary-dark);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 0.85rem;
            flex-shrink: 0;
            box-shadow: 0 6px 15px rgba(212, 175, 55, 0.3);
        }
        
        .contact-box {
            background: linear-gradient(135deg, rgba(212, 175, 55, 0.15), rgba(212, 175, 55, 0.05));
            border: 1px solid rgba(212, 175, 55, 0.3);
            border-radius: 18px;
            padding: 30px;
            margin-top: 40px;
            text-align: center;
        }
        
        .contact-box h4 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 10px;
        }
        
        .contact-box p {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
            margin-bottom: 20px;
        }
        
        .contact-links {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .contact-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 22px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(212, 175, 55, 0.3);
            border-radius: 50px;
            color: #fff;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.4s ease;
        }
        
        .contact-link:hover {
            background: var(--accent-gold);
            color: var(--primary-dark);
            border-color: var(--accent-gold);
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(212, 175, 55, 0.4);
        }
        
        .mini-footer {
            position: relative;
            z-index: 5;
            padding: 30px 0;
            text-align: center;
            color: var(--text-muted);
            font-size: 0.85rem;
            border-top: 1px solid rgba(212, 175, 55, 0.15);
            background: rgba(5, 14, 26, 0.5);
            backdrop-filter: blur(20px);
        }
        
        .mini-footer i { color: var(--accent-gold); margin-right: 6px; }
        
        .mini-footer a {
            color: var(--accent-gold);
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .mini-footer a:hover { text-decoration: underline; }
        
        @media (max-width: 768px) {
            .hero-title { font-size: 2.2rem; }
            .hero-crown { width: 70px; height: 70px; font-size: 1.9rem; }
            .content-card { padding: 35px 25px; border-radius: 20px; }
            .section-heading { font-size: 1.3rem; }
            .section-heading .heading-icon { width: 38px; height: 38px; font-size: 1rem; }
            .brand-logo { font-size: 1.4rem; }
        }
        
        @media (max-width: 480px) {
            .hero-title { font-size: 1.7rem; letter-spacing: 2px; }
            .content-card { padding: 28px 20px; }
            .contact-links { flex-direction: column; }
            .contact-link { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>

<!-- Animated Background -->
<div class="bg-layer">
    <div class="bg-gradient"></div>
    <div class="bg-pattern"></div>
    <div class="glow-orb orb-1"></div>
    <div class="glow-orb orb-2"></div>
    <div class="particles-container">
        <div class="particle"></div><div class="particle"></div>
        <div class="particle"></div><div class="particle"></div>
        <div class="particle"></div><div class="particle"></div>
        <div class="particle"></div><div class="particle"></div>
        <div class="particle"></div><div class="particle"></div>
    </div>
</div>

<!-- Header -->
<header class="header-luxe">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center">
            <a href="index.php" class="brand-logo">
                Mr.<span>AYS</span>
            </a>
            <a href="index.php" class="btn-back">
                <i class="bi bi-arrow-left"></i> Back to Store
            </a>
        </div>
    </div>
</header>

<!-- Hero -->
<section class="page-hero">
    <div class="container">
        <div class="hero-crown">
            <i class="bi bi-file-earmark-text-fill"></i>
        </div>
        <h1 class="hero-title">Terms & Conditions</h1>
        <p class="hero-subtitle">Our Agreement</p>
    </div>
</section>

<!-- Content -->
<div class="content-wrapper">
    <div class="container">
        <div class="content-card">
            
            <!-- Section 1 -->
            <div class="policy-section">
                <h3 class="section-heading">
                    <span class="heading-icon"><i class="bi bi-info-circle-fill"></i></span>
                    Agreement to Terms
                    <span class="heading-num">01</span>
                </h3>
                <p class="section-text">
                    By accessing and using <strong>Mr.AYS</strong> ("we", "us", or "our"), you agree to be bound by these 
                    Terms & Conditions. If you do not agree with any part of these terms, please do not use our website 
                    or services.
                </p>
                <div class="info-box">
                    <i class="bi bi-check-circle-fill"></i>
                    Your continued use of Mr.AYS constitutes your acceptance of these terms.
                </div>
            </div>
            
            <!-- Section 2 -->
            <div class="policy-section">
                <h3 class="section-heading">
                    <span class="heading-icon"><i class="bi bi-person-badge-fill"></i></span>
                    User Accounts
                    <span class="heading-num">02</span>
                </h3>
                <p class="section-text">When creating an account with us, you agree to:</p>
                <ul class="steps-list">
                    <li>
                        <span class="step-number"><i class="bi bi-check"></i></span>
                        <div>Provide <strong>accurate, current, and complete</strong> information</div>
                    </li>
                    <li>
                        <span class="step-number"><i class="bi bi-check"></i></span>
                        <div>Maintain the <strong>security of your password</strong> and account</div>
                    </li>
                    <li>
                        <span class="step-number"><i class="bi bi-check"></i></span>
                        <div>Accept responsibility for all activities under your account</div>
                    </li>
                    <li>
                        <span class="step-number"><i class="bi bi-check"></i></span>
                        <div>Notify us immediately of any <strong>unauthorized access</strong></div>
                    </li>
                </ul>
            </div>
            
            <!-- Section 3 -->
            <div class="policy-section">
                <h3 class="section-heading">
                    <span class="heading-icon"><i class="bi bi-cart-check-fill"></i></span>
                    Orders & Payments
                    <span class="heading-num">03</span>
                </h3>
                <p class="section-text">
                    All orders are subject to <strong>acceptance and availability</strong>. We reserve the right to refuse 
                    or cancel any order for any reason, including:
                </p>
                <ul class="steps-list">
                    <li><span class="step-number"><i class="bi bi-x"></i></span><div>Product unavailability or pricing errors</div></li>
                    <li><span class="step-number"><i class="bi bi-x"></i></span><div>Suspected fraudulent activity</div></li>
                    <li><span class="step-number"><i class="bi bi-x"></i></span><div>Incorrect customer information</div></li>
                </ul>
                <div class="info-box warning">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    Prices are subject to change without notice. All payments are processed securely through third-party gateways.
                </div>
            </div>
            
            <!-- Section 4 -->
            <div class="policy-section">
                <h3 class="section-heading">
                    <span class="heading-icon"><i class="bi bi-truck"></i></span>
                    Shipping & Delivery
                    <span class="heading-num">04</span>
                </h3>
                <p class="section-text">
                    Delivery times are estimates and may vary based on location, weather, or courier delays. 
                   Mr.AYS is <strong>not responsible for delays</strong> caused by circumstances beyond our control.
                </p>
                <div class="info-box">
                    <i class="bi bi-info-circle-fill"></i>
                    Risk of loss and title for products pass to you upon delivery to the carrier.
                </div>
            </div>
            
            <!-- Section 5 -->
            <div class="policy-section">
                <h3 class="section-heading">
                    <span class="heading-icon"><i class="bi bi-shield-exclamation"></i></span>
                    Intellectual Property
                    <span class="heading-num">05</span>
                </h3>
                <p class="section-text">
                    All content on this website — including <strong>logos, text, images, graphics, and software</strong> — 
                    is the exclusive property of Mr.AYS and is protected by copyright and trademark laws. 
                    You may not reproduce, distribute, or modify any content without our written permission.
                </p>
                <div class="info-box danger">
                    <i class="bi bi-shield-slash-fill"></i>
                    <strong>Prohibited:</strong> Unauthorized use of our brand, images, or content is strictly forbidden.
                </div>
            </div>
            
            <!-- Section 6 -->
            <div class="policy-section">
                <h3 class="section-heading">
                    <span class="heading-icon"><i class="bi bi-x-octagon-fill"></i></span>
                    Limitation of Liability
                    <span class="heading-num">06</span>
                </h3>
                <p class="section-text">
                    Mr.AYS shall not be held liable for any <strong>indirect, incidental, or consequential damages</strong> 
                    arising from the use of our products or services. Our total liability shall not exceed the amount 
                    paid for the product in question.
                </p>
            </div>
            
            <!-- Section 7 -->
            <div class="policy-section">
                <h3 class="section-heading">
                    <span class="heading-icon"><i class="bi bi-arrow-repeat"></i></span>
                    Changes to Terms
                    <span class="heading-num">07</span>
                </h3>
                <p class="section-text">
                    We reserve the right to update or modify these Terms & Conditions at any time. 
                    Changes take effect immediately upon posting. Your continued use of the site after changes 
                    signifies your acceptance of the updated terms.
                </p>
            </div>
            
            <!-- Contact -->
            <div class="contact-box">
                <h4>Questions About Terms?</h4>
                <p>Reach out to our legal team for clarifications</p>
                <div class="contact-links">
                    <a href="mailto:legal@luxescent.com" class="contact-link">
                        <i class="bi bi-envelope-fill"></i> mrays@gmail.com
                    </a>
                    <a href="tel:+923001234567" class="contact-link">
                        <i class="bi bi-telephone-fill"></i>03263368118
                    </a>
                </div>
            </div>
            
        </div>
    </div>
</div>

<!-- Footer -->
<footer class="mini-footer">
    <div class="container">
            &copy; 2026 AYS. All Rights Reserved. powered by <a href="https://zam2.vercel.app" target="_blank" rel="noopener noreferrer" style="color: #ffcc00; text-decoration: none; font-weight: bold;">ZAM Digital Agency</a>
        <span style="margin: 0 10px; opacity: 0.4;">|</span>
        <a href="index.php">Home</a>
        <span style="margin: 0 8px; opacity: 0.4;">·</span>
        <a href="return-policy.php">Returns</a>
        <span style="margin: 0 8px; opacity: 0.4;">·</span>
        <a href="privacy-policy.php">Privacy</a>
        <span style="margin: 0 8px; opacity: 0.4;">·</span>
        <a href="shipping-info.php">Shipping</a>
    </div>
</footer>

</body>
</html>