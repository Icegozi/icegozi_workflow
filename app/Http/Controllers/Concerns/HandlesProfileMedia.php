<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

trait HandlesProfileMedia
{
    /** Các nền tảng mạng xã hội được hỗ trợ (khớp với UI). */
    public const SOCIAL_KEYS = ['facebook', 'twitter', 'linkedin', 'github', 'website'];

    /**
     * Lưu ảnh đại diện vào disk public (storage/app/public/avatars) và trả về URL
     * dạng "/storage/avatars/...". Không có file -> giữ nguyên URL cũ.
     * Xoá ảnh cũ nếu nó là ảnh nội bộ do app upload.
     */
    protected function storeAvatar(?UploadedFile $file, ?string $old = null): ?string
    {
        if (! $file) {
            return $old;
        }

        $this->deleteLocalAvatar($old);
        $path = $file->store('avatars', 'public');

        return Storage::disk('public')->url($path);
    }

    /** Xoá file avatar cũ nếu là ảnh nội bộ (bỏ qua URL ngoài / pravatar). */
    protected function deleteLocalAvatar(?string $url): void
    {
        if ($url && str_starts_with($url, '/storage/avatars/')) {
            Storage::disk('public')->delete(substr($url, strlen('/storage/')));
        }
    }

    /** Chỉ giữ các key hợp lệ và bỏ giá trị rỗng; null nếu không còn gì. */
    protected function cleanSocial(?array $social): ?array
    {
        $clean = [];
        foreach (self::SOCIAL_KEYS as $key) {
            $value = trim((string) ($social[$key] ?? ''));
            if ($value !== '') {
                $clean[$key] = $value;
            }
        }

        return $clean ?: null;
    }
}
