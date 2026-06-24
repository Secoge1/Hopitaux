<?php
/**
 * Imprimante thermique ESC/POS — Xprinter XP-80TS et compatibles (80 mm, réseau port 9100).
 */

class EscPosPrinter
{
    private string $buffer = '';
    private int $lineWidth = 48;

    public function __construct(int $lineWidth = 48)
    {
        $this->lineWidth = max(32, min(64, $lineWidth));
    }

    public function getBuffer(): string
    {
        return $this->buffer;
    }

    public function clear(): void
    {
        $this->buffer = '';
    }

    public function init(): self
    {
        $this->buffer .= "\x1B\x40";
        $this->selectCodePage();
        return $this;
    }

    /** Sélection page de codes Windows-1252 (accents français sur Xprinter). */
    public function selectCodePage(int $page = 16): self
    {
        $this->buffer .= "\x1B\x74" . chr(max(0, min(255, $page)));
        return $this;
    }

    public function align(string $mode = 'left'): self
    {
        $map = ['left' => 0, 'center' => 1, 'right' => 2];
        $this->buffer .= "\x1B\x61" . chr($map[$mode] ?? 0);
        return $this;
    }

    public function bold(bool $on = true): self
    {
        $this->buffer .= "\x1B\x45" . chr($on ? 1 : 0);
        return $this;
    }

    public function size(int $width = 1, int $height = 1): self
    {
        $width = max(1, min(8, $width));
        $height = max(1, min(8, $height));
        $n = (($width - 1) << 4) | ($height - 1);
        $this->buffer .= "\x1D\x21" . chr($n);
        return $this;
    }

    public function normal(): self
    {
        return $this->bold(false)->size(1, 1);
    }

    public function text(string $line): self
    {
        $this->buffer .= $this->encode($line) . "\n";
        return $this;
    }

    /** Texte en double taille avec retour à la ligne adapté à la largeur papier. */
    public function textLarge(string $text, bool $center = false): self
    {
        $effectiveWidth = max(8, (int) floor($this->lineWidth / 2));
        $lines = explode("\n", wordwrap(trim($text), $effectiveWidth, "\n", true));
        if ($center) {
            $this->align('center');
        }
        foreach ($lines as $line) {
            $this->bold(true)->size(2, 2)->text($line)->normal();
        }
        return $this;
    }

    public function textWrapped(string $text): self
    {
        foreach ($this->wrap($text) as $line) {
            $this->text($line);
        }
        return $this;
    }

    public function feed(int $lines = 1): self
    {
        $this->buffer .= "\x1B\x64" . chr(max(0, min(255, $lines)));
        return $this;
    }

    public function separator(string $char = '-'): self
    {
        $this->text(str_repeat($char, $this->lineWidth));
        return $this;
    }

    public function cut(bool $partial = false): self
    {
        $this->feed(3);
        $this->buffer .= $partial ? "\x1D\x56\x01" : "\x1D\x56\x00";
        return $this;
    }

    /**
     * Imprime une image (PNG/JPEG/GIF/WEBP) en raster ESC/POS (GS v 0).
     */
    public function image(string $path, int $maxWidthPx = 384): self
    {
        if (!extension_loaded('gd') || !is_readable($path)) {
            return $this;
        }

        $info = @getimagesize($path);
        if ($info === false) {
            return $this;
        }

        $src = $this->loadImage($path, (int) $info[2]);
        if ($src === false) {
            return $this;
        }

        return $this->appendRasterImage($src, $maxWidthPx);
    }

    /**
     * @param resource|\GdImage $src
     */
    public function imageResource($src, int $maxWidthPx = 384): self
    {
        if (!extension_loaded('gd') || (!is_object($src) && !is_resource($src))) {
            return $this;
        }

        return $this->appendRasterImage($src, $maxWidthPx);
    }

    /**
     * @param resource|\GdImage $src
     */
    private function appendRasterImage($src, int $maxWidthPx): self
    {
        $srcW = imagesx($src);
        $srcH = imagesy($src);
        if ($srcW <= 0 || $srcH <= 0) {
            if (is_object($src) || is_resource($src)) {
                imagedestroy($src);
            }
            return $this;
        }

        $maxWidthPx = max(96, min(576, $maxWidthPx));
        $dstW = min($maxWidthPx, $srcW);
        $dstH = max(1, (int) round($srcH * ($dstW / $srcW)));
        $dstH = min($dstH, 220);

        $dst = imagecreatetruecolor($dstW, $dstH);
        if ($dst === false) {
            imagedestroy($src);
            return $this;
        }

        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefill($dst, 0, 0, $white);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
        imagedestroy($src);

        $widthBytes = (int) ceil($dstW / 8);
        $raster = '';
        for ($y = 0; $y < $dstH; $y++) {
            for ($xByte = 0; $xByte < $widthBytes; $xByte++) {
                $byte = 0;
                for ($bit = 0; $bit < 8; $bit++) {
                    $x = ($xByte * 8) + $bit;
                    if ($x >= $dstW) {
                        continue;
                    }
                    $rgba = imagecolorat($dst, $x, $y);
                    $alpha = ($rgba & 0x7F000000) >> 24;
                    $r = ($rgba >> 16) & 0xFF;
                    $g = ($rgba >> 8) & 0xFF;
                    $b = $rgba & 0xFF;
                    $gray = (int) ($r * 0.299 + $g * 0.587 + $b * 0.114);
                    if ($alpha > 48) {
                        $gray = 255;
                    }
                    if ($gray < 140) {
                        $byte |= (0x80 >> $bit);
                    }
                }
                $raster .= chr($byte);
            }
        }
        imagedestroy($dst);

        $this->align('center');
        $this->buffer .= "\x1D\x76\x30\x00"
            . chr($widthBytes & 0xFF) . chr(($widthBytes >> 8) & 0xFF)
            . chr($dstH & 0xFF) . chr(($dstH >> 8) & 0xFF)
            . $raster;
        $this->feed(1);

        return $this;
    }

    /** @return resource|\GdImage|false */
    private function loadImage(string $path, int $type)
    {
        switch ($type) {
            case IMAGETYPE_PNG:
                return @imagecreatefrompng($path);
            case IMAGETYPE_JPEG:
                return @imagecreatefromjpeg($path);
            case IMAGETYPE_GIF:
                return @imagecreatefromgif($path);
            case IMAGETYPE_WEBP:
                return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false;
            default:
                return false;
        }
    }

    /** @return array{ok:bool,error?:string} */
    public static function sendToNetwork(string $host, int $port, string $data, int $timeout = 5): array
    {
        $host = trim($host);
        if ($host === '') {
            return ['ok' => false, 'error' => 'Adresse IP imprimante non configurée.'];
        }
        $port = max(1, min(65535, $port));

        $errno = 0;
        $errstr = '';
        $fp = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if (!$fp) {
            return ['ok' => false, 'error' => "Connexion impossible ($host:$port) : $errstr ($errno)"];
        }
        stream_set_timeout($fp, $timeout);
        $written = fwrite($fp, $data);
        fclose($fp);
        if ($written === false || $written < strlen($data)) {
            return ['ok' => false, 'error' => 'Échec envoi des données vers l\'imprimante.'];
        }
        return ['ok' => true];
    }

    /** @return list<string> */
    private function wrap(string $text): array
    {
        $text = preg_replace('/\s+/u', ' ', trim($text)) ?? '';
        if ($text === '') {
            return [''];
        }
        return explode("\n", wordwrap($text, $this->lineWidth, "\n", true));
    }

    private function encode(string $text): string
    {
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
        return $converted !== false ? $converted : $text;
    }
}
