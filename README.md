# Icegozi Workflow

Icegozi Workflow là ứng dụng quản lý công việc theo phương pháp Kanban, phù hợp cho nhóm nhỏ và dự án nội bộ. Mỗi dự án được tổ chức thành một bảng, các công việc được đặt trong cột và có thể kéo-thả để phản ánh tiến độ thực tế.

## Giới thiệu

Ứng dụng hỗ trợ quản lý công việc từ lúc tạo bảng đến khi hoàn thành: phân công người thực hiện, đặt hạn, bình luận, checklist, tệp đính kèm, nhãn và lịch sử thay đổi. Giao diện được xây dựng responsive để sử dụng được trên máy tính, tablet và điện thoại.

Mô hình dữ liệu chính:

```text
Người dùng
└── Bảng
    ├── Cột
    │   └── Công việc
    │       ├── Người phụ trách
    │       ├── Bình luận
    │       ├── Checklist
    │       ├── Nhãn
    │       ├── Tệp đính kèm
    │       └── Lịch sử hoạt động
    └── Thành viên và lời mời
```

## Tính năng

- Đăng ký, đăng nhập, đăng xuất và quản lý hồ sơ cá nhân.
- Tạo bảng từ mẫu có sẵn hoặc cấu hình riêng; sao chép bảng khi cần.
- Thêm, đổi tên, sắp xếp và xoá cột bằng kéo-thả.
- Tạo và quản lý công việc: mô tả Markdown, trạng thái, độ ưu tiên, hạn xử lý, nhãn và người phụ trách.
- Di chuyển công việc trong cùng cột hoặc giữa các cột bằng kéo-thả.
- Theo dõi công việc ở dạng bảng Kanban, lịch và phân tích số liệu.
- Bình luận, nhắc tên thành viên, checklist và tải tệp đính kèm.
- Thông báo trong ứng dụng, lịch sử hoạt động và danh sách “Task của tôi”.
- Quản lý thành viên, lời mời và vai trò ở cấp bảng.
- Khu vực quản trị để quản lý tài khoản, trạng thái dùng chung và mẫu bảng.

## Phân quyền bảng

Chủ sở hữu bảng luôn có toàn quyền. Thành viên được cấp một trong các vai trò sau:

| Vai trò | Quyền chính |
|---|---|
| `board_viewer` | Xem bảng và công việc. |
| `board_editor` | Xem và chỉnh sửa công việc, cột, bình luận, checklist và tệp đính kèm. |
| `board_member_manager` | Toàn bộ quyền của editor, đồng thời mời và quản lý thành viên. |

Quản trị viên là quyền ở cấp ứng dụng, tách biệt với các vai trò trên bảng.

## Công nghệ sử dụng

| Thành phần | Công nghệ |
|---|---|
| Backend | PHP 8.2, Laravel 10 |
| Frontend | Vue 3, Inertia.js, Vite |
| Giao diện | Bootstrap 5, Font Awesome, CSS responsive |
| Biểu đồ và kéo-thả | Chart.js, VueDraggable |
| Cơ sở dữ liệu | MySQL 8 |
| Hạ tầng phát triển | Docker, Docker Compose, Nginx, PHP-FPM, Supervisor |
| Email phát triển | Mailpit |
| Kiểm thử | PHPUnit 10 với SQLite in-memory |

## Cài đặt bằng Docker

Đây là cách chạy được khuyến nghị. Máy phát triển cần có Docker và Docker Compose.

1. Tạo file môi trường từ mẫu:

   ```bash
   cp .env.example .env
   ```

2. Kiểm tra và điều chỉnh các cổng trong `.env` nếu cần, đặc biệt là `APP_PORT`, `DB_PORT` và `MAILPIT_UI_PORT`.

3. Build image và khởi động dịch vụ:

   ```bash
   make build
   make up
   ```

4. Ứng dụng sẽ tự tạo `APP_KEY` nếu thiếu và chạy migration khi container khởi động. Mở ứng dụng tại `http://localhost:<APP_PORT>`; giá trị mặc định trong `.env.example` là `http://localhost:8888`.

Các dịch vụ đi kèm:

| Dịch vụ | Mục đích |
|---|---|
| `app` | Ứng dụng Laravel, Nginx và PHP-FPM. |
| `db` | MySQL lưu dữ liệu ứng dụng. |
| `mailpit` | Hộp thư phát triển, mở tại `http://localhost:<MAILPIT_UI_PORT>`; mặc định là cổng `8025`. |

### Dữ liệu khởi tạo

Migration tự tạo các quyền bảng bắt buộc. Khi cần tài khoản quản trị cho môi trường phát triển, chạy:

```bash
make seed
```

Không seed dữ liệu mẫu hoặc công khai thông tin đăng nhập ở môi trường production. Dùng lệnh sau chỉ khi chấp nhận xoá toàn bộ dữ liệu hiện có:

```bash
make fresh
```

## Phát triển hằng ngày

| Lệnh | Mục đích |
|---|---|
| `make up` / `make down` | Khởi động hoặc dừng các service. |
| `make logs` | Theo dõi log container. |
| `make shell` | Mở shell trong container ứng dụng. |
| `make dev` | Chạy Vite development server. |
| `make npm c="run build"` | Build frontend cho production. |
| `make migrate` | Chạy migration. |
| `make seed` | Chạy database seeder. |
| `make artisan c="route:list"` | Chạy lệnh Artisan bất kỳ. |
| `make test` | Chạy PHPUnit. |
| `make quality` | Kiểm tra Pint, PHPMD, PHPCS và cú pháp PHP. |
| `make quality-fix` | Áp dụng các sửa chữa style an toàn. |
| `make help` | Xem danh sách lệnh được hỗ trợ. |

## Cài đặt không dùng Docker

Yêu cầu: PHP 8.2, Composer, Node.js, npm và MySQL 8.

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Cập nhật biến `DB_*` trong `.env` để trỏ đến MySQL, sau đó chạy:

```bash
php artisan migrate
npm install
npm run dev
php artisan serve
```

Ứng dụng sẽ có tại `http://127.0.0.1:8000`. Nếu cần dữ liệu khởi tạo cho môi trường local, chạy thêm `php artisan db:seed`.

## Hướng dẫn sử dụng

### 1. Bắt đầu

1. Đăng ký tài khoản hoặc đăng nhập.
2. Tại **Bảng của tôi**, chọn mẫu bảng hoặc nhập tên để tạo bảng mới.
3. Mở bảng vừa tạo; các cột mặc định có thể được đổi tên, bổ sung, sắp xếp hoặc xoá theo quyền của bạn.

### 2. Quản lý công việc

1. Chọn **Thêm công việc** trong cột phù hợp.
2. Nhập tiêu đề, sau đó mở công việc để thêm mô tả, hạn xử lý, mức ưu tiên, trạng thái, nhãn và người phụ trách.
3. Kéo thẻ công việc sang cột khác để cập nhật tiến độ, hoặc chuyển sang chế độ **Lịch** để kéo thẻ sang ngày cần thực hiện.
4. Dùng thanh lọc trên bảng để tìm theo tiêu đề/mã, ưu tiên, người thực hiện, nhãn hoặc hạn xử lý.

### 3. Cộng tác

- Trong trang chi tiết công việc, thêm checklist, bình luận hoặc tệp đính kèm.
- Dùng Markdown trong phần mô tả và bình luận; có thể xem trước trước khi gửi.
- Xem **Task của tôi** để theo dõi các công việc được phân công trên mọi bảng bạn có quyền truy cập.
- Mở phần **Hoạt động** để xem lịch sử thay đổi của bảng.

### 4. Mời và quản lý thành viên

1. Mở bảng và chọn **Thành viên**.
2. Nhập email của tài khoản đã đăng ký, chọn vai trò, rồi gửi lời mời.
3. Người nhận mở thông báo trong ứng dụng và chấp nhận lời mời qua liên kết có chữ ký số.
4. Chủ sở hữu hoặc người có quyền `board_member_manager` có thể thay đổi vai trò, gỡ thành viên hoặc huỷ lời mời đang chờ.

### 5. Quản trị hệ thống

Tài khoản quản trị được chuyển đến khu vực quản trị sau khi đăng nhập. Tại đây có thể quản lý tài khoản, trạng thái dùng chung, mẫu bảng và xem số liệu tổng quan.

## Kiểm thử và kiểm tra chất lượng

```bash
make test
make quality
make npm c="run build"
```

Để chạy một nhóm test cụ thể:

```bash
make artisan c="test --filter=TaskAccessTest"
```

## Lưu ý triển khai

- Production phải dùng `APP_ENV=production`, `APP_DEBUG=false` và mật khẩu cơ sở dữ liệu mạnh.
- Đặt `RUN_SEEDERS=false` trong production để tránh tạo dữ liệu khởi tạo ngoài ý muốn.
- Cấu hình `APP_URL` đúng domain thực tế vì ứng dụng sử dụng signed URL cho một số luồng truy cập và lời mời.
- Sau khi đổi cấu hình, route hoặc biến môi trường trong container đang chạy, chạy `make artisan c="optimize:clear"` hoặc rebuild container.
- Không commit `.env`, credential, cache sinh tự động hoặc dữ liệu database cục bộ.

## Tài liệu dành cho lập trình viên

- [AGENTS.md](AGENTS.md): quy ước cấu trúc, kiểm thử và chất lượng mã.
- [CLAUDE.md](CLAUDE.md): ghi chú kiến trúc và vận hành chi tiết.
