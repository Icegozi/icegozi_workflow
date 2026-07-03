<?php

namespace App\Console\Commands;

use App\Models\Comment;
use App\Models\Task;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Dọn ảnh/tệp upload nội tuyến (thư mục editor/ trên disk public) không còn được dùng.
 *
 * AN TOÀN LÀ ƯU TIÊN SỐ 1 — các file này là content-addressed (đặt tên theo hash nội dung),
 * nên MỘT file có thể được trỏ tới bởi NHIỀU mô tả task / bình luận khác nhau. Vì vậy chỉ xoá
 * một file khi:
 *   1) KHÔNG có mô tả task nào (còn sống, hoặc xoá mềm trong "retention") tham chiếu, VÀ
 *   2) KHÔNG có bình luận nào (còn sống, hoặc xoá mềm trong retention) tham chiếu, VÀ
 *   3) File cũ hơn retention (grace cho ảnh vừa dán nhưng chưa kịp lưu mô tả/bình luận).
 *
 * Nhờ (1)(2) gom cả bản ghi xoá-mềm-gần-đây, việc khôi phục (restore) task/comment trong
 * khoảng retention sẽ KHÔNG bị vỡ ảnh. Task/board xoá mềm quá lâu -> ảnh riêng của nó mới bị dọn.
 */
class PruneInlineMedia extends Command
{
    protected $signature = 'media:prune-inline
        {--days=30 : Số ngày giữ (retention) trước khi dọn file mồ côi}
        {--dry-run : Chỉ liệt kê, không xoá}';

    protected $description = 'Dọn file editor/ nội tuyến không còn được tham chiếu (an toàn với soft-delete/restore).';

    public function handle(): int
    {
        $disk = Storage::disk('public');
        if (! $disk->exists('editor')) {
            $this->info('Không có thư mục editor/ — không có gì để dọn.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subDays(max(0, (int) $this->option('days')));

        $referenced = $this->referencedPaths($cutoff);
        [$kept, $deleted, $freed] = $this->pruneOrphans($disk, $referenced, $cutoff, $dryRun);

        $this->info(sprintf(
            'Dọn nội tuyến xong: giữ %d, %s %d file (~%.2f MB).',
            $kept,
            $dryRun ? 'sẽ xoá' : 'đã xoá',
            $deleted,
            $freed / 1048576
        ));

        return self::SUCCESS;
    }

    /**
     * Gom mọi đường dẫn editor/... đang được tham chiếu bởi nội dung "còn giá trị"
     * (mô tả task + bình luận còn sống HOẶC mới xoá mềm trong retention).
     *
     * @return array<string, true>
     */
    private function referencedPaths(CarbonInterface $cutoff): array
    {
        $referenced = [];
        $collect = function (?string $text) use (&$referenced): void {
            if ($text && preg_match_all('#editor/[A-Za-z0-9]+\.[A-Za-z0-9]+#', $text, $m)) {
                foreach ($m[0] as $path) {
                    $referenced[$path] = true;
                }
            }
        };

        $withinRetention = function ($query) use ($cutoff): void {
            $query->whereNull('deleted_at')->orWhere('deleted_at', '>=', $cutoff);
        };

        Task::withTrashed()->where($withinRetention)->whereNotNull('description')
            ->select('description')
            ->chunk(500, fn ($rows) => $rows->each(fn ($r) => $collect($r->description)));

        Comment::withTrashed()->where($withinRetention)->whereNotNull('content')
            ->select('content')
            ->chunk(500, fn ($rows) => $rows->each(fn ($r) => $collect($r->content)));

        return $referenced;
    }

    /**
     * Xoá file editor/ vừa KHÔNG được tham chiếu vừa đủ cũ (qua retention).
     *
     * @param  array<string, true>  $referenced
     * @return array{0: int, 1: int, 2: int} [kept, deleted, freedBytes]
     */
    private function pruneOrphans(Filesystem $disk, array $referenced, CarbonInterface $cutoff, bool $dryRun): array
    {
        $kept = 0;
        $deleted = 0;
        $freed = 0;

        foreach ($disk->files('editor') as $path) {
            // Còn tham chiếu, hoặc còn mới (bản nháp đang soạn) -> giữ.
            if (isset($referenced[$path]) || $disk->lastModified($path) > $cutoff->getTimestamp()) {
                $kept++;

                continue;
            }

            $freed += $disk->size($path);
            if ($dryRun) {
                $this->line("[dry-run] sẽ xoá: {$path}");
            } else {
                $disk->delete($path);
            }
            $deleted++;
        }

        return [$kept, $deleted, $freed];
    }
}
