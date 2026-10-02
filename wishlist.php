<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['client_id'])) {
    header("Location: login.php");
    exit();
}

$client_id = $_SESSION['client_id'];

// Fetch wishlist items from database
$stmt = $pdo->prepare("
    SELECT w.id as wishlist_id, w.product_id, p.name, p.price, p.image_url, p.description 
    FROM wishlist w 
    JOIN products p ON w.product_id = p.id 
    WHERE w.client_id = ?
");
$stmt->execute([$client_id]);
$wishlistItems = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Wishlist - LUXE SCENT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;600;700;800;900&family=Cormorant+Garamond:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root { 
            --accent-gold: #d4af37; 
            --accent-gold-hover: #f7d26b;
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
            color: #fff;
        }
        
        .gold-text { color: var(--accent-gold) !important; }
        .main-content { flex: 1; position: relative; z-index: 5; }
        
        /* ========================== */
        /* ANIMATED LUXURY BACKGROUND */
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
                radial-gradient(ellipse at 20% 20%, rgba(212, 175, 55, 0.12) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 80%, rgba(212, 175, 55, 0.08) 0%, transparent 50%),
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
        
        /* Big rotating glow orbs */
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
        
        /* Floating gold particles */
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
            background: rgba(10, 25, 47, 0.5);
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
        
        /* ========================== */
        /* PAGE TITLE SECTION          */
        /* ========================== */
        .page-title-section {
            padding: 60px 0 40px;
            text-align: center;
            animation: titleFadeIn 1.2s cubic-bezier(0.22, 1, 0.36, 1) 0.2s both;
        }
        
        @keyframes titleFadeIn {
            from {
                opacity: 0;
                transform: translateY(40px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .page-crown {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--accent-gold), #b8941f);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            font-size: 2.2rem;
            color: var(--primary-dark);
            box-shadow: 
                0 10px 30px rgba(212, 175, 55, 0.4),
                0 0 0 8px rgba(212, 175, 55, 0.1);
            animation: crownPulse 3s ease-in-out infinite;
            position: relative;
        }
        
        .page-crown::before {
            content: '';
            position: absolute;
            inset: -12px;
            border-radius: 50%;
            border: 2px solid rgba(212, 175, 55, 0.3);
            animation: crownRing 3s ease-out infinite;
        }
        
        .page-crown::after {
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
        
        .page-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 3rem;
            font-weight: 700;
            letter-spacing: 3px;
            margin-bottom: 12px;
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
        
        .page-subtitle {
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.85rem;
            letter-spacing: 4px;
            text-transform: uppercase;
            font-weight: 300;
        }
        
        .page-subtitle::before,
        .page-subtitle::after {
            content: '◆';
            color: var(--accent-gold);
            margin: 0 12px;
            font-size: 0.6rem;
        }
        
        /* ========================== */
        /* WISHLIST CARDS - GLASS MORPHISM */
        /* ========================== */
        .wishlist-grid {
            padding: 20px 0 80px;
        }
        
        .wishlist-card {
            background: rgba(10, 25, 47, 0.55);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid rgba(212, 175, 55, 0.2);
            box-shadow: 
                0 15px 40px rgba(0, 0, 0, 0.4),
                inset 0 1px 0 rgba(255, 255, 255, 0.05);
            transition: all 0.5s cubic-bezier(0.22, 1, 0.36, 1);
            position: relative;
            height: 100%;
            display: flex;
            flex-direction: column;
            animation: cardEntrance 0.8s cubic-bezier(0.22, 1, 0.36, 1) both;
        }
        
        .wishlist-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--accent-gold), transparent);
            animation: cardShimmer 4s ease-in-out infinite;
            z-index: 2;
        }
        
        @keyframes cardShimmer {
            0% { left: -100%; }
            50%, 100% { left: 100%; }
        }
        
        @keyframes cardEntrance {
            from {
                opacity: 0;
                transform: translateY(40px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        .wishlist-card:hover {
            border-color: rgba(212, 175, 55, 0.6);
            box-shadow: 
                0 25px 60px rgba(0, 0, 0, 0.5),
                0 0 60px rgba(212, 175, 55, 0.15),
                inset 0 1px 0 rgba(255, 255, 255, 0.08);
            transform: translateY(-8px);
        }
        
        .wishlist-image-wrapper {
            position: relative;
            overflow: hidden;
            height: 220px;
        }
        
        .wishlist-image-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.6s cubic-bezier(0.22, 1, 0.36, 1);
        }
        
        .wishlist-card:hover .wishlist-image-wrapper img {
            transform: scale(1.08);
        }
        
        .wishlist-image-wrapper::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, transparent 40%, rgba(10, 25, 47, 0.9) 100%);
            pointer-events: none;
        }
        
        .wishlist-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: linear-gradient(135deg, var(--accent-gold), #b8941f);
            color: var(--primary-dark);
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            z-index: 2;
            box-shadow: 0 4px 15px rgba(212, 175, 55, 0.4);
        }
        
        .wishlist-body {
            padding: 22px;
            display: flex;
            flex-direction: column;
            flex: 1;
        }
        
        .wishlist-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.35rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 8px;
            letter-spacing: 0.5px;
            line-height: 1.3;
        }
        
        .wishlist-price {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--accent-gold);
            margin-bottom: 15px;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .wishlist-price::before {
            content: '';
            display: inline-block;
            width: 20px;
            height: 2px;
            background: var(--accent-gold);
            opacity: 0.6;
        }
        
        .wishlist-description {
            font-size: 0.82rem;
            color: rgba(255, 255, 255, 0.5);
            line-height: 1.6;
            margin-bottom: 20px;
            flex: 1;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .wishlist-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        /* ========================== */
        /* ADD TO CART BUTTON          */
        /* ========================== */
        .wishlist-btn {
            flex: 1;
            background: linear-gradient(135deg, var(--accent-gold) 0%, #b8941f 100%);
            color: var(--primary-dark);
            font-weight: 700;
            font-size: 0.82rem;
            letter-spacing: 1px;
            text-transform: uppercase;
            padding: 12px 20px;
            border-radius: 50px;
            border: none;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            transition: all 0.4s ease;
            box-shadow: 
                0 8px 20px rgba(212, 175, 55, 0.25),
                inset 0 1px 0 rgba(255, 255, 255, 0.3);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        
        .wishlist-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            transition: left 0.6s ease;
        }
        
        .wishlist-btn:hover {
            transform: translateY(-3px);
            box-shadow: 
                0 15px 35px rgba(212, 175, 55, 0.5),
                inset 0 1px 0 rgba(255, 255, 255, 0.4);
            background: linear-gradient(135deg, var(--accent-gold-hover) 0%, var(--accent-gold) 100%);
        }
        
        .wishlist-btn:hover::before {
            left: 100%;
        }
        
        .wishlist-btn:active {
            transform: translateY(-1px);
        }
        
        /* ========================== */
        /* REMOVE BUTTON               */
        /* ========================== */
        .remove-btn {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: rgba(220, 53, 69, 0.1);
            border: 1px solid rgba(220, 53, 69, 0.4);
            color: #ff6b7d;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            transition: all 0.4s ease;
            flex-shrink: 0;
        }
        
        .remove-btn:hover {
            background: #dc3545;
            color: white;
            border-color: #dc3545;
            transform: rotate(90deg) scale(1.1);
            box-shadow: 0 8px 25px rgba(220, 53, 69, 0.5);
        }
        
        /* ========================== */
        /* EMPTY STATE                 */
        /* ========================== */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: rgba(10, 25, 47, 0.5);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 24px;
            border: 1px solid rgba(212, 175, 55, 0.2);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
            animation: cardEntrance 1s cubic-bezier(0.22, 1, 0.36, 1);
            position: relative;
            overflow: hidden;
        }
        
        .empty-state::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--accent-gold), transparent);
            animation: cardShimmer 4s ease-in-out infinite;
        }
        
        .empty-state-icon {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, rgba(212, 175, 55, 0.15), rgba(212, 175, 55, 0.05));
            border: 2px solid rgba(212, 175, 55, 0.3);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            font-size: 3rem;
            color: var(--accent-gold);
            animation: emptyIconFloat 4s ease-in-out infinite;
            position: relative;
        }
        
        .empty-state-icon::before,
        .empty-state-icon::after {
            content: '';
            position: absolute;
            inset: -8px;
            border-radius: 50%;
            border: 1px solid rgba(212, 175, 55, 0.2);
            animation: emptyRipple 3s ease-out infinite;
        }
        
        .empty-state-icon::after {
            animation-delay: 1.5s;
        }
        
        @keyframes emptyIconFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
        @keyframes emptyRipple {
            0% {
                transform: scale(1);
                opacity: 0.8;
            }
            100% {
                transform: scale(1.5);
                opacity: 0;
            }
        }
        
        .empty-state h3 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 12px;
            letter-spacing: 1px;
        }
        
        .empty-state p {
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.95rem;
            margin-bottom: 30px;
            letter-spacing: 0.5px;
        }
        
        /* ========================== */
        /* BUTTONS                     */
        /* ========================== */
        .btn-gold-luxe {
            background: linear-gradient(135deg, var(--accent-gold) 0%, #b8941f 100%);
            color: var(--primary-dark);
            font-weight: 700;
            font-size: 0.9rem;
            letter-spacing: 2px;
            text-transform: uppercase;
            padding: 15px 35px;
            border-radius: 50px;
            border: none;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            transition: all 0.4s ease;
            box-shadow: 
                0 10px 30px rgba(212, 175, 55, 0.3),
                inset 0 1px 0 rgba(255, 255, 255, 0.3);
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
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
            color: var(--primary-dark);
            background: linear-gradient(135deg, var(--accent-gold-hover) 0%, var(--accent-gold) 100%);
        }
        
        .btn-gold-luxe:hover::before {
            left: 100%;
        }
        
        /* ========================== */
        /* PREMIUM FOOTER              */
        /* ========================== */
        .footer {
            background: rgba(5, 14, 26, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
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
        @media (max-width: 992px) {
            .page-title { font-size: 2.4rem; }
        }
        
        @media (max-width: 768px) {
            .page-title { font-size: 2rem; }
            .page-crown { width: 70px; height: 70px; font-size: 1.8rem; }
            .page-title-section { padding: 40px 0 30px; }
            
            .wishlist-image-wrapper { height: 180px; }
            .wishlist-title { font-size: 1.15rem; }
            .wishlist-price { font-size: 1.15rem; }
            
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
            
            .brand-logo { font-size: 1.4rem; }
        }
        
        @media (max-width: 576px) {
            .page-title { font-size: 1.6rem; letter-spacing: 2px; }
            .page-crown { width: 60px; height: 60px; font-size: 1.5rem; }
            .page-subtitle { font-size: 0.7rem; letter-spacing: 3px; }
            
            .wishlist-image-wrapper { height: 200px; }
            
            .empty-state-icon { width: 90px; height: 90px; font-size: 2.2rem; }
            .empty-state h3 { font-size: 1.5rem; }
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
<div class="main-content">
    
    <!-- Page Title Section -->
    <section class="page-title-section">
        <div class="container">
            <div class="page-crown">
                <i class="bi bi-heart-fill"></i>
            </div>
            <h1 class="page-title">My Wishlist</h1>
            <p class="page-subtitle">Your Curated Luxury Collection</p>
        </div>
    </section>

    <!-- Wishlist Grid -->
    <section class="wishlist-grid">
        <div class="container">
            <div class="row g-4">
                <?php if(count($wishlistItems) > 0): ?>
                    <?php foreach($wishlistItems as $index => $item): ?>
                    <div class="col-md-6 col-lg-4 col-xl-3">
                        <div class="wishlist-card" style="animation-delay: <?php echo ($index * 0.1); ?>s;">
                            <div class="wishlist-image-wrapper">
                                <span class="wishlist-badge">Premium</span>
                                <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                            </div>
                            <div class="wishlist-body">
                                <h5 class="wishlist-title"><?php echo htmlspecialchars($item['name']); ?></h5>
                                <div class="wishlist-price">PKR <?php echo number_format($item['price'], 2); ?></div>
                                <p class="wishlist-description"><?php echo htmlspecialchars($item['description'] ?? 'Exclusive luxury item curated just for you.'); ?></p>
                                <div class="wishlist-actions">
                                    <button class="wishlist-btn" onclick="addToCart(<?php echo $item['product_id']; ?>, '<?php echo addslashes($item['name']); ?>', <?php echo $item['price']; ?>, '<?php echo addslashes($item['image_url']); ?>')">
                                        <i class="bi bi-bag-plus"></i> Add to Cart
                                    </button>
                                    <button class="remove-btn" onclick="removeFromWishlist(<?php echo $item['product_id']; ?>)" title="Remove from wishlist">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="bi bi-heart"></i>
                            </div>
                            <h3>Your Wishlist Awaits</h3>
                            <p>Discover our exclusive luxury collection and start curating your favorites.</p>
                            <a href="index.php" class="btn-gold-luxe">
                                <i class="bi bi-grid"></i> Explore Collection
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
</div>

<!-- ============================================ -->
<!-- PREMIUM FOOTER                                -->
<!-- ============================================ -->
<footer class="footer">
    <div class="container">
        <div class="row g-4">
            <!-- Brand -->
            <div class="col-lg-4 col-md-6">
                <h6 class="gold-text">Mr. AYS</h6>
                <p class="brand-desc">
                    Premium luxury products since 2026. Discover our exclusive range of perfumes, testers, watches, and premium eyewear.
                </p>
                <div class="social-links">
                    <a href="https://www.facebook.com/profile.php?id=61593054195065&rdid=B8vJjskA9o9Ho0jJ&share_url=https%3A%2F%2Fwww.facebook.com%2Fshare%2F1LjNSGnjJx%2F#"><i class="bi bi-facebook"></i></a>
                    <a href="https://www.instagram.com/mr.ays_officiall?stkn=MWUzbGM1dHdxNXFkcQ=="><i class="bi bi-instagram"></i></a>
                    <a href=""><i class="bi bi-twitter-x"></i></a>
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
                    <li><a href="return-policy.php"><i class="bi bi-chevron-right"></i> Return Policy</a></li>
                    <li><a href="privacy-policy.php"><i class="bi bi-chevron-right"></i> Privacy Policy</a></li>
                    <li><a href="terms-conditions.php"><i class="bi bi-chevron-right"></i> Terms &amp; Conditions</a></li>
                    <li><a href="shipping-info.php"><i class="bi bi-chevron-right"></i> Shipping Info</a></li>
                </ul>
            </div>
        </div>

        <!-- Divider -->
        <div class="footer-divider"></div>

        <div class="copyright">
            &copy; 2026 AYS. All Rights Reserved. powered by <a href="https://zam2.vercel.app" target="_blank" rel="noopener noreferrer" style="color: #ffcc00; text-decoration: none; font-weight: bold;">ZAM Digital Agency</a>


        </div>
    </div>
</footer>

<script>
// ==========================
// ADD TO CART FUNCTION
// ==========================
function addToCart(id, name, price, image) {
    let cart = JSON.parse(localStorage.getItem('luxeCart')) || [];
    const existing = cart.find(item => item.id == id);
    if (existing) {
        existing.quantity += 1;
    } else {
        cart.push({ id, name, price: parseFloat(price), image, quantity: 1 });
    }
    localStorage.setItem('luxeCart', JSON.stringify(cart));
    showLuxuryToast('Added to cart! ✨', 'success');
}

// ==========================
// REMOVE FROM WISHLIST FUNCTION
// ==========================
function removeFromWishlist(productId) {
    if (!confirm('Are you sure you want to remove this item from your wishlist?')) {
        return;
    }

    fetch('remove-wishlist.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'product_id=' + productId
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showLuxuryToast('Removed from wishlist ✨', 'warning');
            setTimeout(() => location.reload(), 800);
        } else {
            showLuxuryToast(data.message || 'Something went wrong', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showLuxuryToast('Connection error. Please try again.', 'error');
    });
}

// ==========================
// LUXURY TOAST NOTIFICATION
// ==========================
function showLuxuryToast(message, type = 'info') {
    // Remove existing toast if any
    const existing = document.getElementById('luxuryToast');
    if (existing) existing.remove();
    
    const toast = document.createElement('div');
    toast.id = 'luxuryToast';
    
    let bgColor, borderColor, icon;
    if (type === 'success') {
        bgColor = 'linear-gradient(135deg, rgba(34, 197, 94, 0.95), rgba(22, 163, 74, 0.95))';
        borderColor = '#22c55e';
        icon = '✓';
    } else if (type === 'warning') {
        bgColor = 'linear-gradient(135deg, rgba(212, 175, 55, 0.95), rgba(184, 148, 31, 0.95))';
        borderColor = '#d4af37';
        icon = '!';
    } else if (type === 'error') {
        bgColor = 'linear-gradient(135deg, rgba(220, 53, 69, 0.95), rgba(185, 28, 28, 0.95))';
        borderColor = '#dc3545';
        icon = '×';
    } else {
        bgColor = 'linear-gradient(135deg, rgba(10, 25, 47, 0.95), rgba(17, 34, 64, 0.95))';
        borderColor = '#d4af37';
        icon = 'ℹ';
    }
    
    toast.style.cssText = `
        position: fixed;
        top: 30px;
        right: 30px;
        z-index: 99999;
        background: ${bgColor};
        color: #fff;
        padding: 18px 28px;
        border-radius: 50px;
        font-family: 'Inter', sans-serif;
        font-weight: 600;
        font-size: 0.9rem;
        letter-spacing: 0.5px;
        box-shadow: 0 15px 40px rgba(0, 0, 0, 0.4), 0 0 30px rgba(212, 175, 55, 0.2);
        border: 1px solid rgba(212, 175, 55, 0.5);
        display: flex;
        align-items: center;
        gap: 12px;
        transform: translateX(150%);
        transition: transform 0.5s cubic-bezier(0.22, 1, 0.36, 1);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
    `;
    
    toast.innerHTML = `
        <span style="
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            font-weight: 700;
            font-size: 0.9rem;
        ">${icon}</span>
        <span>${message}</span>
    `;
    
    document.body.appendChild(toast);
    
    // Slide in
    requestAnimationFrame(() => {
        toast.style.transform = 'translateX(0)';
    });
    
    // Slide out after delay
    setTimeout(() => {
        toast.style.transform = 'translateX(150%)';
        setTimeout(() => toast.remove(), 500);
    }, 2800);
}
</script>

</body>
</html>