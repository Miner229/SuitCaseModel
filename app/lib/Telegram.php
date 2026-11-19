<?php
declare(strict_types=1);

final class Telegram
{
    public static function configured(): bool
    {
        return Env::get('TELEGRAM_BOT_TOKEN') !== '' && Env::get('TELEGRAM_CHAT_ID') !== '';
    }

    public static function sendLead(array $l): bool
    {
        $lines = [
            '🧳 <b>Новая заявка #' . (int)$l['id'] . '</b> — ' . self::h(Leads::TYPES[$l['type']] ?? $l['type']),
            '',
            '👤 ' . self::h($l['name'] !== '' ? $l['name'] : 'Без имени'),
            '📞 <a href="tel:' . self::h($l['phone']) . '">' . self::h(phone_display($l['phone'])) . '</a>',
        ];
        if (!empty($l['product_name'])) {
            $lines[] = '🛒 ' . self::h($l['product_name']) . ' — ' . self::h(price_fmt($l['product_price']));
            $lines[] = '🔗 ' . self::h(base_url('/product/' . $l['product_slug'] . '/'));
        }
        if ($l['city'] !== '') $lines[] = '📍 ' . self::h($l['city']);
        if ($l['message'] !== '') $lines[] = '💬 ' . self::h($l['message']);
        if ($l['page_url'] !== '') $lines[] = '🌐 Страница: ' . self::h($l['page_url']);
        if ($l['utm'] !== '') $lines[] = '📈 ' . self::h($l['utm']);
        $lines[] = '🕒 ' . self::h(date('d.m.Y H:i', strtotime($l['created_at'])));
        return self::send(implode("\n", $lines));
    }

    public static function send(string $html): bool
    {
        if (!self::configured()) {
            return false;
        }
        $url = rtrim(Env::get('TELEGRAM_API_BASE', 'https://api.telegram.org'), '/')
            . '/bot' . Env::get('TELEGRAM_BOT_TOKEN') . '/sendMessage';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'chat_id' => Env::get('TELEGRAM_CHAT_ID'),
                'text' => mb_substr($html, 0, 4000),
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => 'true',
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => 6,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $resp = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        $ok = $code === 200 && is_string($resp) && (json_decode($resp, true)['ok'] ?? false) === true;
        if (!$ok) {
            // В лог не пишем токен — только код ответа и описание ошибки
            error_log('Telegram send failed: HTTP ' . $code . ' ' . $err . ' ' . (is_string($resp) ? substr($resp, 0, 300) : ''));
        }
        return $ok;
    }

    private static function h(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
