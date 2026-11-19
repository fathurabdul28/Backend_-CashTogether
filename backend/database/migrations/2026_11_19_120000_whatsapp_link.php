<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('phone', 20)->nullable()->unique()->after('email'); // format 62812...
            $t->char('wa_link_code', 6)->nullable();
            $t->timestamp('wa_link_expires_at')->nullable();
        });
        Schema::table('transactions', function (Blueprint $t) {
            $t->string('source', 10)->default('app')->after('note'); // app | whatsapp
        });
    }

    public function down(): void
    {
        Schema::table('transactions', fn (Blueprint $t) => $t->dropColumn('source'));
        Schema::table('users', function (Blueprint $t) {
            $t->dropUnique(['phone']);
            $t->dropColumn(['phone', 'wa_link_code', 'wa_link_expires_at']);
        });
    }
};
