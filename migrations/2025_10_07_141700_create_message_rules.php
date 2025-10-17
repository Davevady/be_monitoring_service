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
        Schema::create('message_rules', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('app_name'); 
            $table->string('message_key');
            $table->integer('max_duration');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_rules');
    }
};
