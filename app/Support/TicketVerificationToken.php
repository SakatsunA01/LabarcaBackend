<?php

namespace App\Support;

use App\Models\TicketOrder;

class TicketVerificationToken
{
    public static function make(TicketOrder $order): string
    {
        $payload = [
            'order_id' => $order->id,
            'event_id' => $order->event_id,
            'quantity' => (int) $order->quantity,
            'mp_payment_id' => $order->mp_payment_id,
            'issued_at' => now()->timestamp,
        ];

        $payloadJson = json_encode($payload);
        $payloadB64 = self::base64UrlEncode($payloadJson);
        $signature = hash_hmac('sha256', $payloadB64, config('app.key'));

        return $payloadB64 . '.' . $signature;
    }

    public static function parse(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return null;
        }

        [$payloadB64, $signature] = $parts;
        $expected = hash_hmac('sha256', $payloadB64, config('app.key'));

        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $payloadJson = self::base64UrlDecode($payloadB64);
        if ($payloadJson === null) {
            return null;
        }

        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            return null;
        }

        return $payload;
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): ?string
    {
        $pad = 4 - (strlen($value) % 4);
        if ($pad < 4) {
            $value .= str_repeat('=', $pad);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        return $decoded === false ? null : $decoded;
    }
}
