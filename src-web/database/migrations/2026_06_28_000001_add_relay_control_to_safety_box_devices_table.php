<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('safety_box_devices', function (Blueprint $table) {
            $table->string('relay_status')->default('Off')->after('door_status');
            $table->boolean('relay_command_pending')->default(false)->after('relay_status');
            $table->timestamp('relay_expires_at')->nullable()->after('relay_command_pending');
        });
    }

    public function down(): void
    {
        Schema::table('safety_box_devices', function (Blueprint $table) {
            $table->dropColumn([
                'relay_status',
                'relay_command_pending',
                'relay_expires_at',
            ]);
        });
    }
};
