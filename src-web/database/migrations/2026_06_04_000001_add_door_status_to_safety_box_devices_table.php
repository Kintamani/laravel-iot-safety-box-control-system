<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('safety_box_devices', function (Blueprint $table) {
            $table->enum('door_status', ['Open', 'Closed'])
                ->nullable()
                ->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('safety_box_devices', function (Blueprint $table) {
            $table->dropColumn('door_status');
        });
    }
};
