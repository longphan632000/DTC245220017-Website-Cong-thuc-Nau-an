SET NAMES utf8mb4;
CREATE DATABASE IF NOT EXISTS recipe_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE recipe_db;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    fullname VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) DEFAULT NULL,
    role VARCHAR(30) NOT NULL DEFAULT 'Người chia sẻ'
);

CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255)
);

CREATE TABLE IF NOT EXISTS recipes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    ingredients TEXT NOT NULL,
    instructions TEXT NOT NULL,
    category_id INT NOT NULL,
    user_id INT NOT NULL,
    cooking_minutes INT NOT NULL DEFAULT 30,
    servings INT NOT NULL DEFAULT 2,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipe_id INT NOT NULL,
    author_name VARCHAR(100) NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX comments_recipe_time (recipe_id, created_at, id),
    FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE
);

INSERT INTO users (username, fullname) VALUES
('daubep', 'Đầu bếp Bếp Nhà'),
('thanhvien', 'Thành viên chia sẻ');

-- Mật khẩu lab LongPhan được lưu bằng password_hash của PHP.
INSERT INTO users (username, fullname, role, password_hash) VALUES
('admin', 'Quản trị Bếp Nhà', 'admin', '$2y$10$9H9.e2dLUOotg/uU4Inv6eY762gl75u4g6Du3Fu18EEPnkxL23RRW');

INSERT INTO categories (name, description) VALUES
('Bữa sáng', 'Món ăn đơn giản cho buổi sáng'),
('Món chính', 'Món mặn cho bữa cơm gia đình'),
('Món canh', 'Canh nóng ăn cùng cơm'),
('Rau và salad', 'Món rau, salad dễ làm'),
('Tráng miệng', 'Món ngọt sau bữa ăn'),
('Đồ uống', 'Thức uống pha tại nhà');

INSERT INTO recipes (title, ingredients, instructions, category_id, user_id, cooking_minutes, servings) VALUES
('Cơm gà gừng', '400 g thịt gà\n2 bát gạo\n1 củ gừng\n2 nhánh hành lá\n1 thìa nước mắm', '1. Vo gạo, rửa sạch gà và thái sợi gừng.\n2. Ướp gà với gừng và nước mắm trong 15 phút.\n3. Cho gạo, gà và nước vào nồi, nấu đến khi gà chín kỹ.\n4. Xới cơm, rắc hành lá và dùng nóng.', 2, 1, 45, 3),
('Bánh mì trứng', '1 ổ bánh mì\n2 quả trứng\n1 quả cà chua\nRau xà lách\nMuối, tiêu và dầu ăn', '1. Rửa rau, cắt lát cà chua.\n2. Đánh trứng với muối tiêu, chiên đến chín.\n3. Làm ấm bánh mì, kẹp trứng và rau vào giữa.', 1, 1, 15, 1),
('Canh bí đỏ đậu phụ', '300 g bí đỏ\n200 g đậu phụ\n800 ml nước\nHành lá\nMuối và hạt nêm', '1. Gọt bí, bỏ hạt và cắt miếng.\n2. Đun nước, cho bí vào nấu khoảng 15 phút.\n3. Thêm đậu phụ, nêm vừa ăn và nấu thêm 3 phút.\n4. Tắt bếp, rắc hành lá.', 3, 2, 25, 3),
('Salad bơ cà chua', '1 quả bơ\n150 g cà chua bi\n100 g xà lách\n1 thìa dầu ô liu\n1 thìa nước chanh', '1. Rửa và để ráo rau.\n2. Thái bơ, cắt đôi cà chua.\n3. Pha dầu ô liu với chanh, muối và tiêu.\n4. Trộn nhẹ các nguyên liệu và dùng ngay.', 4, 2, 15, 2),
('Sữa chua trái cây', '2 hộp sữa chua\n1 quả chuối\n100 g dâu tây\n1 thìa mật ong', '1. Rửa và cắt nhỏ trái cây.\n2. Cho sữa chua vào bát, thêm trái cây.\n3. Rưới mật ong trước khi dùng.', 5, 1, 10, 2),
('Trà chanh bạc hà', '2 túi trà\n400 ml nước nóng\n1 quả chanh\n2 thìa mật ong\n5 lá bạc hà', '1. Ủ trà theo hướng dẫn rồi để nguội bớt.\n2. Khuấy mật ong vào trà ấm.\n3. Thêm nước chanh khi trà nguội.\n4. Rót vào ly với đá và bạc hà.', 6, 2, 15, 2);

-- Chương 6: thu hồi quyền ALL do image MySQL cấp tự động, rồi cấp lại 4 quyền dữ liệu.
REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'recipe_user'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON recipe_db.* TO 'recipe_user'@'%';

CREATE USER IF NOT EXISTS 'report_user'@'%' IDENTIFIED BY 'LongPhan';
GRANT SELECT ON recipe_db.* TO 'report_user'@'%';
FLUSH PRIVILEGES;

-- Tài khoản MySQL exporter theo mẫu, chỉ dùng để đọc metrics.
CREATE USER IF NOT EXISTS 'exporter_user'@'%' IDENTIFIED BY 'LongPhan' WITH MAX_USER_CONNECTIONS 3;
GRANT PROCESS, REPLICATION CLIENT, SELECT ON *.* TO 'exporter_user'@'%';
FLUSH PRIVILEGES;
