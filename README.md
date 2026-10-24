# Đề 29 - Website công thức nấu ăn

Triển khai theo cấu trúc dự án mẫu [DTC245220024_De7-Blog-CMS](https://github.com/Khanhslee/DTC245220024_De7-Blog-CMS), chuyển nội dung Blog/CMS sang công thức nấu ăn. Ứng dụng dùng PHP/PDO thuần; Nginx dùng file cấu hình trực tiếp. Bổ sung trang admin và bình luận theo yêu cầu, giữ nguyên mô hình Docker của bài thực hành.

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

Website người dùng: https://localhost. Trang quản trị: https://localhost/admin.php, đăng nhập **admin / LongPhan**. phpMyAdmin: http://localhost:8082. Mật khẩu lab dùng chung `LongPhan`. MySQL có database `recipe_db`, tài khoản ứng dụng `recipe_user` và tài khoản chỉ đọc `report_user`.

## Phân quyền website

| Người sử dụng | Chức năng |
| --- | --- |
| Khách tại `/` | Xem công thức, tìm món/nguyên liệu, lọc danh mục; mở một món để nhập tên và bình luận. Không cần tạo tài khoản. |
| Admin tại `/admin.php` | Đăng nhập; tạo và chia sẻ công thức, sửa/xóa món, thêm danh mục và xóa bình luận không phù hợp; đăng xuất. |

Trang công khai không có biểu mẫu quản lý công thức. PHP kiểm tra session và role trong DB trước mọi thao tác quản trị; POST giả mạo từ khách trả `403`. Admin dùng mật khẩu hash (`password_hash`/`password_verify`), đổi session ID khi đăng nhập/đăng xuất và hết phiên sau 30 phút không hoạt động. Mọi biểu mẫu POST có CSRF token. Tên/nội dung bình luận được escape khi hiển thị; câu SQL dùng tham số PDO. Tác giả món mới lấy từ admin đã đăng nhập, không lấy `user_id` client gửi.

### Cập nhật máy đã có dữ liệu từ phiên bản cũ

`init.sql` chỉ chạy trên volume trống. Với bản cũ, áp dụng file nâng cấp bằng tài khoản root trước khi build lại app; không xóa volume:

```bash
docker compose exec -T mysql sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot --default-character-set=utf8mb4' < mysql/upgrade-admin-comments.sql
docker compose up -d --build recipe-app
docker compose exec nginx nginx -s reload
```

File nâng cấp thêm cột hash, bảng `comments` và tài khoản admin nếu chưa có; không xóa công thức/danh mục hay đổi mật khẩu admin đã tồn tại. Cài mới bằng phần Khởi động ở trên đã có sẵn các thay đổi này. Hai dòng tác giả mẫu `daubep`/`thanhvien` chỉ là dữ liệu tác giả, không phải tài khoản đăng nhập.

Chứng chỉ tự ký dùng cho bài thực hành; trình duyệt có thể cảnh báo chưa tin cậy. Kiểm tra HTTPS bằng chứng chỉ vừa tạo:

```bash
curl --cacert nginx/certs/nginx.crt -I https://localhost
docker compose exec recipe-app id
docker compose exec nginx nginx -t
```

Sử dụng Compose, Dockerfile, volume/network theo chương 2–3; Git theo chương 4; reverse proxy theo chương 5; MySQL/phpMyAdmin/GRANT/REVOKE theo chương 6. Mã ứng dụng dùng PHP/PDO như mẫu, thay bảng posts bằng recipes có nguyên liệu và cách làm. Có 6 món mẫu, 6 danh mục, 2 tác giả dữ liệu và 1 tài khoản admin. Database có 4 bảng: `users`, `categories`, `recipes`, `comments`. Bình luận liên kết với công thức bằng khóa ngoại `ON DELETE CASCADE`.

File `.env` và private key không được commit. Thông tin sinh viên sẽ tự bổ sung. Báo cáo in để riêng và đang dừng theo yêu cầu.

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

Để dựng lại đúng dashboard của Đề 29: Dashboards → New → Import → Upload JSON file → chọn `grafana/dashboards/de29.json` → chọn datasource Prometheus → Import. File JSON được quản lý trong Git cùng source/config; các dashboard khác tự import trên máy được giữ lại.

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

Có thể Import `grafana/dashboards/de29-logs.json` và chọn datasource Loki để dựng lại ba panel trên máy mới. Các file JSON dùng biến datasource trong bước Import nên không phụ thuộc UID datasource của máy hiện tại. Đây là thao tác import bằng giao diện; chưa bật provisioning tự động.

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
│   └── src/
│       ├── index.php           # Khách xem và bình luận
│       ├── admin.php           # Đăng nhập và quản trị
│       ├── common.php          # PDO, session, CSRF và helper
│       └── style.css
├── mysql/
│   ├── init.sql
│   └── upgrade-admin-comments.sql
├── nginx/
│   ├── nginx.conf
│   └── certs/                 # Tự tạo chứng chỉ và key bằng OpenSSL
├── prometheus/
│   ├── .my.cnf
│   └── prometheus.yml
├── loki/loki-config.yml
├── promtail/promtail-config.yml
├── grafana/dashboards/         # JSON để import hai dashboard Đề 29
└── minh-chung/                # Ảnh và kết quả chạy thật
```

| File | Phần theo mẫu / giáo trình |
| --- | --- |
| `app/Dockerfile` | Giữ nguyên Dockerfile mẫu: PHP 8.3 + Apache, pdo_mysql, cổng 8080, COPY, chown/chmod, USER www-data. Chương 2 về Dockerfile; chương 3 về Compose. |
| `app/src/` | PHP/PDO và HTML/CSS như mẫu; `index.php` xem/bình luận, `admin.php` quản lý công thức, `common.php` dùng chung kết nối và kiểm tra phiên. Tách trang để đáp ứng yêu cầu admin/khách; không thêm framework. |
| `mysql/init.sql` | users/categories/recipes tương ứng mô hình mẫu; thêm comments và hash tài khoản admin. Khóa ngoại, INSERT, CREATE USER và GRANT/REVOKE theo cách làm chương 6. |
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
| Website người dùng | https://localhost | Xem và bình luận, không cần đăng nhập |
| Trang admin | https://localhost/admin.php | admin / LongPhan |
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
6. **Quyền ở ứng dụng:** chỉ admin đã xác thực được thay đổi công thức/danh mục và xóa bình luận; CSRF, prepared statement và escape HTML cho các biểu mẫu.

Mật khẩu `LongPhan` được giữ theo yêu cầu riêng của bạn, không tính là mật khẩu mạnh. Bốn biện pháp chính đối chiếu rubric là non-root ứng dụng, phân đoạn mạng, hạn chế quyền DB và HTTPS/security headers. Các dịch vụ quản trị vẫn publish theo mô hình lab; chưa coi cấu hình này là bản triển khai production.

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

1. Trang người dùng: tìm `gừng`, lọc Món canh, mở một món và gửi bình luận. Trang admin: đăng nhập `admin / LongPhan`, tạo/sửa/xóa công thức; kiểm tra món mới xuất hiện trên trang người dùng và xóa bình luận thử. Khách không được quản lý công thức.
2. phpMyAdmin: mở recipe_db, xem recipes/categories/users/comments, đối chiếu dữ liệu từ website; kiểm tra quyền DB. Không chụp công khai hash mật khẩu.
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

## Đối chiếu tiêu chí sau khi thêm admin

| Tiêu chí | Kết quả kiểm tra |
| --- | --- |
| GitHub và README | Repository public, tên repo có MSSV, giữ ba mốc commit ban đầu và các commit bổ sung; source, SQL, Nginx, monitoring, log và JSON dashboard được lưu trong Git. |
| Website và MySQL | Admin đăng nhập, tạo/sửa/xóa công thức; khách xem/bình luận; phpMyAdmin hoạt động. Đã kiểm tra nâng cấp DB cũ và SQL khởi tạo mới. |
| Nginx | HTTP 301 sang HTTPS; HTTPS 200 với CA lab; security headers và nginx -t hợp lệ. |
| Prometheus và Grafana | Năm job UP; mysql_up=1, nginx_up=1; dashboard có CPU/RAM container, request Nginx, kết nối/truy vấn MySQL. |
| Loki và Promtail | Thu log project d29; bốn query LogQL ở trên trả dữ liệu; có dashboard Logs. |
| Hardening | UID 33 cho app; 3 mạng; DB không publish cổng; quyền app chỉ SELECT/INSERT/UPDATE/DELETE; HTTPS/headers và kiểm tra quyền admin phía PHP. |
| Tổng thể và minh chứng | 12 service chạy bằng Compose, ảnh giao diện và kết quả lệnh trong minh-chung; có kịch bản demo. Việc trình bày và giải thích khi chấm cần sinh viên thực hiện. |
| Báo cáo in tối thiểu 10 trang và bìa | Chưa hoàn tất; đang dừng theo yêu cầu của bạn. Báo cáo là tài liệu riêng mang đi in, không đưa lên Git. |

Ngoại lệ giữ theo yêu cầu riêng: tài khoản GitHub hiện là `longphan632000`, chưa đổi tên đăng nhập thành MSSV; mật khẩu lab vẫn là `LongPhan`. Tên repository đã có `DTC245220017`. Không khẳng định hoàn thành yêu cầu đổi tên tài khoản hay mật khẩu mạnh.

Kết quả kiểm tra mới: `minh-chung/kiem-tra-admin.txt`. Ảnh chụp website bằng HTTP nội bộ container phục vụ kiểm tra giao diện; đường HTTPS qua Nginx được xác minh riêng bằng CA lab. Lịch sử kiểm tra trước khi bổ sung admin vẫn nằm trong `minh-chung/kiem-tra.txt`.
