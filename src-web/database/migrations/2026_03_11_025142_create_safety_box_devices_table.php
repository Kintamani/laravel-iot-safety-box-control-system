<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('safety_box_devices', function (Blueprint $table) {
            $table->string('box_id')->primary();
            $table->enum('status', ['Available', 'In Use'])->default('Available');
            $table->integer('battery_level')->nullable();
            $table->string('gps_location')->nullable();
            $table->timestamp('last_seen')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('safety_box_devices');
    }
};
