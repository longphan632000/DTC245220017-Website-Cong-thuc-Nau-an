#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
umask 077
if [ -e .env ] || [ -e TAI_KHOAN_LOCAL.txt ]; then
    echo 'Đã có .env hoặc TAI_KHOAN_LOCAL.txt; không ghi đè thông tin đang dùng.' >&2
    exit 1
fi
command -v openssl >/dev/null
command -v docker >/dev/null
new_password() {
    local random_part
    random_part=$(openssl rand -hex 16) || return 1
    [[ "$random_part" =~ ^[0-9a-f]{32}$ ]] || return 1
    printf 'D29!aA7%s' "$random_part"
}
MYSQL_ROOT_PASSWORD=$(new_password)
MYSQL_PASSWORD=$(new_password)
REPORT_PASSWORD=$(new_password)
EXPORTER_PASSWORD=$(new_password)
GRAFANA_ADMIN_PASSWORD=$(new_password)
WEB_ADMIN_PASSWORD=$(new_password)
export WEB_ADMIN_PASSWORD
# Dùng PHP trong Docker để tạo bcrypt, không yêu cầu cài PHP trên host.
WEB_ADMIN_PASSWORD_HASH=$(docker run --rm --network none --user www-data \
    --env WEB_ADMIN_PASSWORD php:8.3-apache \
    php -r 'echo password_hash(getenv("WEB_ADMIN_PASSWORD"), PASSWORD_DEFAULT);')
[[ "$WEB_ADMIN_PASSWORD_HASH" == '$2y$'* ]]
cat > .env <<EOF
MYSQL_ROOT_PASSWORD=$MYSQL_ROOT_PASSWORD
MYSQL_DATABASE=recipe_db
MYSQL_USER=recipe_user
MYSQL_PASSWORD=$MYSQL_PASSWORD
REPORT_PASSWORD=$REPORT_PASSWORD
EXPORTER_USER=exporter_user
EXPORTER_PASSWORD=$EXPORTER_PASSWORD
GRAFANA_ADMIN_PASSWORD=$GRAFANA_ADMIN_PASSWORD
WEB_ADMIN_PASSWORD=$WEB_ADMIN_PASSWORD
WEB_ADMIN_PASSWORD_HASH='$WEB_ADMIN_PASSWORD_HASH'
EOF
cat > TAI_KHOAN_LOCAL.txt <<EOF
TÀI KHOẢN RIÊNG ĐỀ 29 - KHÔNG CHIA SẺ / KHÔNG ĐƯA LÊN GITHUB
Website: https://localhost
Admin website: https://localhost/admin.php
  Tên đăng nhập: admin
  Mật khẩu: $WEB_ADMIN_PASSWORD
Grafana: http://localhost:3000
  Tên đăng nhập: admin
  Mật khẩu: $GRAFANA_ADMIN_PASSWORD
phpMyAdmin: http://localhost:8082
  root / $MYSQL_ROOT_PASSWORD (quản trị DB)
  recipe_user / $MYSQL_PASSWORD (chỉ thao tác dữ liệu)
  report_user / $REPORT_PASSWORD (chỉ đọc)
MySQL exporter: exporter_user / $EXPORTER_PASSWORD
Các mật khẩu khác nhau, sinh ngẫu nhiên. File này chỉ chủ sở hữu được đọc/ghi.
EOF
chmod 600 .env TAI_KHOAN_LOCAL.txt
echo 'Đã tạo mật khẩu riêng trong .env và TAI_KHOAN_LOCAL.txt (quyền 600).'
echo 'Tiếp tục sinh chứng chỉ rồi chạy docker compose up -d --build theo README.'
