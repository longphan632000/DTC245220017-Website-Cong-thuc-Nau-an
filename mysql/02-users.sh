#!/bin/bash
set -euo pipefail
: "${MYSQL_ROOT_PASSWORD:?}"
: "${MYSQL_PASSWORD:?}"
: "${REPORT_PASSWORD:?}"
: "${EXPORTER_PASSWORD:?}"
: "${WEB_ADMIN_PASSWORD_HASH:?}"
# Các mật khẩu do script khởi tạo sinh có 39 ký tự, chỉ dùng bảng ký tự an toàn.
# Từ chối dữ liệu không hợp lệ thay vì ghép tùy ý giá trị vào SQL quản trị.
for secret in "$MYSQL_PASSWORD" "$REPORT_PASSWORD" "$EXPORTER_PASSWORD"; do
    [[ "$secret" =~ ^[A-Za-z0-9!@%_=+:,./?-]{16,72}$ ]] || { echo 'Mật khẩu không đúng định dạng an toàn.' >&2; exit 1; }
done
[[ "$WEB_ADMIN_PASSWORD_HASH" =~ ^\$2y\$[0-9]{2}\$[A-Za-z0-9./]{53}$ ]] || { echo 'Hash admin phải là bcrypt của PHP.' >&2; exit 1; }
MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql --protocol=socket -uroot --default-character-set=utf8mb4 <<SQL
CREATE USER IF NOT EXISTS 'recipe_user'@'%' IDENTIFIED BY '$MYSQL_PASSWORD';
ALTER USER 'recipe_user'@'%' IDENTIFIED BY '$MYSQL_PASSWORD';
REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'recipe_user'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON recipe_db.* TO 'recipe_user'@'%';
CREATE USER IF NOT EXISTS 'report_user'@'%' IDENTIFIED BY '$REPORT_PASSWORD';
ALTER USER 'report_user'@'%' IDENTIFIED BY '$REPORT_PASSWORD';
REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'report_user'@'%';
GRANT SELECT ON recipe_db.* TO 'report_user'@'%';
CREATE USER IF NOT EXISTS 'exporter_user'@'%' IDENTIFIED BY '$EXPORTER_PASSWORD';
ALTER USER 'exporter_user'@'%' IDENTIFIED BY '$EXPORTER_PASSWORD' WITH MAX_USER_CONNECTIONS 3;
REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'exporter_user'@'%';
GRANT PROCESS, REPLICATION CLIENT, SELECT ON *.* TO 'exporter_user'@'%';
INSERT INTO recipe_db.users (username, fullname, role, password_hash)
VALUES ('admin', 'Quản trị Bếp Nhà', 'admin', '$WEB_ADMIN_PASSWORD_HASH')
ON DUPLICATE KEY UPDATE password_hash='$WEB_ADMIN_PASSWORD_HASH', role='admin';
SQL
echo 'Đã khởi tạo quyền DB và tài khoản admin; không in mật khẩu.'
