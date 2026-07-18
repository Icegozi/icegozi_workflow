<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedBigInteger('revision')->default(1)->after('position');
        });

        Schema::table('columns', function (Blueprint $table) {
            $table->unsignedBigInteger('revision')->default(1)->after('position');
        });

        Schema::table('labels', function (Blueprint $table) {
            $table->unsignedBigInteger('revision')->default(1)->after('color');
        });
    }

    public function down(): void
    {
        Schema::table('labels', fn (Blueprint $table) => $table->dropColumn('revision'));
        Schema::table('columns', fn (Blueprint $table) => $table->dropColumn('revision'));
        Schema::table('tasks', fn (Blueprint $table) => $table->dropColumn('revision'));
    }
};
