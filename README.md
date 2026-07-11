<p align="center">
  <a href="https://laravel.com" target="_blank">
    <img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="320" alt="Laravel Logo">
  </a>
</p>

<h1 align="center">icegozi_workflow</h1>

<p align="center">Ứng dụng quản lý công việc theo mô hình bảng Kanban (kiểu Trello) — Laravel 10 + PHP 8.2.</p>

---

## 1. Giới thiệu nghiệp vụ

**icegozi_workflow** là công cụ quản lý công việc nhóm theo phương pháp **Kanban**. Mỗi nhóm/dự án được tổ chức thành các **Bảng (Board)**; trong bảng có nhiều **Cột (Column)** thể hiện các trạng thái công việc (ví dụ: *Cần làm → Đang làm → Hoàn thành*); mỗi cột chứa các **Thẻ công việc (Task)** có thể kéo-thả qua lại giữa các cột.

Mô hình dữ liệu cốt lõi:

```
User (Người dùng)
  └── Board (Bảng)              ← người tạo là "chủ sở hữu"
        ├── Column (Cột)
        │     └── Task (Thẻ công việc)
        │           ├── Assignee     (Người phụ trách)
        │           ├── Comment      (Bình luận)
        │           ├── Attachment   (Tệp đính kèm)
        │           ├── Checklist    (Danh sách việc con)
        │           └── TaskHistory  (Nhật ký thay đổi)
        └── BoardInvitation (Lời mời thành viên)
```

**Phân quyền theo bảng (RBAC cấp Board)** — một người dùng có thể được mời vào bảng của người khác với 1 trong 3 vai trò:

| Vai trò | Quyền hạn |
|---|---|
| `board_viewer` | Chỉ **xem** nội dung bảng/thẻ |
| `board_editor` | Xem + **chỉnh sửa** thẻ, cột, bình luận, đính kèm, checklist |
| `board_member_manager` | Như editor + **mời / quản lý thành viên** trong bảng |

> **Chủ sở hữu bảng** (người tạo) luôn có toàn quyền. Ngoài ra hệ thống có vai trò **Quản trị viên (admin)** ở cấp toàn ứng dụng để quản lý người dùng.

---

## 2. Tính năng chính

### Xác thực & tài khoản
- Đăng ký / Đăng nhập / Đăng xuất (có **giới hạn tần suất** chống dò mật khẩu).
- Mật khẩu yêu cầu tối thiểu 8 ký tự, có chữ hoa/thường + số.
- Tài khoản bị khoá (`status != active`) bị chặn truy cập ngay ở mọi request.
- Điều hướng theo vai trò: admin → trang quản trị, người dùng thường → bảng làm việc.

### Bảng & Kanban
- Tạo / sửa / xoá **Bảng**.
- Tạo / đổi tên / xoá / **kéo-thả sắp xếp Cột**.
- Tạo / sửa / xoá **Thẻ công việc**; **kéo-thả** thẻ trong cột và giữa các cột.
- Thuộc tính thẻ: tiêu đề, mô tả, **độ ưu tiên** (low/normal/high/urgent), **hạn hoàn thành (due date)**.
- Trang **Tổng quan bảng** (Overview): thống kê số cột, số thẻ, danh sách thành viên.

### Cộng tác trên từng thẻ
- **Người phụ trách (Assignee)**: gán/bỏ thành viên của bảng cho thẻ.
- **Bình luận**: thêm/xoá (tác giả hoặc người quản lý mới được xoá).
- **Tệp đính kèm**: tải lên (giới hạn loại tệp an toàn, tối đa 10MB), tải xuống, xoá.
- **Checklist**: thêm/sửa/xoá/đánh dấu hoàn thành + kéo-thả sắp xếp.
- **Nhật ký hoạt động (TaskHistory)**: tự động ghi lại mọi thay đổi trên thẻ.

### Quản lý thành viên bảng
- **Mời thành viên qua email** kèm vai trò; lời mời gửi bằng **liên kết có chữ ký số** (signed URL), hết hạn sau 7 ngày.
- Chấp nhận lời mời (kiểm tra đúng email + người mời vẫn còn quyền).
- Cập nhật vai trò / gỡ thành viên / huỷ lời mời đang chờ.

### Quản trị (Admin)
- Bảng điều khiển quản trị + **biểu đồ đăng ký người dùng** theo thời gian.
- Quản lý người dùng: danh sách, tạo, xem, cập nhật, xoá.

---

## 3. Công nghệ

| Lớp | Công nghệ |
|---|---|
| Backend | Laravel 10, PHP 8.2 |
| CSDL | MySQL 8 |
| Frontend | Inertia.js + Vue 3, Bootstrap 5, Vite |
| Hạ tầng | Docker (Nginx + PHP-FPM + Supervisor trong 1 image), Docker Compose |
| Mail (dev) | Mailpit |

---

## 4. Chạy ứng dụng bằng Docker + Makefile

Đây là cách **được khuyến nghị** — không cần cài PHP/Composer/Node trên máy. Yêu cầu: **Docker** + **Docker Compose**.

### Khởi chạy lần đầu

```bash
make init      # tạo .env (nếu chưa có) + build image + khởi động + sinh APP_KEY
```

Lệnh trên dựng 3 service: **app**, **db** (MySQL), **mailpit**. Entrypoint tự chạy migration mỗi lần khởi động.

Sau khi chạy xong, mở:

| Dịch vụ | Địa chỉ | Ghi chú |
|---|---|---|
| Ứng dụng | http://localhost:8888 | Cổng đặt bởi `APP_PORT` trong `.env` |
| MySQL | `127.0.0.1:2204` | Cổng đặt bởi `DB_PORT` |
| Mailpit (xem email) | http://localhost:8025 | Giao diện web đọc email mời thành viên |

> **Lưu ý về cổng mail:** `MAIL_PORT=1025` là cổng **SMTP** để app *gửi* email (không mở bằng trình duyệt). Còn `http://localhost:8025` là **giao diện web** để *xem* email.

### Nạp dữ liệu mẫu (tuỳ chọn)

```bash
make seed      # đổ dữ liệu mẫu: người dùng, bảng, quyền
# hoặc làm mới sạch:
make fresh     # ⚠ XOÁ toàn bộ DB rồi migrate lại + seed
```

> Các **quyền** (`board_viewer`, `board_editor`, `board_member_manager`) được seed tự động qua migration nên luôn có sẵn kể cả khi không chạy `make seed`.

#### Tài khoản demo (chỉ có khi đã `make seed`/`make fresh`)

| Email | Mật khẩu | Vai trò |
|---|---|---|
| `a@example.com` | `password123` | Người dùng thường |
| `b@example.com` | `password123` | **Quản trị viên** |

### Các lệnh Make thường dùng

```bash
make help            # liệt kê toàn bộ lệnh (nguồn tài liệu chính xác nhất)

# Vòng đời
make up / make down  # bật / tắt toàn bộ service
make restart         # khởi động lại
make ps              # trạng thái container
make logs            # xem log app (Ctrl-C để thoát)

# Truy cập
make shell           # vào bash trong container app
make db-shell        # mở mysql client
make tinker          # REPL Laravel

# CSDL
make migrate         # chạy migration
make migrate-status  # trạng thái migration
make rollback        # lùi batch migration gần nhất
make seed            # đổ dữ liệu mẫu

# Tiện ích Laravel (truyền lệnh tự do)
make artisan c="route:list"
make composer c="require vendor/package"

# Chất lượng mã
make test            # PHPUnit
make lint            # kiểm tra code style (Pint)
make lint-fix        # tự sửa code style

# Tài nguyên tĩnh (chạy trước khi deploy nếu sửa JS/CSS)
make minifyjs        # minify public/assets/js/*.js -> *.min.js
make minifycss       # minify public/assets/css/*.css -> *.min.css
make minify          # cả hai

# Cache (production)
make optimize        # cache config/route/view
make clear           # xoá toàn bộ cache

# Dọn dẹp
make clean           # ⚠ gỡ container + volume (mất dữ liệu DB)
make prune           # dọn image/cache treo của Docker
```

---

## 5. Chạy không dùng Docker (tuỳ chọn)

Yêu cầu cài sẵn: PHP 8.2 (kèm extension `pdo_mysql, mbstring, bcmath, gd, zip, exif, pcntl`), Composer, Node 20, MySQL 8.

```bash
composer install
cp .env.example .env            # cấu hình DB_* trỏ tới MySQL của bạn
php artisan key:generate
php artisan migrate --seed
npm install && npm run dev      # biên dịch asset (Vite)
php artisan serve               # http://127.0.0.1:8000
```

---

## 6. Triển khai (Deploy)

Ứng dụng đóng gói thành **một image Docker** (Nginx + PHP-FPM + Supervisor). Quy trình triển khai:

### 6.1. Chuẩn bị biến môi trường

Đặt các biến sau (qua `.env` hoặc biến môi trường của hệ thống/CI). Compose đã hỗ trợ override toàn bộ:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...            # sinh bằng: php artisan key:generate --show
APP_URL=https://your-domain   # PHẢI đúng domain thật (ảnh hưởng link trong email mời)
APP_PORT=8080                 # cổng host map ra ngoài

# CSDL (đặt mật khẩu mạnh)
DB_DATABASE=...
DB_USERNAME=...               # KHÔNG dùng 'root' cho MYSQL_USER
DB_PASSWORD=...
DB_ROOT_PASSWORD=...

# Mail thật cho production (mailpit chỉ dành cho dev)
MAIL_MAILER=smtp
MAIL_HOST=smtp.your-provider.com
MAIL_PORT=587

RUN_MIGRATIONS=true           # entrypoint tự migrate khi khởi động
RUN_SEEDERS=false             # KHÔNG seed dữ liệu mẫu ở production
```

### 6.2. Build asset tĩnh trước khi build image

Nếu vừa sửa JS/CSS, sinh lại các bản minify để commit/đóng gói:

```bash
make minify
```

### 6.3. Build & chạy

```bash
docker compose build           # hoặc: make build
docker compose up -d           # hoặc: make up
```

Khi container khởi động, `docker/entrypoint.sh` tự động:
1. Sinh `APP_KEY` nếu thiếu.
2. Chờ DB sẵn sàng rồi chạy `php artisan migrate --force` (gồm cả seed **quyền bắt buộc**).
3. `config:cache` + `route:cache` + `view:cache` để tối ưu hiệu năng.

### 6.4. Lưu ý vận hành

- **Mail production:** thay mailpit bằng SMTP thật (xem `MAIL_*` ở trên). Có thể bỏ service `mailpit` khỏi compose khi lên production.
- **Bảo mật:** image đã cấu hình chặn thực thi PHP trong thư mục `/storage` (chống upload mã độc) và chỉ chấp nhận các loại tệp đính kèm an toàn.
- **Sau khi đổi `.env`/route trong container đang chạy:** chạy `make clear` (hoặc rebuild) để xoá cache cũ.
- **Dữ liệu** (DB + tệp người dùng tải lên) được lưu trong Docker volume (`db_data`, `storage_data`) nên không mất khi rebuild image. `make clean` sẽ **xoá** các volume này.

---

## 7. Tài liệu thêm

- `make help` — danh sách lệnh đầy đủ và cập nhật nhất.
- `CLAUDE.md` — ghi chú kiến trúc dành cho người phát triển (mô hình phân quyền, cấu trúc frontend, các lưu ý khi triển khai).
