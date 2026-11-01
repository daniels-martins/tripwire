<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->string('tx_hash')->unique();
            $table->string('provider')->nullable();
            $table->string('corridor');
            $table->decimal('quoted_amount', 20, 8)->nullable();
            $table->decimal('delivered_amount', 20, 8);
            $table->decimal('slippage_bps', 10, 4)->nullable();
            $table->unsignedBigInteger('block_height');
            $table->timestamp('settled_at');
            $table->jsonb('raw_data')->nullable();
            $table->timestamps();

            $table->index(['corridor', 'settled_at']);
            $table->index('block_height');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settlements');
    }
};
