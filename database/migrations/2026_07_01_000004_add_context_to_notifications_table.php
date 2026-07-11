<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->foreignId('task_id')->nullable()->after('user_id')
                ->constrained('tasks')->nullOnDelete();
            $table->string('url')->nullable()->after('task_id');   // link click-through
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('task_id');
            $table->dropColumn('url');
        });
    }
};
