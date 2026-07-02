<?php

namespace App\Support;

/**
 * Tiện ích cho các liên kết mạng xã hội của user (facebook/twitter/linkedin/github/website).
 * Dùng chung giữa FormRequest (chuẩn hoá trước khi validate) và controller (làm sạch trước khi lưu).
 */
class SocialLinks
{
    public const KEYS = ['facebook', 'twitter', 'linkedin', 'github', 'website'];

    /**
     * Chuẩn hoá đầu vào: tự thêm "https://" cho link thiếu scheme (để qua rule url,
     * vd người dùng gõ "facebook.com/abc"). Trả về map đầy đủ các KEY (giá trị đã trim).
     */
    public static function normalize(?array $social): array
    {
        $out = [];
        foreach (self::KEYS as $key) {
            $value = trim((string) ($social[$key] ?? ''));
            if ($value !== '' && ! preg_match('#^https?://#i', $value)) {
                $value = 'https://' . $value;
            }
            if ($value !== '') {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    /** Làm sạch để lưu: bỏ key rỗng; trả null nếu không còn gì. */
    public static function clean(?array $social): ?array
    {
        return self::normalize($social) ?: null;
    }
}
