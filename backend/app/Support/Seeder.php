<?php

namespace App\Support;

use App\Models\Account;
use App\Models\Category;

class Seeder
{
    public const EXPENSE = [
        ['Makan & Minum', 'ForkKnife', 'clay'], ['Belanja Harian', 'ShoppingCart', 'mustard'],
        ['Transportasi', 'Motorcycle', 'teal'], ['Tagihan & Listrik', 'Lightning', 'mustard'],
        ['Rumah', 'House', 'stone'], ['Kesehatan', 'FirstAidKit', 'clay'], ['Hiburan', 'FilmSlate', 'plum'],
        ['Pendidikan', 'BookOpen', 'teal'], ['Pulsa & Internet', 'WifiHigh', 'teal'], ['Belanja', 'Bag', 'plum'],
        ['Hadiah & Sosial', 'Gift', 'plum'], ['Cicilan', 'CreditCard', 'stone'], ['Lainnya', 'DotsThree', 'stone'],
    ];
    public const INCOME = [
        ['Gaji', 'Briefcase', 'sage'], ['Bonus', 'Sparkle', 'sage'], ['Usaha', 'Storefront', 'sage'],
        ['Investasi', 'ChartLineUp', 'teal'], ['Hadiah', 'Gift', 'plum'], ['Lainnya', 'DotsThree', 'stone'],
    ];

    /** Kategori dipakai bersama; tiap anggota punya akun "Tunai" sendiri. */
    public static function household(int $householdId, int $userId): void
    {
        foreach (['expense' => self::EXPENSE, 'income' => self::INCOME] as $kind => $list) {
            foreach ($list as $i => [$name, $icon, $color]) {
                Category::create(compact('name', 'icon', 'color') + ['household_id' => $householdId, 'kind' => $kind, 'sort_order' => $i]);
            }
        }
        self::personalAccount($householdId, $userId);
    }

    public static function personalAccount(int $householdId, int $userId): void
    {
        Account::create(['household_id' => $householdId, 'owner_id' => $userId, 'name' => 'Tunai', 'type' => 'cash']);
    }
}
