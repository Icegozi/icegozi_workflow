<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('boards', function (Blueprint $table) {
            $table->unsignedBigInteger('revision')->default(1)->after('layout_revision');
        });
        Schema::table('checklists', function (Blueprint $table) {
            $table->unsignedBigInteger('revision')->default(1)->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('checklists', fn (Blueprint $table) => $table->dropColumn('revision'));
        Schema::table('boards', fn (Blueprint $table) => $table->dropColumn('revision'));
    }
};
