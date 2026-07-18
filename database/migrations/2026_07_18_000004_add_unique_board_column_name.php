<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('columns', function (Blueprint $table) {
            $table->unique(['board_id', 'name'], 'columns_board_id_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('columns', function (Blueprint $table) {
            $table->dropUnique('columns_board_id_name_unique');
        });
    }
};
