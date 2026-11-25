<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Setoran yang berasal dari transaksi pengeluaran ("nabung 1 juta" di WhatsApp) ditautkan ke transaksinya. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saving_entries', function (Blueprint $t) {
            $t->foreignId('transaction_id')->nullable()->unique()->after('goal_id')->constrained('transactions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('saving_entries', function (Blueprint $t) {
            $t->dropForeign(['transaction_id']);
            $t->dropUnique(['transaction_id']);
            $t->dropColumn('transaction_id');
        });
    }
};
