# Tự động deploy với Railway

Railway đã có GitHub Autodeploy, vì vậy dự án không cần GitHub Actions, SSH key hay script deploy chạy trên VPS. Sau khi dịch vụ được liên kết với repository và chọn nhánh `main`, mỗi pull request được merge vào `main` sẽ được Railway build và deploy tự động.

## Cấu hình lần đầu trên Railway

1. Tạo một Railway Project, chọn **New → GitHub Repo**, rồi chọn repository này.
2. Trong service ứng dụng, đặt **Trigger Branch** thành `main` và bật **Autodeploy**.
3. Thêm **MySQL** bằng **New → Database → MySQL**. Không deploy service `db` trong `docker-compose.yml`; Railway quản lý database riêng.
4. Trong tab **Variables** của service ứng dụng, thêm cấu hình production. Các biến database phải tham chiếu service MySQL theo đúng tên service của bạn (ví dụ bên dưới giả sử tên là `MySQL`):

   ```dotenv
   APP_ENV=production
   APP_DEBUG=false
   APP_KEY=base64:...             # tạo bằng: php artisan key:generate --show
   APP_URL=https://${{RAILWAY_PUBLIC_DOMAIN}}
   PORT=80
   LOG_CHANNEL=stderr
   LOG_STDERR_FORMATTER=\Monolog\Formatter\JsonFormatter
   DB_CONNECTION=mysql
   DB_HOST=${{MySQL.MYSQLHOST}}
   DB_PORT=${{MySQL.MYSQLPORT}}
   DB_DATABASE=${{MySQL.MYSQLDATABASE}}
   DB_USERNAME=${{MySQL.MYSQLUSER}}
   DB_PASSWORD=${{MySQL.MYSQLPASSWORD}}
   RUN_MIGRATIONS=false           # migrations run in Railway pre-deploy
   RUN_SEEDERS=false
   ```

   Cấu hình `MAIL_*`, `SESSION_*`, `CACHE_*`, `QUEUE_CONNECTION` và các biến ứng dụng khác theo môi trường production của bạn. Không dán hoặc commit `.env` production vào repository.

5. Trong **Settings → Networking**, chọn **Generate Domain** hoặc thêm custom domain. Railway cấp TLS cho domain được cấu hình.
6. Trong **Settings → Source**, bật **Wait for CI** nếu muốn Railway chỉ deploy sau khi GitHub Actions kiểm tra code thành công.

## Cấu hình trong repository

`railway.toml` bảo Railway build bằng `Dockerfile`. Dockerfile cài Composer/NPM dependency và build Vite ngay trong image, nên `railway/init-app.sh` có thể chạy migration và tạo cache Laravel trong pre-deploy container. Nếu migrate thất bại, deployment mới sẽ không được phát hành.

`docker-compose.yml` vẫn giữ nguyên cho môi trường local; Railway không sử dụng nó cho service production.

## Lưu trữ file upload

Filesystem của service Railway là tạm thời. Nếu ứng dụng cần giữ file đính kèm, cấu hình một Volume hoặc chuyển `FILESYSTEM_DISK` sang object storage (S3-compatible) trước khi dùng production. Database MySQL là service riêng nên không bị mất khi app được deploy lại.
