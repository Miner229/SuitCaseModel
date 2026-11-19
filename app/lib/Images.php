<?php
declare(strict_types=1);

/**
 * Загрузка фото товаров. Любой загруженный файл перекодируется через GD в WebP —
 * это убирает EXIF/вредоносный «хвост» и даёт оптимизированные размеры 400/800/1200 px.
 */
final class Images
{
    public const SIZES = [400, 800, 1200];
    public const MAX_BYTES = 10 * 1024 * 1024;
    private const ALLOWED = ['image/jpeg', 'image/png', 'image/webp'];

    public static function url(string $file, int $w = 800): string
    {
        if (!in_array($w, self::SIZES, true)) {
            $w = 800;
        }
        return '/uploads/' . rawurlencode($file) . '-' . $w . '.webp';
    }

    public static function srcset(string $file): string
    {
        return implode(', ', array_map(fn($w) => self::url($file, $w) . ' ' . $w . 'w', self::SIZES));
    }

    /** @return array{file:string,width:int,height:int} */
    public static function storeUpload(array $f): array
    {
        if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
            throw new RuntimeException('Файл не загружен (код ' . (int)($f['error'] ?? -1) . ')');
        }
        if ($f['size'] > self::MAX_BYTES) {
            throw new RuntimeException('Файл больше 10 МБ');
        }
        return self::storeFile($f['tmp_name']);
    }

    /** @return array{file:string,width:int,height:int} */
    public static function storeFile(string $path): array
    {
        // Тип определяем по содержимому, а не по расширению/заголовку от браузера
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
        if (!in_array($mime, self::ALLOWED, true)) {
            throw new RuntimeException('Допустимы только JPG, PNG и WebP');
        }
        $info = @getimagesize($path);
        if (!$info || $info[0] < 200 || $info[1] < 200 || $info[0] > 8000 || $info[1] > 8000) {
            throw new RuntimeException('Изображение должно быть от 200×200 до 8000×8000 px');
        }
        $src = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
        };
        if (!$src) {
            throw new RuntimeException('Не удалось прочитать изображение');
        }
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data($path);
            $src = match ((int)($exif['Orientation'] ?? 1)) {
                3 => imagerotate($src, 180, 0),
                6 => imagerotate($src, -90, 0),
                8 => imagerotate($src, 90, 0),
                default => $src,
            };
        }
        if (!is_dir(UPLOADS)) {
            mkdir(UPLOADS, 0755, true);
        }
        $name = bin2hex(random_bytes(10));
        $w = imagesx($src);
        $h = imagesy($src);
        foreach (self::SIZES as $target) {
            $tw = min($target, $w);
            $th = (int)round($h * $tw / $w);
            $dst = imagecreatetruecolor($tw, $th);
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 255, 255, 255, 127));
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $tw, $th, $w, $h);
            imagewebp($dst, UPLOADS . "/$name-$target.webp", 80);
            imagedestroy($dst);
        }
        imagedestroy($src);
        $outW = min(1200, $w);
        return ['file' => $name, 'width' => $outW, 'height' => (int)round($h * $outW / $w)];
    }

    public static function delete(string $file): void
    {
        if (!preg_match('/^[a-f0-9]{20}$/', $file)) {
            return; // защита от path traversal
        }
        foreach (self::SIZES as $s) {
            @unlink(UPLOADS . "/$file-$s.webp");
        }
    }
}
