<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_logs', function (Blueprint $table) {
            $table->id('log_id');
            $table->string('box_id');
            $table->unsignedBigInteger('scanned_qr_id')->nullable();
            $table->enum('log_type', ['Unlock', 'Lock']);
            $table->timestamp('timestamp')->useCurrent();

            $table->foreign('box_id')
                ->references('box_id')
                ->on('safety_box_devices')
                ->cascadeOnDelete();

            $table->foreign('scanned_qr_id')
                ->references('qr_id')
                ->on('qr_codes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_logs');
    }
};
