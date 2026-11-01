<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('raw_quotes', function (Blueprint $table) {
            $table->uuid('batch_id')->nullable()->after('id')->index();
            $table->integer('size_usd')->nullable()->after('trade_size');
            $table->integer('latency_ms')->nullable()->after('spread_bps');
            $table->boolean('ok')->default(true)->after('latency_ms');
            $table->text('error')->nullable()->after('ok');
        });
    }

    public function down(): void
    {
        Schema::table('raw_quotes', function (Blueprint $table) {
            $table->dropColumn(['batch_id', 'size_usd', 'latency_ms', 'ok', 'error']);
        });
    }
};
