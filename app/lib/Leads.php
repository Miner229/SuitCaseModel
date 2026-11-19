<?php
declare(strict_types=1);

final class Leads
{
    public const TYPES = [
        'callback' => 'Обратный звонок',
        'order' => 'Заказ товара',
        'consult' => 'Подбор чемодана',
        'popup' => 'Всплывающее окно (скидка)',
    ];
    public const STATUSES = ['new' => 'Новая', 'work' => 'В работе', 'done' => 'Завершена', 'spam' => 'Спам'];

    /**
     * Принимает заявку из публичной формы.
     * @return array{ok:bool, errors?:array<string,string>, id?:int, code?:int}
     */
    public static function submit(array $in): array
    {
        // 1. Honeypot: скрытое поле, которое заполняют только боты. Отвечаем «успехом», чтобы бот не подбирал обход.
        if (trim((string)($in['website'] ?? '')) !== '') {
            return ['ok' => true, 'id' => 0];
        }
        // 2. Подписанный токен формы: защита от CSRF и мгновенной отправки ботом
        $tokErr = Security::verifyFormToken((string)($in['_ft'] ?? ''));
        if ($tokErr === 'bad_token' || $tokErr === 'expired') {
            return ['ok' => false, 'code' => 400, 'errors' => ['form' => 'Форма устарела. Обновите страницу и отправьте ещё раз.']];
        }
        if ($tokErr === 'too_fast') {
            return ['ok' => false, 'code' => 400, 'errors' => ['form' => 'Слишком быстрая отправка. Попробуйте ещё раз.']];
        }
        // 3. Лимит: не более 5 заявок с одного IP за 10 минут и 20 за сутки
        $ip = client_ip();
        if (!Security::rateLimit('lead10m:' . $ip, 5, 600) || !Security::rateLimit('lead1d:' . $ip, 20, 86400)) {
            return ['ok' => false, 'code' => 429, 'errors' => ['form' => 'Слишком много заявок. Позвоните нам или попробуйте позже.']];
        }

        // 4. Валидация
        $errors = [];
        $type = (string)($in['type'] ?? 'callback');
        if (!isset(self::TYPES[$type])) {
            $type = 'callback';
        }
        $name = trim(preg_replace('/[\x00-\x1F\x7F<>]/u', '', (string)($in['name'] ?? '')));
        if (mb_strlen($name) > 100) {
            $errors['name'] = 'Имя слишком длинное';
        }
        $phone = normalize_phone((string)($in['phone'] ?? ''));
        if ($phone === null) {
            $errors['phone'] = 'Укажите корректный номер телефона, например +7 700 123-45-67';
        }
        $message = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string)($in['message'] ?? '')));
        if (mb_strlen($message) > 1000) {
            $errors['message'] = 'Сообщение не длиннее 1000 символов';
        }
        if (preg_match_all('~https?://~i', $message) > 1) {
            $errors['message'] = 'Ссылки в сообщении не допускаются';
        }
        // 5. Согласие на обработку персональных данных (Закон РК №94-V «О персональных данных и их защите»)
        if (empty($in['consent'])) {
            $errors['consent'] = 'Необходимо согласие на сбор и обработку персональных данных';
        }
        $productId = null;
        if (!empty($in['product_id']) && ctype_digit((string)$in['product_id'])) {
            $productId = Db::value('SELECT id FROM products WHERE id = ?', [(int)$in['product_id']]);
            $productId = $productId ? (int)$productId : null;
        }
        if ($errors) {
            return ['ok' => false, 'code' => 422, 'errors' => $errors];
        }

        $city = mb_substr(trim(strip_tags((string)($in['city'] ?? ''))), 0, 80);
        $pageUrl = mb_substr((string)($in['page'] ?? ''), 0, 500);
        if ($pageUrl !== '' && !preg_match('~^/[^\s]*$~', $pageUrl)) {
            $pageUrl = '';
        }
        $utm = mb_substr(preg_replace('/[^\w\-=&.%]/u', '', (string)($in['utm'] ?? '')), 0, 500);

        Db::run('INSERT INTO leads (type, name, phone, message, product_id, city, page_url, utm, ip, user_agent, consent_at, consent_version)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)', [
            $type, $name, $phone, $message, $productId, $city, $pageUrl, $utm, $ip,
            mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 300), Settings::get('consent_version'),
        ]);
        $id = Db::lastId();
        $lead = self::find($id);
        if ($lead && Telegram::sendLead($lead)) {
            Db::run('UPDATE leads SET tg_sent = 1 WHERE id = ?', [$id]);
        }
        return ['ok' => true, 'id' => $id];
    }

    public static function find(int $id): ?array
    {
        return Db::one('SELECT l.*, p.name AS product_name, p.slug AS product_slug, p.price AS product_price
            FROM leads l LEFT JOIN products p ON p.id = l.product_id WHERE l.id = ?', [$id]);
    }
}
