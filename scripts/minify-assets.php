<?php

/**
 * Minify tài nguyên tĩnh (JS/CSS) trong public/ thành các file .min.* đặt cạnh file gốc.
 *
 * Dùng chính thư viện matthiasmullie/minify như asset_min() lúc runtime để output nhất quán.
 *
 * Cách dùng:
 *   php scripts/minify-assets.php js                 # minify public/assets/js/*.js
 *   php scripts/minify-assets.php css                # minify public/assets/css/*.css
 *   php scripts/minify-assets.php js path/to/dir     # minify *.js trong thư mục chỉ định
 *   php scripts/minify-assets.php js public/assets/js --force   # bỏ qua kiểm tra mtime
 *
 * Quy ước: bỏ qua mọi file đã là *.min.js / *.min.css. Chỉ ghi lại khi nguồn mới hơn
 * bản .min (hoặc khi --force).
 */

require __DIR__ . '/../vendor/autoload.php';

use MatthiasMullie\Minify;

$type = $argv[1] ?? 'js';
$force = in_array('--force', $argv, true);

// Thư mục đích (tham số thứ 2 nếu không phải là cờ), mặc định theo loại.
$argDir = $argv[2] ?? null;
if ($argDir === '--force') {
    $argDir = null;
}

$defaults = [
    'js' => __DIR__ . '/../public/assets/js',
    'css' => __DIR__ . '/../public/assets/css',
];

if (! isset($defaults[$type])) {
    fwrite(STDERR, "Loại không hỗ trợ: '{$type}'. Dùng 'js' hoặc 'css'.\n");
    exit(2);
}

$dir = $argDir ? rtrim($argDir, '/') : $defaults[$type];
$ext = $type;                  // 'js' | 'css'
$minExt = '.min.' . $ext;

if (! is_dir($dir)) {
    fwrite(STDERR, "Không tìm thấy thư mục: {$dir}\n");
    exit(2);
}

$files = glob($dir . '/*.' . $ext) ?: [];
$processed = 0;
$skipped = 0;
$failed = 0;

foreach ($files as $src) {
    // Bỏ qua chính các file đã minify.
    if (str_ends_with($src, $minExt)) {
        continue;
    }

    $minPath = preg_replace('/\.' . preg_quote($ext, '/') . '$/', $minExt, $src);

    // Bỏ qua nếu bản .min đã mới hơn nguồn (trừ khi --force).
    if (! $force && is_file($minPath) && filemtime($minPath) >= filemtime($src)) {
        $skipped++;

        continue;
    }

    try {
        $minifier = $type === 'css' ? new Minify\CSS($src) : new Minify\JS($src);
        $tmp = $minPath . '.' . getmypid() . '.tmp';
        $minifier->minify($tmp);   // ghi ra file tạm
        rename($tmp, $minPath);    // đổi tên (atomic)

        $before = filesize($src);
        $after = filesize($minPath);
        $saved = $before > 0 ? round((1 - $after / $before) * 100) : 0;
        printf(
            "  ✓ %-28s %6.1fKB -> %6.1fKB (-%d%%)\n",
            basename($minPath),
            $before / 1024,
            $after / 1024,
            $saved
        );
        $processed++;
    } catch (\Throwable $e) {
        fwrite(STDERR, '  ✗ ' . basename($src) . ': ' . $e->getMessage() . "\n");
        if (isset($tmp) && is_file($tmp)) {
            @unlink($tmp);
        }
        $failed++;
    }
}

printf("\nHoàn tất: %d minify, %d bỏ qua (đã mới), %d lỗi.\n", $processed, $skipped, $failed);

exit($failed > 0 ? 1 : 0);
