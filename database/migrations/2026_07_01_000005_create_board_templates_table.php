<?php

use App\Support\BoardTemplates;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('board_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('icon')->default('fa-columns');
            $table->string('description')->nullable();
            $table->json('columns');      // ["Việc cần làm", ...] = quy trình
            $table->json('status_ids');   // [1,2,4] = tập con status global áp dụng
            $table->json('labels');       // [{"name":"Lỗi","color":"#e5484d"}, ...]
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        // Map key trạng thái -> id (statuses đã được seed trước đó).
        $statusIdByKey = DB::table('statuses')->pluck('id', 'key');

        $pos = 0;
        foreach (BoardTemplates::defaults() as $tpl) {
            $statusIds = collect($tpl['statuses'])
                ->map(fn ($key) => $statusIdByKey[$key] ?? null)
                ->filter()->values()->all();

            DB::table('board_templates')->insert([
                'name' => $tpl['name'],
                'icon' => $tpl['icon'],
                'description' => $tpl['description'],
                'columns' => json_encode(array_values($tpl['columns']), JSON_UNESCAPED_UNICODE),
                'status_ids' => json_encode($statusIds),
                'labels' => json_encode(array_values($tpl['labels']), JSON_UNESCAPED_UNICODE),
                'position' => $pos++,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('board_templates');
    }
};
