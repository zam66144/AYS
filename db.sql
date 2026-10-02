-- Create database
CREATE DATABASE IF NOT EXISTS luxe_scent_db;
USE luxe_scent_db;

-- Clients table
CREATE TABLE clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Products table with category
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    category VARCHAR(50) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    original_price DECIMAL(10,2),
    discount INT DEFAULT 0,
    image_url VARCHAR(255) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Orders table
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    product_id INT NOT NULL,
    product_name VARCHAR(100) NOT NULL,
    quantity INT NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'processed', 'returned', 'cancelled') DEFAULT 'pending',
    screenshot LONGTEXT,
    verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
);

-- Insert sample products with categories
INSERT INTO products (name, category, price, original_price, discount, image_url, description) VALUES
-- Perfumes
('Oud Noir Deluxe', 'perfume', 89.00, 120.00, 25, 'https://images.pexels.com/photos/66704/pexels-photo-66704.jpeg?auto=compress&cs=tinysrgb&w=300', 'Deep woody oriental fragrance with notes of agarwood and spice'),
('Rose Ambre Royale', 'perfume', 72.00, 95.00, 24, 'https://images.pexels.com/photos/6943020/pexels-photo-6943020.jpeg?auto=compress&cs=tinysrgb&w=300', 'Romantic floral with amber notes and a hint of vanilla'),
('Santal Blanc Premium', 'perfume', 95.00, 130.00, 27, 'https://images.pexels.com/photos/965989/pexels-photo-965989.jpeg?auto=compress&cs=tinysrgb&w=300', 'Creamy sandalwood with bergamot and cedar'),
('Citron Vert Fresh', 'perfume', 64.00, 85.00, 25, 'https://images.pexels.com/photos/7075100/pexels-photo-7075100.jpeg?auto=compress&cs=tinysrgb&w=300', 'Fresh citrus with green notes and a touch of mint'),
('Amber Noir', 'perfume', 78.00, 110.00, 29, 'https://images.pexels.com/photos/6943022/pexels-photo-6943022.jpeg?auto=compress&cs=tinysrgb&w=300', 'Warm amber with hints of vanilla and musk'),

-- Testers
('Oud Noir Tester', 'tester', 45.00, 89.00, 49, 'https://images.pexels.com/photos/66704/pexels-photo-66704.jpeg?auto=compress&cs=tinysrgb&w=300', 'Tester version - 30ml'),
('Rose Ambre Tester', 'tester', 38.00, 72.00, 47, 'https://images.pexels.com/photos/6943020/pexels-photo-6943020.jpeg?auto=compress&cs=tinysrgb&w=300', 'Tester version - 30ml'),
('Santal Blanc Tester', 'tester', 48.00, 95.00, 49, 'https://images.pexels.com/photos/965989/pexels-photo-965989.jpeg?auto=compress&cs=tinysrgb&w=300', 'Tester version - 30ml'),

-- Watches
('Luxe Chronograph Gold', 'watch', 299.00, 450.00, 33, 'https://images.pexels.com/photos/190819/pexels-photo-190819.jpeg?auto=compress&cs=tinysrgb&w=300', 'Gold plated chronograph with leather strap'),
('Slim Classic Silver', 'watch', 199.00, 280.00, 29, 'https://images.pexels.com/photos/277390/pexels-photo-277390.jpeg?auto=compress&cs=tinysrgb&w=300', 'Minimalist silver watch with mesh strap'),
('Sport Diver Blue', 'watch', 249.00, 350.00, 29, 'https://images.pexels.com/photos/428397/pexels-photo-428397.jpeg?auto=compress&cs=tinysrgb&w=300', 'Water resistant diver watch with blue dial'),

-- Glasses
('Aviator Gold Frame', 'glass', 159.00, 220.00, 28, 'https://images.pexels.com/photos/310790/pexels-photo-310790.jpeg?auto=compress&cs=tinysrgb&w=300', 'Classic aviator style with gold frame'),
('Wayfarer Black', 'glass', 129.00, 180.00, 28, 'https://images.pexels.com/photos/3013504/pexels-photo-3013504.jpeg?auto=compress&cs=tinysrgb&w=300', 'Modern wayfarer with black frame'),
('Round Tortoise', 'glass', 149.00, 200.00, 25, 'https://images.pexels.com/photos/268443/pexels-photo-268443.jpeg?auto=compress&cs=tinysrgb&w=300', 'Vintage round frame with tortoise finish');

-- Insert default admin (password: luxe123)
INSERT INTO clients (name, email, password) VALUES 
('Admin', 'admin@luxe.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');