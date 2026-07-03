<?php

namespace App\Models;

use App\Models\Concerns\CascadesSoftDeletes;
use App\Models\Concerns\TaskRelationships;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Task extends Model
{
    use CascadesSoftDeletes;
    use HasFactory;
    use SoftDeletes;
    use TaskRelationships;

    protected $fillable = [
        'title',
        'description',
        'status_id',
        'priority',
        'column_id',
        'due_date',
        'position',
    ];

    protected $casts = [
        'due_date' => 'date',
    ];

    protected static function booted(): void
    {
        // Xoá mềm task -> xoá mềm luôn các bản ghi con (bình luận, checklist, tệp, lịch sử)
        // để không còn dữ liệu "mồ côi" vẫn hiện trong các truy vấn khác. Xoá cứng
        // (forceDelete) thì để FK ON DELETE CASCADE của DB lo.
        static::deleting(function (Task $task) {
            if ($task->isForceDeleting()) {
                return;
            }
            // Xoá mềm hàng loạt (1 UPDATE/quan hệ, không nổ event từng dòng): các bảng con này
            // đều là "lá" (không cascade tiếp) và không có hook deleting cần chạy. Riêng attachment
            // dùng builder delete nên KHÔNG kích hoạt hook xoá file -> file được giữ (khôi phục được).
            $task->comments()->delete();
            $task->checklists()->delete();
            $task->attachments()->delete();
            $task->taskHistories()->delete();
        });
    }

    /**
     * Sinh task_code (số tăng dần TRONG board) một cách atomic: đọc max + INSERT nằm
     * trong CÙNG một transaction với lockForUpdate, nên hai task tạo song song trong
     * cùng board không thể nhận trùng mã (index task_code không unique nên cần khoá này).
     */
    public function save(array $options = []): bool
    {
        if ($this->exists || ! empty($this->task_code) || empty($this->column_id)) {
            return parent::save($options);
        }

        return DB::transaction(function () use ($options) {
            $boardId = Column::withTrashed()->where('id', $this->column_id)->value('board_id');
            $columnIds = Column::withTrashed()->where('board_id', $boardId)->pluck('id');
            $max = static::withTrashed()
                ->whereIn('column_id', $columnIds)
                ->lockForUpdate()
                ->max('task_code');
            $this->task_code = ($max ?? 0) + 1;
            // Ghi board_id để ràng buộc unique(board_id, task_code) ở DB chặn trùng mã
            // (lưới an toàn nếu khoá lockForUpdate không đủ dưới mức isolation READ COMMITTED).
            $this->board_id = $boardId;

            return parent::save($options);
        });
    }

    /**
     * Mã hiển thị kiểu "ICE-0042": tiền tố lấy từ tên board + id có đệm số 0.
     * Chỉ để hiển thị / dựng URL; id thật vẫn suy ngược được qua idFromCode().
     */
    public static function buildCode(?string $boardName, int $id): string
    {
        $clean = preg_replace('/[^A-Za-z0-9]/', '', (string) $boardName);
        $prefix = $clean !== '' ? Str::upper(Str::substr($clean, 0, 3)) : 'TSK';

        return $prefix . '-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
    }

    /** Suy ngược id từ mã (phần số sau dấu '-' cuối). */
    public static function idFromCode(string $code): int
    {
        return (int) Str::afterLast($code, '-');
    }

    /** Mã của task hiện tại (cần đã nạp quan hệ column.board hoặc chấp nhận lazy-load). */
    public function code(): string
    {
        return self::buildCode($this->column?->board?->name, $this->id);
    }

    public function createForColumn(Column $column, array $data): Task
    {
        $maxPosition = $column->tasks()->max('position');
        $position = is_null($maxPosition) ? 0 : $maxPosition + 1;

        $data['position'] = $position;
        $data['column_id'] = $column->id;
        $data['status_id'] = $data['status_id'] ?? Status::default()?->id;
        $data['priority'] = $data['priority'] ?? 'normal';

        return self::create($data);
    }

    public function loadDetails(): self
    {
        return $this->load([
            'column',
            'status',
            'labels',
            'assignees',
            'attachments',
            'comments.user',
            'taskHistories.user',
        ]);
    }

    public function updateDetails(array $data): bool
    {
        $originalData = $this->only(array_keys($data));
        $updated = $this->update($data);

        if ($updated) {
            $changes = [];
            foreach ($data as $field => $newValue) {
                $oldValue = $originalData[$field] ?? null;
                if ($oldValue != $newValue) {
                    $changes[] = $this->describeChange($field, $oldValue, $newValue);
                }
            }

            if (! empty($changes)) {
                $this->taskHistories()->create([
                    'user_id' => Auth::id(),
                    'action' => 'updated',
                    'note' => implode('; ', $changes),
                ]);
            }
        }

        return $updated;
    }

    /**
     * Mô tả thay đổi một trường cho lịch sử: escape dữ liệu (note render bằng v-html),
     * đổi tên trường thân thiện và hiển thị tên trạng thái thay vì id.
     */
    private function describeChange(string $field, $old, $new): string
    {
        $labels = [
            'title' => 'tiêu đề', 'description' => 'mô tả',
            'priority' => 'độ ưu tiên', 'due_date' => 'hạn', 'status_id' => 'trạng thái',
        ];
        $label = $labels[$field] ?? $field;

        if ($field === 'status_id') {
            $names = Status::whereIn('id', array_filter([$old, $new]))->pluck('name', 'id');
            $old = $old ? ($names[$old] ?? $old) : '—';
            $new = $new ? ($names[$new] ?? $new) : '—';
        }

        return e($label) . ": '" . e((string) $old) . "' → '" . e((string) $new) . "'";
    }

    public function deleteWithHistory(): bool
    {
        $this->taskHistories()->create([
            'user_id' => Auth::id(),
            'action' => 'deleted',
            'note' => "Task '" . e($this->title) . "' was deleted.",
        ]);

        return $this->delete();
    }

    public function moveToColumnWithOrder($newColumnId, $orderedTaskIds, $userId)
    {
        $oldColumnId = $this->column_id;
        $oldColumn = $this->column;
        $newColumn = Column::find($newColumnId);

        $this->update(['column_id' => $newColumnId]);

        // Ghi lại lịch sử nếu chuyển sang cột khác
        if ($oldColumnId != $newColumnId) {
            $this->taskHistories()->create([
                'user_id' => $userId,
                'action' => 'di chuyển',
                'note' => "Thẻ công việc di chuyển từ '" . e($oldColumn->name) . "' sang '" . e($newColumn->name) . "'",
            ]);
        }

        $tasksToUpdate = Task::whereIn('id', $orderedTaskIds)
            ->where('column_id', $newColumnId)
            ->get();

        foreach ($tasksToUpdate as $index => $task) {
            $task->update(['position' => $index]);
        }
    }
}
