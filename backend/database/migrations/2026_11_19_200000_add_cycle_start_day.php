<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Tanggal mulai periode keuangan per orang (mis. 25 = tanggal gajian). 1 = bulan kalender biasa. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->unsignedTinyInteger('cycle_start_day')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('cycle_start_day'));
    }
};
