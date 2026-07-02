<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email gửi chủ sở hữu bảng khi có người xin quyền xem một công việc.
 * Kèm nút "Cấp quyền xem" (signed URL) để owner cấp board_viewer chỉ với 1 chạm.
 * Đưa vào hàng đợi (ShouldQueue) để không chặn response khi gửi mail.
 */
class TaskAccessRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Task $task,
        public User $requester,
        public string $boardName,
        public string $taskCode,
        public ?string $note,
        public string $grantUrl,
    ) {
    }

    /**
     * @return array<int, string>
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage())
            ->subject("Yêu cầu quyền truy cập công việc {$this->taskCode}")
            ->greeting('Chào bạn,')
            ->line("{$this->requester->name} ({$this->requester->email}) xin quyền xem công việc "
                . "'{$this->task->title}' [{$this->taskCode}] trong bảng '{$this->boardName}'.");

        if ($this->note) {
            $mail->line('Lời nhắn: ' . $this->note);
        }

        return $mail
            ->action('Cấp quyền xem', $this->grantUrl)
            ->line('Nhấn nút trên để cấp quyền xem (board_viewer) cho người này. Liên kết có hiệu lực 7 ngày.')
            ->line('Nếu bạn không muốn cấp quyền, có thể bỏ qua email này.')
            ->salutation("Trân trọng,\n" . config('app.name'));
    }
}
