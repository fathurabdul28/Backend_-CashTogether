<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('gender', 10)->default('male')->after('email'); // male = biru, female = pink
            $t->string('identity_override', 10)->nullable();
        });

        Schema::create('households', function (Blueprint $t) {
            $t->id();
            $t->string('name')->default('Dompet Kita');
            $t->char('invite_code', 6)->nullable()->unique();
            $t->timestamp('invite_expires_at')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('household_members', function (Blueprint $t) {
            $t->foreignId('household_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('role', 10); // owner | partner
            $t->string('color_variant', 10)->default('default'); // default | alt
            $t->timestamp('joined_at')->useCurrent();
            $t->primary(['household_id', 'user_id']);
            $t->unique('user_id'); // satu user = satu dompet
        });

        Schema::create('accounts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('household_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('type', 10); // cash|bank|ewallet|credit
            $t->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete(); // null = bersama
            $t->bigInteger('initial_balance')->default(0);
            $t->boolean('archived')->default(false);
            $t->timestamps();
        });

        Schema::create('categories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('household_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('kind', 10); // income|expense
            $t->string('icon');
            $t->string('color');
            $t->integer('sort_order')->default(0);
            $t->boolean('archived')->default(false);
        });

        Schema::create('saving_goals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('household_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->bigInteger('target_amount');
            $t->date('target_date')->nullable();
            $t->string('icon');
            $t->string('color');
            $t->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamp('achieved_at')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('saving_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('goal_id')->constrained('saving_goals')->cascadeOnDelete();
            $t->bigInteger('amount'); // negatif = penarikan
            $t->foreignId('contributed_by')->constrained('users');
            $t->timestamp('occurred_at')->useCurrent();
            $t->string('note')->nullable();
            $t->timestamps();
        });

        Schema::create('transactions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('household_id')->constrained()->cascadeOnDelete();
            $t->string('type', 10); // income|expense|transfer
            $t->bigInteger('amount');
            $t->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $t->foreignId('account_id')->constrained('accounts');
            $t->foreignId('to_account_id')->nullable()->constrained('accounts');
            $t->foreignId('saving_goal_id')->nullable()->constrained('saving_goals')->nullOnDelete();
            $t->timestamp('occurred_at');
            $t->text('note')->nullable();
            $t->string('receipt_path')->nullable();
            $t->foreignId('created_by')->constrained('users');
            $t->foreignId('paid_by')->nullable()->constrained('users');
            $t->foreignId('updated_by')->nullable()->constrained('users');
            $t->timestamps();
            $t->softDeletes();
            $t->index(['household_id', 'occurred_at']);
            $t->index(['household_id', 'updated_at']); // untuk polling ?since=
        });

        Schema::create('budgets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('household_id')->constrained()->cascadeOnDelete();
            $t->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $t->date('month'); // tanggal 1
            $t->bigInteger('limit_amount');
            $t->timestamps();
            $t->unique(['household_id', 'category_id', 'month']);
        });
    }

    public function down(): void
    {
        foreach (['budgets','transactions','saving_entries','saving_goals','categories','accounts','household_members','households'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['gender', 'identity_override']));
    }
};
