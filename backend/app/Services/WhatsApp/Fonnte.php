<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Fonnte
{
    /** Kirim balasan. Tidak pernah melempar error: webhook harus tetap membalas 200 ke Fonnte. */
    public function send(string $target, string $message): bool
    {
        $token = config('services.fonnte.token');
        if (! $token) {
            Log::warning('FONNTE_TOKEN belum diisi, balasan WhatsApp tidak dikirim.');
            return false;
        }
        try {
            $res = Http::withHeaders(['Authorization' => $token])->asForm()->timeout(10)
                ->post('https://api.fonnte.com/send', ['target' => $target, 'message' => $message, 'countryCode' => '62']);
            if (! $res->successful() || ($res->json('status') === false)) {
                Log::warning('Fonnte menolak pesan', ['status' => $res->status(), 'body' => $res->body()]);
                return false;
            }
            return true;
        } catch (\Throwable $e) {
            Log::warning('Fonnte gagal dihubungi: '.$e->getMessage());
            return false;
        }
    }
}
