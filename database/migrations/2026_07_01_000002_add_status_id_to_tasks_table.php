<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        $defaultId = DB::table('statuses')->where('is_default', true)->value('id')
            ?? DB::table('statuses')->orderBy('position')->value('id');
        $doneId = DB::table('statuses')->where('key', 'done')->value('id');

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('status_id')->nullable()->after('column_id')
                ->constrained('statuses')->nullOnDelete();
        });

        // Chuyển dữ liệu từ cột chuỗi cũ sang khóa ngoại.
        if (Schema::hasColumn('tasks', 'status')) {
            DB::table('tasks')->where('status', 'done')->update(['status_id' => $doneId]);
            DB::table('tasks')->whereNull('status_id')->update(['status_id' => $defaultId]);

            Schema::table('tasks', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('status')->default('todo')->after('column_id');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('status_id');
        });
    }
};
