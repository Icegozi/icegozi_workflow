<?php

use Illuminate\Support\Str;
use MatthiasMullie\Minify;

if (! function_exists('asset_min')) {
    function asset_min($path)
    {
        $publicPath = public_path($path);

        // Chỉ minify khi có ?minify và file nguồn thực sự tồn tại bên trong public/.
        if (request()->has('minify') && is_file($publicPath) && ! Str::contains($path, '.min.')) {
            // Chặn path traversal: đường dẫn phải nằm trong thư mục public.
            $real = realpath($publicPath);
            $publicRoot = realpath(public_path());
            if ($real === false || $publicRoot === false || ! Str::startsWith($real, $publicRoot)) {
                return asset($path);
            }

            $map = ['.css' => Minify\CSS::class, '.js' => Minify\JS::class];
            foreach ($map as $ext => $minifierClass) {
                if (Str::endsWith($path, $ext)) {
                    $minPath = Str::replaceLast($ext, '.min'.$ext, $path);
                    $minFullPath = public_path($minPath);

                    if (! is_file($minFullPath) || filemtime($minFullPath) < filemtime($publicPath)) {
                        try {
                            // Ghi ra file tạm rồi đổi tên (atomic) để tránh race khi 2 request cùng minify.
                            $tmp = $minFullPath.'.'.getmypid().'.tmp';
                            (new $minifierClass($publicPath))->minify($tmp);
                            @rename($tmp, $minFullPath);
                        } catch (\Throwable $e) {
                            // Không minify được (thiếu quyền ghi, lỗi parser...) -> phục vụ file gốc.
                            @unlink($tmp ?? '');

                            return asset($path);
                        }
                    }

                    if (! is_file($minFullPath)) {
                        return asset($path);
                    }

                    return asset($minPath).'?v='.filemtime($minFullPath);
                }
            }
        }

        return asset($path);
    }
}
