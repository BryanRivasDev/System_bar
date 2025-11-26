-- Script de datos de ejemplo para un día normal de ventas
-- Ejecutar después de tener la base de datos limpia

USE bar_system;

-- Insertar Categorías
INSERT INTO categories (name) VALUES 
('Bebidas'),
('Cócteles'),
('Comidas'),
('Postres');

-- Insertar Productos
INSERT INTO products (code, name, price, stock, category_id, status) VALUES 
-- Bebidas
('BEB001', 'Cerveza Corona', 3.50, 50, 1, 'active'),
('BEB002', 'Refresco Coca-Cola', 2.00, 80, 1, 'active'),
('BEB003', 'Agua Mineral', 1.50, 100, 1, 'active'),

-- Cócteles
('COC001', 'Mojito', 7.00, 30, 2, 'active'),
('COC002', 'Margarita', 7.50, 25, 2, 'active'),

-- Comidas
('COM001', 'Hamburguesa Clásica', 8.50, 40, 3, 'active'),
('COM002', 'Nachos con Queso', 6.50, 35, 3, 'active'),
('COM003', 'Alitas Picantes', 9.00, 30, 3, 'active'),

-- Postres
('POS001', 'Brownie con Helado', 5.50, 20, 4, 'active'),
('POS002', 'Cheesecake', 6.00, 15, 4, 'active');

-- Insertar Mesas
INSERT INTO tables (name, status) VALUES 
('Mesa 1', 'available'),
('Mesa 2', 'available'),
('Mesa 3', 'available'),
('Mesa 4', 'available'),
('Mesa 5', 'available');

-- Obtener el ID del Super Admin para usar en los pedidos
SET @admin_user_id = (SELECT id FROM users WHERE is_super_admin = 1 LIMIT 1);

-- Verificar que existe un usuario
SELECT CONCAT('Usando usuario ID: ', @admin_user_id) as Info;

-- Simular ventas del día (pedidos completados)

-- Pedido 1 - Mesa 1 (Completado)
INSERT INTO orders (table_id, user_id, total, status, date_created) VALUES 
(1, @admin_user_id, 18.00, 'completed', DATE_SUB(NOW(), INTERVAL 3 HOUR));

SET @order1_id = LAST_INSERT_ID();

INSERT INTO order_details (order_id, product_id, quantity, price) VALUES 
(@order1_id, 1, 2, 3.50),  -- 2 Cervezas
(@order1_id, 6, 1, 8.50),  -- 1 Hamburguesa
(@order1_id, 3, 1, 1.50);  -- 1 Agua

INSERT INTO payments (order_id, method, amount, date_created) VALUES 
(@order1_id, 'cash', 18.00, DATE_SUB(NOW(), INTERVAL 3 HOUR));

-- Pedido 2 - Mesa 2 (Completado)
INSERT INTO orders (table_id, user_id, total, status, date_created) VALUES 
(2, @admin_user_id, 28.50, 'completed', DATE_SUB(NOW(), INTERVAL 2 HOUR));

SET @order2_id = LAST_INSERT_ID();

INSERT INTO order_details (order_id, product_id, quantity, price) VALUES 
(@order2_id, 4, 2, 7.00),  -- 2 Mojitos
(@order2_id, 7, 1, 6.50),  -- 1 Nachos
(@order2_id, 8, 1, 9.00);  -- 1 Alitas

INSERT INTO payments (order_id, method, amount, date_created) VALUES 
(@order2_id, 'card', 28.50, DATE_SUB(NOW(), INTERVAL 2 HOUR));

-- Pedido 3 - Mesa 3 (Completado)
INSERT INTO orders (table_id, user_id, total, status, date_created) VALUES 
(3, @admin_user_id, 23.00, 'completed', DATE_SUB(NOW(), INTERVAL 1 HOUR));

SET @order3_id = LAST_INSERT_ID();

INSERT INTO order_details (order_id, product_id, quantity, price) VALUES 
(@order3_id, 5, 2, 7.50),  -- 2 Margaritas
(@order3_id, 6, 1, 8.50),  -- 1 Hamburguesa
(@order3_id, 10, 1, 6.00); -- 1 Cheesecake

INSERT INTO payments (order_id, method, amount, date_created) VALUES 
(@order3_id, 'transfer', 23.00, DATE_SUB(NOW(), INTERVAL 1 HOUR));

-- Pedido 4 - Mesa 4 (Completado)
INSERT INTO orders (table_id, user_id, total, status, date_created) VALUES 
(4, @admin_user_id, 15.50, 'completed', DATE_SUB(NOW(), INTERVAL 30 MINUTE));

SET @order4_id = LAST_INSERT_ID();

INSERT INTO order_details (order_id, product_id, quantity, price) VALUES 
(@order4_id, 2, 3, 2.00),  -- 3 Refrescos
(@order4_id, 7, 1, 6.50),  -- 1 Nachos
(@order4_id, 3, 2, 1.50);  -- 2 Aguas

INSERT INTO payments (order_id, method, amount, date_created) VALUES 
(@order4_id, 'cash', 15.50, DATE_SUB(NOW(), INTERVAL 30 MINUTE));

-- Actualizar stock de productos (restar lo vendido)
UPDATE products SET stock = stock - 2 WHERE id = 1;  -- Cervezas
UPDATE products SET stock = stock - 3 WHERE id = 2;  -- Refrescos
UPDATE products SET stock = stock - 3 WHERE id = 3;  -- Agua
UPDATE products SET stock = stock - 2 WHERE id = 4;  -- Mojitos
UPDATE products SET stock = stock - 2 WHERE id = 5;  -- Margaritas
UPDATE products SET stock = stock - 2 WHERE id = 6;  -- Hamburguesas
UPDATE products SET stock = stock - 2 WHERE id = 7;  -- Nachos
UPDATE products SET stock = stock - 1 WHERE id = 8;  -- Alitas
UPDATE products SET stock = stock - 1 WHERE id = 10; -- Cheesecake

-- Verificar datos insertados
SELECT 'Categorías insertadas:' as Info;
SELECT * FROM categories;

SELECT 'Productos insertados:' as Info;
SELECT code, name, price, stock FROM products;

SELECT 'Pedidos del día:' as Info;
SELECT o.id, t.name as mesa, o.total, o.status, o.date_created 
FROM orders o 
JOIN tables t ON o.table_id = t.id;

SELECT 'Total de ventas del día:' as Info;
SELECT SUM(total) as total_ventas FROM orders WHERE status = 'completed';
