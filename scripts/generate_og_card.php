<?php
/**
 * Genera la tarjeta Open Graph (1200x630) para previews en WhatsApp / Twitter /
 * Facebook / LinkedIn. La compone con el logo + branding sobre fondo azul.
 *
 * Output: public/assets/css/og-card.png
 *
 * Idempotente: re-ejecutalo cuando cambie el logo o el copy.
 *
 * Uso:
 *   C:\xampp\php\php.exe scripts/generate_og_card.php
 */
declare(strict_types=1);

$root      = dirname(__DIR__);
$logoPath  = $root . '/public/assets/css/logo.png';
$outPath   = $root . '/public/assets/css/og-card.png';

if (!is_file($logoPath)) {
    fwrite(STDERR, "[ERROR] Logo no encontrado: $logoPath\n");
    exit(1);
}
if (!extension_loaded('gd')) {
    fwrite(STDERR, "[ERROR] Extension GD no esta cargada.\n");
    exit(1);
}

$W = 1200;
$H = 630;

$img = imagecreatetruecolor($W, $H);
imagesavealpha($img, true);

// ---- Fondo: gradiente azul (de #0B1B3F arriba a #1E40AF abajo) ----
// Implementado como muchas lineas horizontales con interpolacion.
$top    = [0x0B, 0x1B, 0x3F];
$bottom = [0x1E, 0x40, 0xAF];
for ($y = 0; $y < $H; $y++) {
    $t = $y / max(1, $H - 1);
    $r = (int) round($top[0] + ($bottom[0] - $top[0]) * $t);
    $g = (int) round($top[1] + ($bottom[1] - $top[1]) * $t);
    $b = (int) round($top[2] + ($bottom[2] - $top[2]) * $t);
    $c = imagecolorallocate($img, $r, $g, $b);
    imageline($img, 0, $y, $W, $y, $c);
}

// ---- Glow azul radial (centro-derecha) para darle profundidad ----
// Estilo "lens flare" sutil
$glowR = 420;
$glowX = (int) ($W * 0.78);
$glowY = (int) ($H * 0.38);
for ($i = $glowR; $i > 0; $i -= 6) {
    $alpha = (int) round(115 - (($glowR - $i) / $glowR) * 115); // 0..115
    if ($alpha < 0) $alpha = 0;
    if ($alpha > 127) $alpha = 127;
    $c = imagecolorallocatealpha($img, 0x3B, 0x82, 0xF6, $alpha);
    imagefilledellipse($img, $glowX, $glowY, $i * 2, $i * 2, $c);
}

// ---- Patron de puntos (grid) sutil ----
$grid = imagecolorallocatealpha($img, 0xFF, 0xFF, 0xFF, 118);
for ($x = 40; $x < $W; $x += 36) {
    for ($y = 40; $y < $H; $y += 36) {
        imagefilledellipse($img, $x, $y, 2, 2, $grid);
    }
}

// ---- Logo (izquierda) ----
// Lo dibujamos dentro de una "burbuja blanca" redondeada para legibilidad.
$logo = imagecreatefrompng($logoPath);
$lw = imagesx($logo);
$lh = imagesy($logo);

$bubbleSize = 220;
$bubbleX = 80;
$bubbleY = (int) (($H - $bubbleSize) / 2);

// Burbuja: rectangulo blanco con esquinas redondeadas (emulado con polygons + ellipses)
$white   = imagecolorallocate($img, 255, 255, 255);
$radius  = 36;
$bx2 = $bubbleX + $bubbleSize;
$by2 = $bubbleY + $bubbleSize;

// Sombra de la burbuja (azul oscuro semi-transparente, offset abajo)
for ($s = 12; $s > 0; $s--) {
    $shAlpha = 80 + ($s * 3);
    if ($shAlpha > 127) $shAlpha = 127;
    $sc = imagecolorallocatealpha($img, 0x0B, 0x1B, 0x3F, $shAlpha);
    $sx = $bubbleX - $s;
    $sy = $bubbleY + $s;
    $sx2 = $bx2 + $s;
    $sy2 = $by2 + $s;
    imagefilledrectangle($img, $sx + $radius, $sy, $sx2 - $radius, $sy2, $sc);
    imagefilledrectangle($img, $sx, $sy + $radius, $sx2, $sy2 - $radius, $sc);
    imagefilledellipse($img, $sx + $radius, $sy + $radius, $radius * 2, $radius * 2, $sc);
    imagefilledellipse($img, $sx2 - $radius, $sy + $radius, $radius * 2, $radius * 2, $sc);
    imagefilledellipse($img, $sx + $radius, $sy2 - $radius, $radius * 2, $radius * 2, $sc);
    imagefilledellipse($img, $sx2 - $radius, $sy2 - $radius, $radius * 2, $radius * 2, $sc);
}

// Burbuja blanca
imagefilledrectangle($img, $bubbleX + $radius, $bubbleY, $bx2 - $radius, $by2, $white);
imagefilledrectangle($img, $bubbleX, $bubbleY + $radius, $bx2, $by2 - $radius, $white);
imagefilledellipse($img, $bubbleX + $radius, $bubbleY + $radius, $radius * 2, $radius * 2, $white);
imagefilledellipse($img, $bx2 - $radius, $bubbleY + $radius, $radius * 2, $radius * 2, $white);
imagefilledellipse($img, $bubbleX + $radius, $by2 - $radius, $radius * 2, $radius * 2, $white);
imagefilledellipse($img, $bx2 - $radius, $by2 - $radius, $radius * 2, $radius * 2, $white);

// Pegar el logo escalado al 80% de la burbuja
$pad = 22;
$logoTarget = $bubbleSize - ($pad * 2);
imagecopyresampled(
    $img, $logo,
    $bubbleX + $pad, $bubbleY + $pad,
    0, 0,
    $logoTarget, $logoTarget,
    $lw, $lh
);
imagedestroy($logo);

// ---- Texto (derecha de la burbuja) ----
// Usamos imagestring con la fuente built-in mas grande (5) para evitar dependencia
// de TTFs externos. Para texto realmente bonito, ejecutar este script de nuevo en un
// entorno con FreeType + alguna TTF (ej: Inter o Roboto Bold).

$textX  = $bubbleX + $bubbleSize + 60;
$rightMargin = 60;
$textMaxW = $W - $textX - $rightMargin;
$brand  = 'Evallish Pulse';
$line1  = 'Contact Center · WhatsApp · IA';
$line2  = 'Atiende, vende y crece';
$line3  = 'desde una sola bandeja';
$line4  = 'Demo gratis 24h · sin tarjeta';

// Si tenemos FreeType + TTF, mejor. Probamos varias rutas tipicas.
$candidates = [
    $root . '/public/assets/fonts/Inter-Bold.ttf',
    'C:/Windows/Fonts/seguibl.ttf',  // Segoe UI Black
    'C:/Windows/Fonts/segoeuib.ttf', // Segoe UI Bold
    'C:/Windows/Fonts/arialbd.ttf',  // Arial Bold
    'C:/Windows/Fonts/arial.ttf',
];
$ttf = null;
foreach ($candidates as $f) {
    if (is_file($f)) { $ttf = $f; break; }
}

$whiteText  = imagecolorallocate($img, 255, 255, 255);
$blueLight  = imagecolorallocate($img, 0x93, 0xC5, 0xFD);
$muted      = imagecolorallocate($img, 0xC7, 0xD2, 0xFE);

if ($ttf && function_exists('imagettftext')) {
    // Chip de eyebrow
    $eyebrowSize = 18;
    $bbox = imagettfbbox($eyebrowSize, 0, $ttf, $line1);
    $eyebrowW = $bbox[2] - $bbox[0];
    $eyebrowH = $bbox[1] - $bbox[7];
    $chipPadX = 14; $chipPadY = 10;
    $chipX = $textX;
    $chipY = 130;
    $chipBg = imagecolorallocatealpha($img, 0x3B, 0x82, 0xF6, 90);
    imagefilledrectangle($img, $chipX, $chipY, $chipX + $eyebrowW + $chipPadX * 2, $chipY + $eyebrowH + $chipPadY * 2, $chipBg);
    imagettftext($img, $eyebrowSize, 0, $chipX + $chipPadX, $chipY + $chipPadY + $eyebrowH, $blueLight, $ttf, $line1);

    // Brand
    imagettftext($img, 28, 0, $textX, 220, $whiteText, $ttf, $brand);

    // Headline (dos lineas)
    imagettftext($img, 46, 0, $textX, 320, $whiteText, $ttf, $line2);
    imagettftext($img, 46, 0, $textX, 390, $whiteText, $ttf, $line3);

    // Pie demo
    imagettftext($img, 22, 0, $textX, 510, $muted, $ttf, $line4);

    // URL footer
    $url = 'pulse.kyrosrd.com';
    imagettftext($img, 18, 0, $textX, 560, $blueLight, $ttf, $url);
} else {
    // Fallback con fuente built-in (basico pero funcional)
    imagestring($img, 5, $textX, 140, $line1, $blueLight);
    imagestring($img, 5, $textX, 180, $brand, $whiteText);
    imagestring($img, 5, $textX, 280, $line2, $whiteText);
    imagestring($img, 5, $textX, 320, $line3, $whiteText);
    imagestring($img, 5, $textX, 460, $line4, $muted);
    imagestring($img, 4, $textX, 510, 'pulse.kyrosrd.com', $blueLight);
}

// Borde sutil inferior
$borderTop = imagecolorallocatealpha($img, 0x60, 0xA5, 0xFA, 90);
imageline($img, 0, $H - 2, $W, $H - 2, $borderTop);

// Output
if (!imagepng($img, $outPath, 9)) {
    fwrite(STDERR, "[ERROR] No se pudo escribir $outPath\n");
    exit(1);
}
imagedestroy($img);

$bytes = filesize($outPath);
echo "OK: $outPath ($bytes bytes, 1200x630)\n";
echo "TTF usado: " . ($ttf ?: 'fuente built-in (instala Inter o re-ejecuta con TTF)') . "\n";
