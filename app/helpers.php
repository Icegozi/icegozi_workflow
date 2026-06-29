<?php

use Illuminate\Support\Str;
use MatthiasMullie\Minify;

if (! function_exists('asset_min_is_within_public')) {
    /**
     * Chặn path traversal: đường dẫn nguồn phải nằm trong thư mục public.
     */
    function asset_min_is_within_public(string $publicPath): bool
    {
        $real = realpath($publicPath);
        $publicRoot = realpath(public_path());

        return $real !== false && $publicRoot !== false && Str::startsWith($real, $publicRoot);
    }
}

if (! function_exists('asset_min_minifier_class')) {
    /**
     * Trả về cặp [extension, class minifier] phù hợp với path, hoặc null nếu không hỗ trợ.
     *
     * @return array{0:string,1:class-string}|null
     */
    function asset_min_minifier_class(string $path): ?array
    {
        $map = ['.css' => Minify\CSS::class, '.js' => Minify\JS::class];
        foreach ($map as $ext => $minifierClass) {
            if (Str::endsWith($path, $ext)) {
                return [$ext, $minifierClass];
            }
        }

        return null;
    }
}

if (! function_exists('asset_min_compile')) {
    /**
     * Minify file nguồn ra $minFullPath nếu chưa có hoặc đã cũ hơn nguồn.
     * Ghi ra file tạm rồi đổi tên (atomic) để tránh race khi 2 request cùng minify.
     *
     * @param  class-string  $minifierClass
     * @return bool  true nếu file .min sẵn sàng phục vụ, false nếu nên phục vụ file gốc.
     */
    function asset_min_compile(string $publicPath, string $minFullPath, string $minifierClass): bool
    {
        if (is_file($minFullPath) && filemtime($minFullPath) >= filemtime($publicPath)) {
            return true;
        }

        $tmp = $minFullPath . '.' . getmypid() . '.tmp';
        try {
            (new $minifierClass($publicPath))->minify($tmp);
            if (is_file($tmp) && rename($tmp, $minFullPath) === false && is_file($tmp)) {
                unlink($tmp);
            }
        } catch (\Throwable $e) {
            // Không minify được (thiếu quyền ghi, lỗi parser...) -> phục vụ file gốc.
            if (is_file($tmp)) {
                unlink($tmp);
            }
        }

        return is_file($minFullPath);
    }
}

if (! function_exists('asset_min')) {
    function asset_min($path)
    {
        $publicPath = public_path($path);

        // Chỉ minify khi có ?minify và file nguồn thực sự tồn tại bên trong public/.
        $eligible = request()->has('minify')
            && is_file($publicPath)
            && ! Str::contains($path, '.min.')
            && asset_min_is_within_public($publicPath);

        if (! $eligible) {
            return asset($path);
        }

        $resolved = asset_min_minifier_class($path);
        if ($resolved === null) {
            return asset($path);
        }

        [$ext, $minifierClass] = $resolved;
        $minPath = Str::replaceLast($ext, '.min' . $ext, $path);
        $minFullPath = public_path($minPath);

        if (! asset_min_compile($publicPath, $minFullPath, $minifierClass)) {
            return asset($path);
        }

        return asset($minPath) . '?v=' . filemtime($minFullPath);
    }
}
