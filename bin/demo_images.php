<?php
declare(strict_types=1);

/** Рисует демо-фото чемодана (1200×1200 PNG) — заглушка, пока заказчик не загрузит реальные фото. */
function demo_suitcase_png(string $out, string $hex, string $size, string $view, bool $soft): void
{
    $W = 1200;
    $im = imagecreatetruecolor($W, $W);
    imageantialias($im, true);
    [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');
    // фон — мягкий градиент
    for ($y = 0; $y < $W; $y++) {
        $t = $y / $W;
        $c = imagecolorallocate($im, (int)(244 - 10 * $t), (int)(247 - 8 * $t), (int)(247 - 4 * $t));
        imageline($im, 0, $y, $W, $y, $c);
    }
    $shade = fn(float $k) => imagecolorallocate($im, (int)max(0, min(255, $r * $k)), (int)max(0, min(255, $g * $k)), (int)max(0, min(255, $b * $k)));
    $body = $shade(1.0);
    $dark = $shade(0.72);
    $light = $shade(1.25);
    $black = imagecolorallocate($im, 40, 44, 48);
    $metal = imagecolorallocate($im, 160, 168, 172);
    $shadow = imagecolorallocatealpha($im, 0, 0, 0, 100);

    $sets = $size === 'SET' ? [['L', 140], ['M', 520], ['S', 830]] : [[$size, null]];
    foreach ($sets as [$sz, $x0]) {
        $hRatio = ['S' => .52, 'M' => .64, 'L' => .76, 'XL' => .82][$sz] ?? .64;
        $h = (int)($W * $hRatio * ($size === 'SET' ? .9 : 1));
        $w = (int)($h * ($view === 'side' ? .42 : .66));
        if ($view === 'open') {
            $w = (int)($h * .66);
        }
        $x = $x0 ?? (int)(($W - $w) / 2);
        $y = (int)($W * .9 - $h - 50);
        // тень
        imagefilledellipse($im, $x + (int)($w / 2), (int)($W * .9), (int)($w * 1.15), 60, $shadow);
        // телескопическая ручка
        imagefilledrectangle($im, $x + (int)($w * .3), $y - 170, $x + (int)($w * .3) + 14, $y, $metal);
        imagefilledrectangle($im, $x + (int)($w * .7) - 14, $y - 170, $x + (int)($w * .7), $y, $metal);
        imagefilledrectangle($im, $x + (int)($w * .3) - 6, $y - 190, $x + (int)($w * .7) + 6, $y - 160, $black);
        // корпус
        rounded($im, $x, $y, $x + $w, $y + $h, 48, $body);
        if ($view === 'open') {
            rounded($im, $x + 30, $y + 30, $x + $w - 30, $y + $h - 30, 30, $dark);
            imagesetthickness($im, 6);
            imageline($im, $x + 50, $y + (int)($h * .5), $x + $w - 50, $y + (int)($h * .5), $metal);
            imagesetthickness($im, 1);
            $strap = imagecolorallocate($im, 220, 220, 220);
            imagefilledrectangle($im, $x + (int)($w * .2), $y + 40, $x + (int)($w * .2) + 22, $y + $h - 40, $strap);
            imagefilledrectangle($im, $x + (int)($w * .8) - 22, $y + 40, $x + (int)($w * .8), $y + $h - 40, $strap);
        } elseif ($soft) {
            rounded($im, $x + 50, $y + (int)($h * .45), $x + $w - 50, $y + $h - 60, 24, $dark);
            imagesetthickness($im, 5);
            imageline($im, $x + 60, $y + (int)($h * .45) + 40, $x + $w - 60, $y + (int)($h * .45) + 40, $black);
            imagesetthickness($im, 1);
        } else {
            // рёбра жёсткости
            imagesetthickness($im, 10);
            $n = $view === 'side' ? 2 : 4;
            for ($i = 1; $i <= $n; $i++) {
                $lx = $x + (int)($w * $i / ($n + 1));
                imageline($im, $lx, $y + 40, $lx, $y + $h - 40, $i % 2 ? $dark : $light);
            }
            imagesetthickness($im, 1);
        }
        // молния по контуру
        if ($view !== 'open') {
            imagesetthickness($im, 4);
            imagerectangle($im, $x + 14, $y + 14, $x + $w - 14, $y + $h - 14, $dark);
            imagesetthickness($im, 1);
        }
        // верхняя ручка
        imagefilledrectangle($im, $x + (int)($w * .38), $y - 22, $x + (int)($w * .62), $y + 4, $black);
        // колёса
        foreach ($view === 'side' ? [.3, .75] : [.14, .86] as $wx) {
            $cx = $x + (int)($w * $wx);
            imagefilledrectangle($im, $cx - 18, $y + $h - 6, $cx + 18, $y + $h + 18, $black);
            imagefilledellipse($im, $cx, $y + $h + 34, 46, 46, $black);
            imagefilledellipse($im, $cx, $y + $h + 34, 18, 18, $metal);
        }
    }
    imagepng($im, $out, 6);
    imagedestroy($im);
}

function rounded($im, int $x1, int $y1, int $x2, int $y2, int $r, int $col): void
{
    imagefilledrectangle($im, $x1 + $r, $y1, $x2 - $r, $y2, $col);
    imagefilledrectangle($im, $x1, $y1 + $r, $x2, $y2 - $r, $col);
    foreach ([[$x1 + $r, $y1 + $r], [$x2 - $r, $y1 + $r], [$x1 + $r, $y2 - $r], [$x2 - $r, $y2 - $r]] as [$cx, $cy]) {
        imagefilledellipse($im, $cx, $cy, $r * 2, $r * 2, $col);
    }
}
