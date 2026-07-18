<?php

namespace App\Support;

/**
 * Dữ liệu mẫu bảng mặc định — CHỈ dùng để seed vào bảng board_templates.
 * Sau khi seed, ứng dụng đọc/ghi qua model App\Models\BoardTemplate (admin quản lý).
 *
 * Mỗi mẫu gồm:
 *  - columns: các nhóm/luồng công việc (KHÔNG phải trạng thái task)
 *  - statuses: khoá (key) các trạng thái global mà mẫu áp dụng (tập con)
 *  - labels: nhãn kèm màu
 */
class BoardTemplates
{
    public static function defaults(): array
    {
        return [
            [
                'name' => 'Dự án liên phòng ban',
                'icon' => 'fa-columns',
                'description' => 'Phân nhóm công việc theo chuyên môn; theo dõi tiến độ bằng trạng thái task.',
                'columns' => ['Chưa phân loại', 'Sản phẩm', 'Kỹ thuật', 'Thiết kế', 'Vận hành'],
                'statuses' => ['new', 'in_progress', 'done'],
                'labels' => [],
            ],
            [
                'name' => 'Sản phẩm phần mềm',
                'icon' => 'fa-bolt',
                'description' => 'Tổ chức backlog theo luồng phát triển, kiểm thử và phát hành.',
                'columns' => ['Product', 'Backend', 'Frontend', 'QA', 'DevOps'],
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
                'description' => 'Phân lỗi theo nơi xử lý; trạng thái cho biết mức độ xử lý hiện tại.',
                'columns' => ['Chưa phân loại', 'Frontend', 'Backend', 'Mobile', 'QA'],
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
                'description' => 'Phân nội dung theo kênh và đội phụ trách thay vì theo trạng thái.',
                'columns' => ['Blog', 'Mạng xã hội', 'Email', 'Thiết kế', 'Phân phối'],
                'statuses' => ['new', 'in_progress', 'done'],
                'labels' => [
                    ['name' => 'Blog', 'color' => '#006adc'],
                    ['name' => 'Mạng xã hội', 'color' => '#8e4ec6'],
                ],
            ],
        ];
    }
}
