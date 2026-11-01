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
        Schema::create('raw_quotes', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('corridor');
            $table->string('trade_size');
            $table->decimal('quoted_output', 20, 8);
            $table->decimal('reference_price', 20, 8);
            $table->decimal('spread_bps', 10, 4);
            $table->jsonb('raw_response')->nullable();
            $table->timestamp('quoted_at');
            $table->jsonb('extra')->nullable();
            $table->timestamps();

            $table->index(['provider', 'corridor', 'trade_size', 'quoted_at']);
            $table->index(['corridor', 'trade_size', 'quoted_at']);
            $table->index('quoted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('raw_quotes');
    }
};
