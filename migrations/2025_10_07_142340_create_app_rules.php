<?php

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\{Blueprint, Schema};

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('app_rules', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('app_name'); // nama aplikasi (Core, Merchant, dsb.)
            $table->integer('max_duration'); // max durasi dalam detik
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_rules');
    }
};
