<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Task;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AttachmentController extends Controller
{
    private function authorizeTaskAccess(?Task $task, array $requiredPermissions = [])
    {
        abort_if(! $task, 404, 'Không tìm thấy công việc.');
        $board = $task->column?->board;
        abort_if(! $board, 404, 'Không tìm thấy bảng.');
        $user = Auth::user();
        foreach ($requiredPermissions as $permission) {
            if ($user->hasBoardPermission($board, $permission)) {
                return $board;
            }
        }
        abort(403, 'Bạn không có quyền thực hiện thao tác!');
    }

    public function store(Request $request, Task $task)
    {
        $this->authorizeTaskAccess($task, ['board_editor', 'board_member_manager']);
        $request->validate([
            'attachments' => 'required|array',
            'attachments.*' => 'file|max:10240|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,'
                . 'png,jpg,jpeg,gif,bmp,webp,zip,rar,7z,txt,csv',
        ]);

        $uploadedAttachmentsData = [];
        $successMessages = [];
        $errorMessages = [];

        try {
            if ($request->hasfile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = null;
                    try {
                        $originalName = $file->getClientOriginalName();
                        $filename = Str::slug(pathinfo($originalName, PATHINFO_FILENAME))
                            . '-' . time()
                            . '-' . Str::random(5)
                            . '.' . $file->getClientOriginalExtension();
                        // Lưu vào disk 'public'
                        $path = $file->storeAs('attachments/task_' . $task->id, $filename, 'public');

                        if (! $path) {
                            $errorMessages[] = "Không thể lưu file: {$originalName}.";
                            Log::error(
                                "Attachment store failed for file: {$originalName} "
                                . "on task {$task->id}. Path not returned."
                            );

                            continue;
                        }

                        $attachment = \DB::transaction(function () use ($task, $originalName, $path, $file) {
                            $lockedTask = Task::query()->lockForUpdate()->findOrFail($task->id);
                            $attachment = Attachment::create([
                                'file_name' => $originalName,
                                'file_path' => $path,
                                'file_size' => $file->getSize(),
                                'mime_type' => $file->getMimeType(),
                                'task_id' => $lockedTask->id,
                                'user_id' => Auth::id(),
                            ]);

                            $lockedTask->taskHistories()->create([
                                'user_id' => Auth::id(),
                                'action' => 'attachment_added',
                                'note' => 'Đã thêm đính kèm: ' . e($originalName),
                            ]);
                            $lockedTask->bumpRevision();

                            return $attachment;
                        });

                        $uploadedAttachmentsData[] = $attachment;

                        $successMessages[] = "File '{$originalName}' đã được tải lên.";
                    } catch (Exception $e) {
                        $this->deleteFailedUpload($path);
                        Log::error(
                            "Attachment upload failed for file: {$originalName} "
                            . "on task {$task->id}. Error: " . $e->getMessage(),
                            ['exception' => $e]
                        );
                        $errorMessages[] = "Lỗi khi tải lên file '{$originalName}'.";
                    }
                }
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Không có tệp nào được gửi lên.',
                ], 400);
            }

            if (! empty($uploadedAttachmentsData)) {
                return response()->json([
                    'success' => true,
                    'message' => implode("\n", $successMessages)
                        . (! empty($errorMessages) ? "\nLỗi: " . implode("\n", $errorMessages) : ''),
                    'attachments' => $uploadedAttachmentsData,
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Không có tệp nào được tải lên thành công. ' . implode("\n", $errorMessages),
                ], 500);
            }
        } catch (Exception $e) {
            Log::error("General attachment store error for task {$task->id}: " . $e->getMessage(), ['exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => 'Đã xảy ra lỗi chung khi xử lý tải file.',
            ], 500);
        }
    }

    private function deleteFailedUpload(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    // ... các phương thức khác (index, destroy) cũng cần được kiểm tra xem có bị revert về phiên bản cũ không ...

    public function index(Task $task)
    {
        $this->authorizeTaskAccess($task, ['board_viewer', 'board_editor', 'board_member_manager']);
        try {
            // Eager-load user để tránh N+1 khi serialize accessor uploader_name
            $attachments = $task->attachments()->with('user')->latest()->get();

            return response()->json([
                'success' => true,
                'attachments' => $attachments,
            ]);
        } catch (Exception $e) {
            Log::error("Get attachments failed for task {$task->id}: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Đã xảy ra lỗi khi lấy danh sách đính kèm.',
            ], 500);
        }
    }

    public function destroy(Attachment $attachment)
    {
        $task = $attachment->task;
        $this->authorizeTaskAccess($task, ['board_member_manager']);

        try {
            $originalName = $attachment->file_name;

            \DB::transaction(function () use ($attachment, $task, $originalName) {
                $lockedTask = Task::query()->lockForUpdate()->findOrFail($task->id);
                $lockedAttachment = Attachment::query()->lockForUpdate()->findOrFail($attachment->id);
                $lockedAttachment->delete();
                $lockedTask->taskHistories()->create([
                    'user_id' => Auth::id(),
                    'action' => 'attachment_deleted',
                    'note' => 'Đã xoá đính kèm: ' . e($originalName),
                ]);
                $lockedTask->bumpRevision();
            });

            return response()->json([
                'success' => true,
                'message' => 'Đính kèm đã được xoá.',
            ]);
        } catch (Exception $e) {
            Log::error("Attachment delete failed for attachment {$attachment->id}: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Đã xảy ra lỗi khi xoá đính kèm.',
            ], 500);
        }
    }

    public function download(Attachment $attachment)
    {
        $task = $attachment->task;
        $this->authorizeTaskAccess($task, ['board_viewer', 'board_editor', 'board_member_manager']);
        try {
            if (! Storage::disk('public')->exists($attachment->file_path)) {
                Log::error(
                    "File not found for download: Attachment ID {$attachment->id}, "
                    . "Path: {$attachment->file_path}"
                );

                return response()->json(['success' => false, 'message' => 'Không tìm thấy tệp tin.'], 404);
            }

            return Storage::disk('public')->download($attachment->file_path, $attachment->file_name);
        } catch (Exception $e) {
            Log::error(
                "Download attachment failed for attachment {$attachment->id}: " . $e->getMessage(),
                ['exception' => $e]
            );

            return response()->json([
                'success' => false,
                'message' => 'Đã xảy ra lỗi khi tải tệp đính kèm.',
            ], 500);
        }
    }

    /**
     * Upload nội tuyến cho ô soạn (mô tả / bình luận): lưu 1 tệp rồi trả về URL
     * để chèn Markdown ảnh/liên kết. Cho phép cả quyền xem (để bình luận đính kèm được).
     */
    public function uploadInline(Request $request, Task $task)
    {
        $this->authorizeTaskAccess($task, ['board_viewer', 'board_editor', 'board_member_manager']);
        $request->validate([
            'file' => 'required|file|max:10240|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,'
                . 'png,jpg,jpeg,gif,bmp,webp,zip,rar,7z,txt,csv',
        ]);

        try {
            $file = $request->file('file');
            $originalName = $file->getClientOriginalName();

            // Đặt tên theo hash nội dung (content-addressed): cùng một tệp/ảnh -> cùng hash
            // -> cùng đường dẫn. Nhờ vậy dán 1 ảnh nhiều lần (mô tả, bình luận...) chỉ lưu
            // MỘT bản trong storage; lần sau chỉ tái sử dụng, không ghi trùng.
            $ext = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');
            $hash = hash_file('sha256', $file->getRealPath());
            $path = 'editor/' . $hash . '.' . $ext;

            if (! Storage::disk('public')->exists($path)) {
                $stored = $file->storeAs('editor', $hash . '.' . $ext, 'public');
                if (! $stored) {
                    return response()->json(['success' => false, 'message' => 'Không thể lưu tệp.'], 500);
                }
            }

            $mime = (string) $file->getMimeType();

            return response()->json([
                'success' => true,
                'name' => $originalName,
                // URL TƯƠNG ĐỐI (/storage/...) thay vì tuyệt đối: để ảnh xem được từ mọi
                // máy/tên miền truy cập app, không bị "khoá" vào APP_URL (vd localhost).
                'url' => '/storage/' . $path,
                'is_image' => str_starts_with($mime, 'image/'),
            ]);
        } catch (Exception $e) {
            Log::error("Inline upload failed for task {$task->id}: " . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Không thể tải tệp lên.'], 500);
        }
    }
}
