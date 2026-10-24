-- Chạy một lần bằng root trên volume cũ; có thể chạy lại, không xóa dữ liệu.
SET NAMES utf8mb4;
USE recipe_db;
SET @add_column = IF(
    (SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema='recipe_db' AND table_name='users' AND column_name='password_hash') = 0,
    'ALTER TABLE users ADD COLUMN password_hash VARCHAR(255) DEFAULT NULL',
    'SELECT 1'
);
PREPARE migration FROM @add_column;
EXECUTE migration;
DEALLOCATE PREPARE migration;

CREATE TABLE IF NOT EXISTS comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipe_id INT NOT NULL,
    author_name VARCHAR(100) NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX comments_recipe_time (recipe_id, created_at, id),
    FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE
);

-- Không thay đổi mật khẩu/quyền nếu username admin đã tồn tại.
INSERT INTO users (username, fullname, role, password_hash) VALUES
('admin', 'Quản trị Bếp Nhà', 'admin', '$2y$10$9H9.e2dLUOotg/uU4Inv6eY762gl75u4g6Du3Fu18EEPnkxL23RRW')
ON DUPLICATE KEY UPDATE username=users.username;
