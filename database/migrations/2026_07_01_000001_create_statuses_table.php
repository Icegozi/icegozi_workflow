<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('statuses', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();     // slug ổn định, dùng cho logic
            $table->string('name');              // nhãn hiển thị (vi)
            $table->string('color', 20)->default('#6c757d');
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_default')->default(false);   // trạng thái mặc định khi tạo task
            $table->boolean('is_completed')->default(false); // coi như "hoàn thành" (để mờ thẻ...)
            $table->timestamps();
        });

        // Bộ trạng thái khởi tạo (admin có thể sửa/bổ sung sau).
        DB::table('statuses')->insert([
            ['key' => 'new', 'name' => 'Mới', 'color' => '#6c757d', 'position' => 1, 'is_default' => true, 'is_completed' => false],
            ['key' => 'in_progress', 'name' => 'Đang làm', 'color' => '#006adc', 'position' => 2, 'is_default' => false, 'is_completed' => false],
            ['key' => 'check', 'name' => 'Kiểm tra', 'color' => '#f76808', 'position' => 3, 'is_default' => false, 'is_completed' => false],
            ['key' => 'done', 'name' => 'Hoàn thành', 'color' => '#18794e', 'position' => 4, 'is_default' => false, 'is_completed' => true],
            ['key' => 'pending', 'name' => 'Tạm hoãn', 'color' => '#e5484d', 'position' => 5, 'is_default' => false, 'is_completed' => false],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('statuses');
    }
};
