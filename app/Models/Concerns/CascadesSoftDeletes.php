<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Bọc thao tác xoá MỀM của model trong một transaction để cha + toàn bộ con
 * (cascade qua event `deleting`) được xoá nguyên tử: hoặc xoá hết, hoặc không gì cả.
 * Nếu không bọc, lỗi giữa chừng sẽ để lại trạng thái "nửa xoá" (con mồ côi vẫn hiện
 * trong các truy vấn xuyên bảng như "Task của tôi").
 *
 * Xoá CỨNG (forceDelete) không bọc: đã có FK ON DELETE CASCADE của DB lo.
 */
trait CascadesSoftDeletes
{
    public function delete()
    {
        if ($this->isForceDeleting()) {
            return parent::delete();
        }

        // transaction lồng nhau -> Laravel dùng savepoint, an toàn khi cha gọi con.
        return DB::transaction(fn () => parent::delete());
    }
}
