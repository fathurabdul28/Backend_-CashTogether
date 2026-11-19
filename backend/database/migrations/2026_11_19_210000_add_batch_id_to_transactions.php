<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Penanda kelompok: beberapa barang dari satu pesan WhatsApp berbagi batch_id, supaya BATAL membatalkan semuanya. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $t) {
            $t->char('batch_id', 26)->nullable()->after('source')->index();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $t) {
            $t->dropIndex(['batch_id']);
            $t->dropColumn('batch_id');
        });
    }
};
