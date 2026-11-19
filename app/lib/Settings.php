<?php
declare(strict_types=1);

/** Настройки сайта, которые заказчик меняет в админке (контакты, счётчики, коды верификации). */
final class Settings
{
    public const DEFAULTS = [
        'site_name' => 'Чемодан.kz',
        'site_tagline' => 'Чемоданы для путешествий с доставкой по Казахстану',
        'phone' => '+77001234567',
        'whatsapp' => '77001234567',
        'telegram' => 'chemodan_kz',
        'email' => 'info@example.kz',
        'address' => 'г. Алматы, пр. Абая, 1 (демо-адрес)',
        'work_hours' => 'Ежедневно 10:00–20:00',
        'city_default' => 'Алматы',
        'instagram' => '',
        'metrika_id' => '',
        'yandex_verification' => '',
        'google_verification' => '',
        'consent_version' => '1.0',
        'company_legal' => 'ИП «Демо», БИН 000000000000',
        'popup_delay' => '40',
    ];

    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = self::DEFAULTS;
            try {
                foreach (Db::all('SELECT k, v FROM settings') as $r) {
                    self::$cache[$r['k']] = $r['v'];
                }
            } catch (PDOException) {
                // БД ещё не установлена — работаем на значениях по умолчанию
            }
        }
        return self::$cache;
    }

    public static function get(string $k): string
    {
        return (string)(self::all()[$k] ?? '');
    }

    public static function set(string $k, string $v): void
    {
        Db::run('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)', [$k, $v]);
        self::$cache = null;
    }
}
