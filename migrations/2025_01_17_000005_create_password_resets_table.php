<?php

declare(strict_types=1);

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;

class CreatePasswordResetsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('password_resets')) {
            // Table already exists; skip to avoid duplicate indexes
            return;
        }
        Schema::create('password_resets', function (Blueprint $table) {
            $table->string('email', 150)->index();
            $table->string('token', 255);
            $table->timestamp('created_at')->nullable();

            // Indexes
            // email index is already added above; avoid duplicate
            // $table->index(['email']);
            $table->index(['token']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('password_resets');
    }
}
