-- Database: bar_system
CREATE DATABASE IF NOT EXISTS bar_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bar_system;

-- Roles table
CREATE TABLE IF NOT EXISTS roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- Users table
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL UNIQUE,
  username VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role_id INT NOT NULL,
  status ENUM('active','inactive') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Categories table
CREATE TABLE IF NOT EXISTS categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  date_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Products table
CREATE TABLE IF NOT EXISTS products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  stock INT DEFAULT 0,
  category_id INT,
  image VARCHAR(255),
  status ENUM('active','inactive') DEFAULT 'active',
  date_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Tables (restaurant tables) table
CREATE TABLE IF NOT EXISTS tables (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE,
  status ENUM('available','occupied','reserved') DEFAULT 'available'
) ENGINE=InnoDB;

-- Orders table
CREATE TABLE IF NOT EXISTS orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  table_id INT NOT NULL,
  user_id INT NOT NULL,
  total DECIMAL(10,2) NOT NULL DEFAULT 0,
  status ENUM('pending','completed','cancelled') DEFAULT 'pending',
  date_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (table_id) REFERENCES tables(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Order details table
CREATE TABLE IF NOT EXISTS order_details (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  product_id INT NOT NULL,
  quantity INT NOT NULL DEFAULT 1,
  price DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Payments table
CREATE TABLE IF NOT EXISTS payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  method ENUM('cash','transfer','card') NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  cash_register_id INT,
  date_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  FOREIGN KEY (cash_register_id) REFERENCES cash_register(id)
) ENGINE=InnoDB;

-- Cash register table
CREATE TABLE IF NOT EXISTS cash_register (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  type ENUM('open','close') NOT NULL,
  date_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  status ENUM('active','closed') DEFAULT 'active',
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;
-- Insert default roles
INSERT INTO roles (name) VALUES ('Admin'), ('Waiter'), ('Cashier'), ('Kitchen');

-- Insert default admin user (username: admin, password: admin123)
INSERT INTO users (name, email, username, password, role_id) VALUES (
    'Admin User',
    'admin@example.com',
    'admin',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    1
);

-- Insert kitchen user (username: cocina, password: cocina123)
INSERT INTO users (name, email, username, password, role_id) VALUES (
    'Usuario Cocina',
    'cocina@example.com',
    'cocina',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    4
);

-- Insert waiter user (username: mesero, password: mesero123)
INSERT INTO users (name, email, username, password, role_id) VALUES (
    'Usuario Mesero',
    'mesero@example.com',
    'mesero',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    2
);

-- Insert default tables
INSERT INTO tables (name, status) VALUES 
('Mesa 1', 'available'),
('Mesa 2', 'available'),
('Mesa 3', 'available'),
('Mesa 4', 'available'),
('Mesa 5', 'available'),
('Mesa 6', 'available');

-- Insert default categories
INSERT INTO categories (name) VALUES 
('Bebidas'),
('Comidas'),
('Postres'),
('Cócteles');

-- Insert default products
INSERT INTO products (code, name, price, stock, category_id, status) VALUES 
-- Bebidas
('BEB001', 'Cerveza Corona', 3.50, 100, 1, 'active'),
('BEB002', 'Cerveza Heineken', 4.00, 80, 1, 'active'),
('BEB003', 'Refresco Coca-Cola', 2.00, 150, 1, 'active'),
('BEB004', 'Agua Mineral', 1.50, 200, 1, 'active'),
('BEB005', 'Jugo Natural', 3.00, 50, 1, 'active'),

-- Comidas
('COM001', 'Hamburguesa Clásica', 8.50, 50, 2, 'active'),
('COM002', 'Pizza Margarita', 12.00, 30, 2, 'active'),
('COM003', 'Alitas Picantes', 9.00, 40, 2, 'active'),
('COM004', 'Nachos con Queso', 7.50, 60, 2, 'active'),
('COM005', 'Papas Fritas', 4.50, 100, 2, 'active'),

-- Postres
('POS001', 'Brownie con Helado', 5.50, 25, 3, 'active'),
('POS002', 'Cheesecake', 6.00, 20, 3, 'active'),
('POS003', 'Helado Artesanal', 4.00, 40, 3, 'active'),

-- Cócteles
('COC001', 'Mojito', 7.00, 50, 4, 'active'),
('COC002', 'Margarita', 7.50, 45, 4, 'active'),
('COC003', 'Piña Colada', 8.00, 40, 4, 'active'),
('COC004', 'Daiquiri', 7.00, 35, 4, 'active'),
('COC005', 'Cosmopolitan', 8.50, 30, 4, 'active');

