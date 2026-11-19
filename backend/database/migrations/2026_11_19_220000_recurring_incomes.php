<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Pemasukan rutin (mis. gaji): dicatat otomatis tiap bulan pada tanggal tertentu. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_incomes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('household_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $t->foreignId('account_id')->constrained('accounts');
            $t->unsignedBigInteger('amount');
            $t->unsignedTinyInteger('day');              // tanggal masuk tiap bulan (1–28)
            $t->string('note', 500)->nullable();
            $t->boolean('active')->default(true);
            $t->char('next_month', 7);                   // bulan kalender (YYYY-MM) berikutnya yang belum dibuatkan
            $t->timestamps();
        });

        Schema::table('transactions', function (Blueprint $t) {
            $t->foreignId('recurring_id')->nullable()->after('batch_id')->constrained('recurring_incomes')->nullOnDelete();
            $t->char('recurring_month', 7)->nullable()->after('recurring_id');
            $t->unique(['recurring_id', 'recurring_month']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $t) {
            $t->dropUnique(['recurring_id', 'recurring_month']);
            $t->dropConstrainedForeignId('recurring_id');
            $t->dropColumn('recurring_month');
        });
        Schema::dropIfExists('recurring_incomes');
    }
};
