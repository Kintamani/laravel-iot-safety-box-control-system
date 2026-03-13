<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_orders', function (Blueprint $table) {
            $table->id('order_id');
            $table->string('spreadsheet_row_id')->nullable();
            $table->string('customer_name');
            $table->string('customer_contact');
            $table->string('phone_model')->nullable();
            $table->enum('status', ['Pending', 'In Transit', 'Completed'])->default('Pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_orders');
    }
};
