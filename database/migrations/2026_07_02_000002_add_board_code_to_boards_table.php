<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('boards', 'board_code')) {
            Schema::table('boards', function (Blueprint $table) {
                // Mã board hiển thị: số tự nhiên tăng dần toàn hệ thống, dùng cho URL /b-{board_code}/...
                $table->unsignedBigInteger('board_code')->nullable()->after('id');
            });
        }

        // Backfill dữ liệu cũ: đánh số 1..N theo thứ tự id (không đụng dữ liệu khác).
        $seq = 0;
        foreach (DB::table('boards')->orderBy('id')->pluck('id') as $id) {
            DB::table('boards')->where('id', $id)->update(['board_code' => ++$seq]);
        }

        // Unique để tra cứu theo mã (cho phép nhiều NULL, nhưng sau backfill không còn NULL).
        Schema::table('boards', function (Blueprint $table) {
            $table->unique('board_code');
        });
    }

    public function down(): void
    {
        Schema::table('boards', function (Blueprint $table) {
            $table->dropUnique(['board_code']);
            $table->dropColumn('board_code');
        });
    }
};
