<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BoardTemplate;
use App\Models\Status;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class StatusController extends Controller
{
    public function index()
    {
        $statuses = Status::orderBy('position')->get()->map(fn ($s) => [
            'id' => $s->id,
            'key' => $s->key,
            'name' => $s->name,
            'color' => $s->color,
            'position' => $s->position,
            'is_default' => $s->is_default,
            'is_completed' => $s->is_completed,
            'tasks_count' => $s->tasks()->count(),
        ]);

        return Inertia::render('Admin/Statuses/Index', ['statuses' => $statuses]);
    }

    public function create()
    {
        return Inertia::render('Admin/Statuses/Form', ['status' => null]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data['key'] = $this->uniqueKey($data['key'] ?? null, $data['name']);

        DB::transaction(function () use ($data) {
            $status = Status::create($data);
            $this->ensureSingleDefault($status);
        });

        return redirect()->route('admin.status.index')->with('success', 'Đã tạo trạng thái.');
    }

    public function edit(Status $status)
    {
        return Inertia::render('Admin/Statuses/Form', [
            'status' => [
                'id' => $status->id,
                'key' => $status->key,
                'name' => $status->name,
                'color' => $status->color,
                'position' => $status->position,
                'is_default' => $status->is_default,
                'is_completed' => $status->is_completed,
            ],
        ]);
    }

    public function update(Request $request, Status $status)
    {
        $data = $this->validateData($request);
        $data['key'] = $this->uniqueKey($data['key'] ?? null, $data['name'], $status->id);

        DB::transaction(function () use ($status, $data) {
            $status->update($data);
            $this->ensureSingleDefault($status);
        });

        return redirect()->route('admin.status.index')->with('success', 'Đã cập nhật trạng thái.');
    }

    public function destroy(Status $status)
    {
        DB::transaction(function () use ($status) {
            // Gỡ id khỏi status_ids của mọi mẫu (JSON, không có ràng buộc FK)
            foreach (BoardTemplate::all() as $tpl) {
                $ids = collect($tpl->status_ids ?? [])->reject(fn ($id) => (int) $id === $status->id)->values()->all();
                if (count($ids) !== count($tpl->status_ids ?? [])) {
                    $tpl->update(['status_ids' => $ids]);
                }
            }
            // tasks.status_id -> null (nullOnDelete), board_status -> cascade
            $status->delete();
        });

        return redirect()->route('admin.status.index')->with('success', 'Đã xoá trạng thái.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:100',
            'key' => 'nullable|string|max:50|alpha_dash',
            'color' => 'required|string|max:20',
            'position' => 'nullable|integer|min:0',
            'is_default' => 'boolean',
            'is_completed' => 'boolean',
        ]);
    }

    /** Sinh key duy nhất từ key nhập vào hoặc từ name. */
    private function uniqueKey(?string $key, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($key ?: $name, '_') ?: 'status';
        $candidate = $base;
        $i = 1;
        $taken = fn ($value) => Status::where('key', $value)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
        while ($taken($candidate)) {
            $candidate = $base . '_' . (++$i);
        }

        return $candidate;
    }

    /** Nếu status này là mặc định thì bỏ mặc định ở các status khác. */
    private function ensureSingleDefault(Status $status): void
    {
        if ($status->is_default) {
            Status::where('id', '!=', $status->id)->where('is_default', true)->update(['is_default' => false]);
        }
    }
}
