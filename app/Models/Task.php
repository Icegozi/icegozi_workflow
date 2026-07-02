<?php

namespace App\Models;

use App\Models\Concerns\TaskRelationships;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class Task extends Model
{
    use HasFactory;
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
