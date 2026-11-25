<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Rincian tabungan dari daftar chat: tidak memotong saldo, bisa berupa emas (gram), dan bisa dibatalkan per pesan. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saving_entries', function (Blueprint $t) {
            $t->boolean('from_balance')->default(true)->after('amount');
            $t->decimal('gold_grams', 8, 2)->nullable()->after('from_balance');
            $t->char('batch_id', 26)->nullable()->after('gold_grams')->index();
        });
    }

    public function down(): void
    {
        Schema::table('saving_entries', function (Blueprint $t) {
            $t->dropIndex(['batch_id']);
            $t->dropColumn(['from_balance', 'gold_grams', 'batch_id']);
        });
    }
};
