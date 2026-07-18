<?php

namespace App\Http\Controllers\Concerns;

use App\Support\SocialLinks;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

trait HandlesProfileMedia
{
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

        // Trả đường dẫn TƯƠNG ĐỐI ('/storage/avatars/...'): không baked host/port từ
        // APP_URL (config disk 'public' đặt url = APP_URL.'/storage'), và khớp với
        // deleteLocalAvatar khi dọn ảnh cũ.
        return '/storage/' . $path;
    }

    /** Xoá file avatar cũ nếu là ảnh nội bộ. */
    protected function deleteLocalAvatar(?string $url): void
    {
        if ($url && str_starts_with($url, '/storage/avatars/')) {
            Storage::disk('public')->delete(substr($url, strlen('/storage/')));
        }
    }

    /** Chỉ giữ các key hợp lệ và bỏ giá trị rỗng; null nếu không còn gì. */
    protected function cleanSocial(?array $social): ?array
    {
        return SocialLinks::clean($social);
    }
}
