<?php

namespace App\Http\Controllers;

use App\Services\WhatsApp\BotHandler;
use App\Services\WhatsApp\Fonnte;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class WhatsAppController extends Controller
{
    /** Webhook Fonnte. Alamatnya memuat rahasia; selalu balas 200 supaya Fonnte tidak mengulang. */
    public function webhook(Request $r, string $secret, BotHandler $bot, Fonnte $fonnte)
    {
        $expected = (string) config('services.fonnte.webhook_secret');
        abort_if($expected === '' || ! hash_equals($expected, $secret), 404);

        $sender = (string) $r->input('sender', '');
        $message = (string) $r->input('message', '');
        if ($sender === '' || trim($message) === '' || $r->filled('member')) { // kosong atau pesan grup
            return response()->json(['ok' => true]);
        }

        // Fonnte bisa mengirim ulang pesan yang sama; proses sekali saja
        $key = 'wa:'.sha1($sender.'|'.($r->input('inboxid') ?: $r->input('timestamp') ?: $message));
        if (! Cache::add($key, 1, now()->addMinutes(10))) {
            return response()->json(['ok' => true, 'duplicate' => true]);
        }

        try {
            $reply = $bot->handle($sender, $message);
        } catch (\Throwable $e) {
            report($e);
            $reply = 'Ada yang tidak beres. Coba lagi sebentar ya.';
        }
        $fonnte->send(BotHandler::normalizePhone($sender), $reply);
        return response()->json(['ok' => true]);
    }

    /** Cek keberadaan URL (dipakai dashboard Fonnte). Tidak memproses apa pun. */
    public function ping(string $secret)
    {
        $expected = (string) config('services.fonnte.webhook_secret');
        abort_if($expected === '' || ! hash_equals($expected, $secret), 404);
        return response()->json(['ok' => true]);
    }

    public function status(Request $r)
    {
        $u = $r->user();
        $codeActive = $u->wa_link_code && $u->wa_link_expires_at?->isFuture();
        return [
            'linked' => (bool) $u->phone,
            'phone' => $u->phone ? substr($u->phone, 0, 4).str_repeat('•', max(0, strlen($u->phone) - 7)).substr($u->phone, -3) : null,
            'bot_number' => config('services.fonnte.bot_number'),
            'code' => $codeActive ? $u->wa_link_code : null,
            'expires_at' => $codeActive ? $u->wa_link_expires_at : null,
        ];
    }

    public function linkCode(Request $r)
    {
        $u = $r->user();
        $u->forceFill(['wa_link_code' => str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT), 'wa_link_expires_at' => now()->addMinutes(15)])->save();
        return $this->status($r->setUserResolver(fn () => $u->fresh()));
    }

    public function unlink(Request $r)
    {
        $r->user()->forceFill(['phone' => null, 'wa_link_code' => null, 'wa_link_expires_at' => null])->save();
        return response()->noContent();
    }
}
