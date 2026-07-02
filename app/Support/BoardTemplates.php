<?php

namespace App\Support;

/**
 * Dữ liệu mẫu bảng mặc định — CHỈ dùng để seed vào bảng board_templates.
 * Sau khi seed, ứng dụng đọc/ghi qua model App\Models\BoardTemplate (admin quản lý).
 *
 * Mỗi mẫu gồm:
 *  - columns: các cột = quy trình triển khai (KHÔNG phải tên trạng thái)
 *  - statuses: khoá (key) các trạng thái global mà mẫu áp dụng (tập con)
 *  - labels: nhãn kèm màu
 */
class BoardTemplates
{
    public static function defaults(): array
    {
        return [
            [
                'name' => 'Cơ bản',
                'icon' => 'fa-columns',
                'description' => 'Quy trình ba bước đơn giản để bắt đầu nhanh.',
                'columns' => ['Việc cần làm', 'Đang làm', 'Hoàn thành'],
                'statuses' => ['new', 'in_progress', 'done'],
                'labels' => [],
            ],
            [
                'name' => 'Sprint',
                'icon' => 'fa-bolt',
                'description' => 'Quy trình scrum: Backlog → Review → Hoàn thành.',
                'columns' => ['Backlog', 'Cần làm', 'Đang làm', 'Review', 'Hoàn thành'],
                'statuses' => ['new', 'in_progress', 'check', 'done'],
                'labels' => [
                    ['name' => 'Tính năng', 'color' => '#006adc'],
                    ['name' => 'Lỗi', 'color' => '#e5484d'],
                    ['name' => 'Việc vặt', 'color' => '#7a869a'],
                ],
            ],
            [
                'name' => 'Bug Tracker',
                'icon' => 'fa-bug',
                'description' => 'Theo dõi lỗi từ khi báo cáo đến khi đóng.',
                'columns' => ['Tiếp nhận', 'Đang xử lý', 'Kiểm thử', 'Đã đóng'],
                'statuses' => ['new', 'in_progress', 'check', 'done', 'pending'],
                'labels' => [
                    ['name' => 'Nghiêm trọng', 'color' => '#e5484d'],
                    ['name' => 'Cao', 'color' => '#f76808'],
                    ['name' => 'Thấp', 'color' => '#18794e'],
                ],
            ],
            [
                'name' => 'Lịch nội dung',
                'icon' => 'fa-pen-nib',
                'description' => 'Sản xuất nội dung: ý tưởng → xuất bản.',
                'columns' => ['Ý tưởng', 'Đang viết', 'Chờ duyệt', 'Đã đăng'],
                'statuses' => ['new', 'in_progress', 'done'],
                'labels' => [
                    ['name' => 'Blog', 'color' => '#006adc'],
                    ['name' => 'Mạng xã hội', 'color' => '#8e4ec6'],
                ],
            ],
        ];
    }
}
