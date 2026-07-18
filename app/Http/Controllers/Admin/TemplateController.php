<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BoardTemplate;
use App\Models\Status;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class TemplateController extends Controller
{
    private function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'icon' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:255',
            'position' => 'nullable|integer|min:0',
            'columns' => 'required|array|min:1',
            'columns.*' => 'required|string|max:100',
            'status_ids' => 'nullable|array',
            'status_ids.*' => 'integer|exists:statuses,id',
            'labels' => 'nullable|array',
            'labels.*.name' => 'nullable|string|max:50',
            'labels.*.color' => 'required|string|max:20',
        ];
    }

    public function index()
    {
        $templates = BoardTemplate::orderBy('position')->get()->map(fn ($t) => [
            'id' => $t->id,
            'name' => $t->name,
            'icon' => $t->icon,
            'description' => $t->description,
            'columns' => $t->columns,
            'labels' => $t->labels,
            'status_ids' => $t->status_ids,
            'position' => $t->position,
        ]);

        return Inertia::render('Admin/Templates/Index', ['templates' => $templates]);
    }

    public function create()
    {
        return Inertia::render('Admin/Templates/Form', [
            'template' => null,
            'statuses' => $this->statusOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->normalize($request->validate($this->rules()));
        BoardTemplate::create($data);

        return redirect()->route('admin.template.index')->with('success', 'Đã tạo mẫu bảng.');
    }

    public function edit(BoardTemplate $template)
    {
        return Inertia::render('Admin/Templates/Form', [
            'template' => [
                'id' => $template->id,
                'name' => $template->name,
                'icon' => $template->icon,
                'description' => $template->description,
                'columns' => $template->columns,
                'labels' => $template->labels,
                'status_ids' => $template->status_ids,
                'position' => $template->position,
            ],
            'statuses' => $this->statusOptions(),
        ]);
    }

    public function update(Request $request, BoardTemplate $template)
    {
        $template->update($this->normalize($request->validate($this->rules())));

        return redirect()->route('admin.template.index')->with('success', 'Đã cập nhật mẫu bảng.');
    }

    public function destroy(BoardTemplate $template)
    {
        $template->delete();

        return redirect()->route('admin.template.index')->with('success', 'Đã xoá mẫu bảng.');
    }

    /** Chuẩn hoá dữ liệu đầu vào trước khi lưu. */
    private function normalize(array $data): array
    {
        $data['icon'] = ($data['icon'] ?? null) ?: 'fa-columns';
        $data['position'] = $data['position'] ?? 0;
        $data['columns'] = array_values(array_filter(array_map('trim', $data['columns'])));
        if (count($data['columns']) !== count(array_unique(array_map('mb_strtolower', $data['columns'])))) {
            throw ValidationException::withMessages([
                'columns' => 'Mỗi nhóm công việc chỉ được xuất hiện một lần trong template.',
            ]);
        }
        $data['status_ids'] = array_values($data['status_ids'] ?? []);
        $data['labels'] = array_values(array_filter(
            $data['labels'] ?? [],
            fn ($l) => ! empty($l['color'])
        ));

        return $data;
    }

    private function statusOptions()
    {
        return Status::orderBy('position')->get(['id', 'name', 'color', 'is_completed']);
    }
}
