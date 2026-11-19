<?php
declare(strict_types=1);

final class View
{
    public static function render(string $tpl, array $vars = [], ?string $layout = 'layout'): string
    {
        $html = self::partial($tpl, $vars);
        if ($layout === null) {
            return $html;
        }
        return self::partial($layout, $vars + ['content' => $html]);
    }

    public static function partial(string $tpl, array $vars = []): string
    {
        if (!preg_match('~^[a-z0-9_/\-]+$~', $tpl)) {
            throw new InvalidArgumentException('Bad template name');
        }
        extract($vars, EXTR_SKIP);
        ob_start();
        require APP . '/views/' . $tpl . '.php';
        return (string)ob_get_clean();
    }

    public static function page(string $tpl, array $vars, int $code = 200): never
    {
        http_response_code($code);
        Security::sendHeaders();
        header('Content-Type: text/html; charset=utf-8');
        echo self::render($tpl, $vars);
        exit;
    }
}
