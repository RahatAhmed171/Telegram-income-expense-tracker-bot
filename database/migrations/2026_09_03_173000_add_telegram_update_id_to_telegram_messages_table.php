<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_messages', function (Blueprint $table) {
            $table->bigInteger('telegram_update_id')
                ->unique()
                ->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_messages', function (Blueprint $table) {
            $table->dropUnique([
                'telegram_messages_telegram_update_id_unique'
            ]);

            $table->dropColumn('telegram_update_id');
        });
    }
};