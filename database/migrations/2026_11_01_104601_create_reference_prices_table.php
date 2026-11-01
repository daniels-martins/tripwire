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
        Schema::create('reference_prices', function (Blueprint $table) {
            $table->id();
            $table->string('pair');
            $table->decimal('price', 20, 8);
            $table->string('source')->default('binance');
            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->index(['pair', 'fetched_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reference_prices');
    }
};
