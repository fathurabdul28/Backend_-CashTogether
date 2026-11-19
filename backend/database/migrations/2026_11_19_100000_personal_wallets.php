<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Saldo, akun, transaksi, dan anggaran jadi pribadi; hanya tabungan yang bersama. Transfer dihapus. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('transactions')->where('type', 'transfer')->delete();

        Schema::table('budgets', function (Blueprint $t) {
            $t->foreignId('user_id')->nullable()->after('household_id')->constrained()->cascadeOnDelete();
            $t->unique(['household_id', 'user_id', 'category_id', 'month'], 'budgets_personal_unique');
        });
        Schema::table('budgets', fn (Blueprint $t) => $t->dropUnique(['household_id', 'category_id', 'month']));
    }

    public function down(): void
    {
        Schema::table('budgets', function (Blueprint $t) {
            $t->unique(['household_id', 'category_id', 'month']);
        });
        Schema::table('budgets', function (Blueprint $t) {
            $t->dropUnique('budgets_personal_unique');
            $t->dropConstrainedForeignId('user_id');
        });
    }
};
