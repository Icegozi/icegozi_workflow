<?php

use App\Support\BoardTemplates;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    /** Cập nhật duy nhất các mẫu ví dụ chưa từng được người quản trị chỉnh sửa. */
    public function up(): void
    {
        $legacyTemplates = [
            ['name' => 'Cơ bản', 'columns' => ['Việc cần làm', 'Đang làm', 'Hoàn thành']],
            ['name' => 'Sprint', 'columns' => ['Backlog', 'Cần làm', 'Đang làm', 'Review', 'Hoàn thành']],
            ['name' => 'Bug Tracker', 'columns' => ['Tiếp nhận', 'Đang xử lý', 'Kiểm thử', 'Đã đóng']],
            ['name' => 'Lịch nội dung', 'columns' => ['Ý tưởng', 'Đang viết', 'Chờ duyệt', 'Đã đăng']],
        ];

        $defaults = BoardTemplates::defaults();
        foreach ($legacyTemplates as $position => $legacy) {
            $template = DB::table('board_templates')->where('name', $legacy['name'])->first();
            if (! $template || json_decode($template->columns, true) !== $legacy['columns']) {
                continue;
            }

            $replacement = $defaults[$position];
            $statusIds = collect($replacement['statuses'])
                ->map(fn ($key) => DB::table('statuses')->where('key', $key)->value('id'))
                ->filter()
                ->values()
                ->all();

            DB::table('board_templates')->where('id', $template->id)->update([
                'name' => $replacement['name'],
                'icon' => $replacement['icon'],
                'description' => $replacement['description'],
                'columns' => json_encode($replacement['columns'], JSON_UNESCAPED_UNICODE),
                'status_ids' => json_encode($statusIds),
                'labels' => json_encode($replacement['labels'], JSON_UNESCAPED_UNICODE),
                'position' => $position,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Không hoàn nguyên dữ liệu mẫu để tránh ghi đè thay đổi của người quản trị.
    }
};
