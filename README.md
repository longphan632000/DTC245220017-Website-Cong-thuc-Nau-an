# Đề 29 - Website công thức nấu ăn

Triển khai theo cấu trúc dự án mẫu [DTC245220024_De7-Blog-CMS](https://github.com/Khanhslee/DTC245220024_De7-Blog-CMS), chuyển nội dung Blog/CMS sang công thức nấu ăn. Mã PHP nằm trong `app/src/index.php`; Nginx dùng file cấu hình trực tiếp.

MSSV: `DTC245220017`. Repository: [DTC245220017-Website-Cong-thuc-Nau-an](https://github.com/longphan632000/DTC245220017-Website-Cong-thuc-Nau-an).

## Khởi động

```bash
git clone https://github.com/longphan632000/DTC245220017-Website-Cong-thuc-Nau-an.git
cd DTC245220017-Website-Cong-thuc-Nau-an
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

## Giám sát - chương 7, commit 2

```bash
docker compose up -d --build
docker compose ps
```

Prometheus: http://localhost:9090/targets. Grafana: http://localhost:3000, đăng nhập `admin` / `LongPhan`. Vào Connections → Data sources → Add data source → Prometheus, URL `http://prometheus:9090`, Save & test.

Tạo dashboard bằng Dashboards → New → Add visualization, chọn datasource Prometheus và nhập từng query:

```promql
nginx_up
mysql_up
rate(nginx_http_requests_total[1m])
mysql_global_status_threads_connected
rate(mysql_global_status_queries[1m])
rate(container_cpu_usage_seconds_total{container_label_com_docker_compose_project="d29"}[5m]) * 100
container_memory_usage_bytes{container_label_com_docker_compose_project="d29"}
```

Hai query `_up` dùng Stat; những query còn lại dùng Time series. Đặt tên dashboard `Đề 29 - Giám sát hệ thống`. Cũng có thể Import các dashboard như mẫu: Node 1860, Docker 14282, Nginx 12708, MySQL 7362; chọn datasource Prometheus khi import.

`prometheus/.my.cnf` dùng đúng user/password đã tạo trong MySQL. Trong bài lab mật khẩu ở file này là `LongPhan` theo yêu cầu; khi đổi mật khẩu, đổi cả file và tài khoản DB. MySQL exporter ở hai mạng backend/monitoring như mẫu; Nginx exporter đọc `/stub_status`. Prometheus, cAdvisor và node-exporter thu metrics như phần thực hành chương 7.

## Log tập trung - chương 8, commit 3

```bash
docker compose up -d
docker compose logs loki
docker compose logs promtail
```

Grafana → Connections → Data sources → Add data source → Loki. URL `http://loki:3100`, Save & test. Sau đó Explore → chọn Loki → Code → nhập query → Run query. Chọn Last 1 hour hoặc Last 24 hours nếu muốn xem log khởi động.

Log của ứng dụng:

```logql
{container="d29-recipe-app"}
```

Lọc các yêu cầu GET hoặc POST như mẫu:

```logql
{container="d29-recipe-app"} |~ "GET|POST"
```

Log MySQL:

```logql
{container="d29-mysql"}
```

Lọc request 404 của Nginx:

```logql
{container="d29-nginx"} |= "404"
```

Tạo log 404 bằng cách mở `https://localhost/?id=999999`, hoặc chạy:

```bash
curl --cacert nginx/certs/nginx.crt 'https://localhost/?id=999999'
```

Dashboard `Đề 29 - Log tập trung` có ba panel Logs: ứng dụng, MySQL và Nginx 404. Dựng bằng Add visualization → Loki → nhập query → chọn Logs → Save. Cả hai dashboard và datasource đã được cấu hình trên máy hiện tại; dữ liệu lưu trong volume Grafana.

## Cấu trúc bám theo repository mẫu

Mẫu được đọc ở commit `68978cd2eb0872ea14261810d7c64299ef8ffcec` của [Khanhslee/DTC245220024_De7-Blog-CMS](https://github.com/Khanhslee/DTC245220024_De7-Blog-CMS/tree/68978cd2eb0872ea14261810d7c64299ef8ffcec). Các file cấu hình chung được giữ theo mẫu; nội dung ứng dụng/SQL chuyển sang Đề 29.

```text
d29/
├── .env.example
├── .gitignore
├── docker-compose.yml
├── README.md
├── app/
│   ├── .dockerignore
│   ├── Dockerfile
│   └── src/index.php
├── mysql/init.sql
├── nginx/
│   ├── nginx.conf
│   └── certs/                 # Tự tạo chứng chỉ và key bằng OpenSSL
├── prometheus/
│   ├── .my.cnf
│   └── prometheus.yml
├── loki/loki-config.yml
├── promtail/promtail-config.yml
└── minh-chung/                # Ảnh và kết quả chạy thật
```

| File | Phần theo mẫu / giáo trình |
| --- | --- |
| `app/Dockerfile` | Giữ nguyên Dockerfile mẫu: PHP 8.3 + Apache, pdo_mysql, cổng 8080, COPY, chown/chmod, USER www-data. Chương 2 về Dockerfile; chương 3 về Compose. |
| `app/src/index.php` | Cùng cách viết PHP/PDO một file và HTML/CSS như mẫu; đổi bài viết thành công thức có nguyên liệu/cách làm. Thêm tìm kiếm và sửa/xóa để demo quản lý công thức. Prepared statement, escape HTML; thêm token CSRF ngắn cho biểu mẫu. |
| `mysql/init.sql` | Ba bảng users/categories/recipes tương ứng users/categories/posts trong mẫu; khóa ngoại, INSERT dữ liệu mẫu, CREATE USER và GRANT/REVOKE theo chương 6. |
| `nginx/nginx.conf` | Giữ cấu hình mẫu, chỉ đổi upstream `blog-app` thành `recipe-app`: HTTP chuyển HTTPS, chứng chỉ tự ký, proxy_pass/proxy_set_header, stub_status và security headers. Reverse proxy theo chương 5. |
| `prometheus/prometheus.yml` | Giữ nguyên file mẫu: 15 giây, 5 job, static_configs, tên service Docker. Giám sát theo chương 7. |
| `prometheus/.my.cnf` | Giữ mẫu exporter dùng file client MySQL; đổi mật khẩu thành LongPhan. |
| `loki/loki-config.yml` | Giữ nguyên mẫu: Loki 2.9.8, filesystem, boltdb-shipper và schema v12 như chương 8 trang 7–8. |
| `promtail/promtail-config.yml` | Giữ discovery/relabel/pipeline của mẫu theo chương 8; lọc thêm project d29 để chỉ lấy log của bài này. |
| `docker-compose.yml` | Giữ 12 thành phần và 3 network frontend/backend/monitoring như mẫu; chia triển khai thành ba commit. |

Các thay đổi vận hành cần thiết:

- Thêm tiền tố `d29-` cho container_name để tránh tên trùng trên máy. Service name vẫn là các tên dùng trong URL nội bộ.
- Cổng host dùng đúng mẫu: Grafana `3000`, Loki `3100`, cAdvisor `8081`, phpMyAdmin `8082`, Prometheus `9090`, Nginx `80/443`. Các container thuộc dự án khác trên máy đã được dừng và tắt tự khởi động lại để dành cổng cho d29; dữ liệu của chúng vẫn được giữ nguyên.
- Cấp mật khẩu Grafana qua biến môi trường để dùng `LongPhan`. Các mật khẩu còn lại cũng dùng `LongPhan` theo yêu cầu. Không tính mật khẩu này là biện pháp mật khẩu mạnh.
- Sửa phần phân quyền: image MySQL tự cấp ALL cho MYSQL_USER. Chỉ thêm GRANT như mẫu sẽ không thu hồi ALL, nên dùng `REVOKE ALL PRIVILEGES, GRANT OPTION FROM ...` trước rồi cấp lại SELECT/INSERT/UPDATE/DELETE. Dùng REVOKE toàn bộ trên riêng tài khoản app để xử lý cả quyền database đã được image escape dấu gạch dưới.
- Tạo chứng chỉ mới, thêm subjectAltName cho localhost; không dùng private key đã xuất hiện trong repository mẫu. Sửa dòng `.gitignore` bị nối key và đường dẫn exporter trong mẫu thành các dòng đúng.
- cAdvisor thêm `--housekeeping_interval=30s` vì máy đang chạy nhiều stack; giữ nguyên bốn mount như mẫu. Panel CPU dùng cửa sổ 5 phút để đủ mẫu khi chu kỳ tự giãn.

## Truy cập dịch vụ

| Dịch vụ | Địa chỉ | Đăng nhập |
| --- | --- | --- |
| Website | https://localhost | Biểu mẫu demo chia sẻ công thức như mẫu |
| phpMyAdmin | http://localhost:8082 | root / LongPhan để quản trị; recipe_user / LongPhan để thao tác dữ liệu; report_user / LongPhan để chỉ đọc |
| Grafana | http://localhost:3000 | admin / LongPhan |
| Prometheus | http://localhost:9090/targets | Không có tài khoản trong cấu hình lab mẫu |
| cAdvisor | http://localhost:8081 | Không có tài khoản trong cấu hình lab mẫu |
| Loki readiness | http://localhost:3100/ready | API; xem log trong Grafana |

Ứng dụng và MySQL không publish cổng ra host. Trình duyệt truy cập website qua Nginx. Các cổng trên giữ cách publish của mẫu, dùng trong mạng lab.

## Hardening và cách kiểm tra

1. **Non-root:** `docker compose exec recipe-app id` trả UID 33 (`www-data`).
2. **Tách mạng:** MySQL chỉ ở backend-net; Nginx ở frontend-net và monitoring-net; app nối frontend với backend; exporter DB nối backend với monitoring.
3. **Quyền DB tối thiểu:** recipe_user chỉ SELECT/INSERT/UPDATE/DELETE; report_user chỉ SELECT; exporter_user dùng cho metrics. App không dùng root.
4. **Nginx:** HTTP → HTTPS, TLS 1.2/1.3, ẩn phiên bản và các security headers theo mẫu.
5. **Thông tin nhạy cảm:** `.env` và key tự ký được ignore; `.env.example` và `.my.cnf` chỉ chứa mật khẩu lab đã yêu cầu.

Các lệnh kiểm tra theo cách thao tác trong mẫu và giáo trình:

```bash
docker compose ps
docker compose exec recipe-app id
docker compose exec nginx nginx -t
curl --cacert nginx/certs/nginx.crt -I https://localhost
docker network inspect d29_frontend-net
docker network inspect d29_backend-net
docker network inspect d29_monitoring-net
docker compose exec recipe-app getent hosts mysql
docker compose exec mysql mysql -uroot -p
```

Nhập `LongPhan` khi MySQL hỏi. Trong MySQL hoặc phpMyAdmin → SQL:

```sql
SHOW GRANTS FOR 'recipe_user'@'%';
SHOW GRANTS FOR 'report_user'@'%';
SHOW GRANTS FOR 'exporter_user'@'%';
SELECT title, ingredients, instructions FROM recipe_db.recipes;
```

Đăng nhập phpMyAdmin bằng report_user; thử `DELETE FROM recipe_db.recipes WHERE id=-1;` phải bị từ chối. Điều kiện id=-1 tránh tác động dữ liệu khi minh họa kiểm tra quyền.

## Demo và quản lý dữ liệu

1. Website: tìm `gừng`, lọc Món canh, xem chi tiết món; tạo một công thức, sửa rồi xóa; thêm danh mục nếu cần.
2. phpMyAdmin: mở recipe_db, xem recipes/categories/users, đối chiếu dữ liệu từ website; kiểm tra quyền DB.
3. Nginx: kiểm tra HTTP 301, HTTPS 200 và response headers bằng curl ở trên.
4. Prometheus: 5 target UP. Grafana: CPU/RAM container, Nginx requests và MySQL connections/queries.
5. Grafana Explore: chạy ít nhất ba câu LogQL đã nêu; tạo 404 rồi lọc log Nginx.
6. Chạy `id`, xem network và SHOW GRANTS để giải thích hardening. Xem `git log --oneline --reverse` để thấy ba mốc triển khai.

`init.sql` chỉ chạy trên MySQL volume trống. Nếu sửa nội dung SQL sau khi đã chạy, dùng tab SQL/Import trong phpMyAdmin để áp dụng có chọn lọc. Có thể Export database bằng phpMyAdmin để sao lưu.

```bash
# Dừng, giữ dữ liệu
docker compose down
# Chạy lại
docker compose up -d
```

`docker compose down -v` xóa các volume dữ liệu; chỉ dùng khi chủ động muốn làm lại dữ liệu từ đầu.

## Git/GitHub - chương 4

Repository public trên tài khoản `longphan632000`, nhánh `main`, có ba mốc triển khai: ứng dụng/DB/Nginx; giám sát; log/hardening/minh chứng. Các thay đổi sau đó được ghi bằng commit bổ sung. Các commit dùng Git identity của bạn. Thông tin cá nhân còn lại sẽ do bạn bổ sung.

```bash
git status
git log --oneline --reverse
git add .
git commit -m "Bo sung thong tin sinh vien"
git push origin main
```

Remote `origin` trỏ tới repository ở đầu README. Không có file báo cáo trong bản này.
