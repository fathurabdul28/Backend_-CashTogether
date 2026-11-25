<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SavingEntry extends Model
{
    protected $guarded = [];
    protected $casts = ['from_balance' => 'boolean', 'gold_grams' => 'float'];
    protected $appends = ['is_conversion'];

    /** Konversi rupiah → emas: rupiah keluar dari tabungan, gram masuk. Saldo dompet tidak tersentuh. */
    public function getIsConversionAttribute(): bool
    {
        return ! ($this->from_balance ?? true) && $this->amount < 0 && $this->gold_grams > 0;
    }
}
