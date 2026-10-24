# Đề 29 - Website công thức nấu ăn

Triển khai theo cấu trúc dự án mẫu [DTC245220024_De7-Blog-CMS](https://github.com/Khanhslee/DTC245220024_De7-Blog-CMS), chuyển nội dung Blog/CMS sang công thức nấu ăn. Mã PHP nằm trong `app/src/index.php`; Nginx dùng file cấu hình trực tiếp.

## Khởi động

```bash
cp .env.example .env
mkdir -p nginx/certs
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout nginx/certs/nginx.key -out nginx/certs/nginx.crt \
  -subj "/C=VN/ST=ThaiNguyen/L=ThaiNguyen/O=ICTU/OU=ATTT/CN=localhost" \
  -addext "subjectAltName=DNS:localhost,IP:127.0.0.1"
docker compose up -d --build
docker compose ps
```

Website: https://localhost. phpMyAdmin: http://localhost:8082. Mật khẩu lab dùng chung `LongPhan`. MySQL có database `recipe_db`, tài khoản ứng dụng `recipe_user` và tài khoản chỉ đọc `report_user`.

Chứng chỉ tự ký dùng cho bài thực hành; trình duyệt có thể cảnh báo chưa tin cậy. Kiểm tra HTTPS bằng chứng chỉ vừa tạo:

```bash
curl --cacert nginx/certs/nginx.crt -I https://localhost
docker compose exec recipe-app id
docker compose exec nginx nginx -t
```

Sử dụng Compose, Dockerfile, volume/network theo chương 2–3; Git theo chương 4; reverse proxy theo chương 5; MySQL/phpMyAdmin/GRANT/REVOKE theo chương 6. Mã ứng dụng dùng PHP/PDO một file theo mẫu, thay bảng posts bằng recipes có nguyên liệu và cách làm. Có 6 món mẫu, 6 danh mục và 2 người chia sẻ. Đây là website demo CRUD giống mẫu; tên người chia sẻ là dữ liệu, không phải tài khoản đăng nhập website.

File `.env` và private key không được commit. Thông tin sinh viên sẽ tự bổ sung. Chưa làm file báo cáo.
