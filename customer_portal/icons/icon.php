<?php
// Dynamic icon generator - Customer Portal (Green theme)
$size = isset($_GET['size']) ? (int)$_GET['size'] : 192;
$size = min(max($size, 16), 512);

$image = imagecreatetruecolor($size, $size);

// Green gradient: #11998e to #38ef7d
for ($y = 0; $y < $size; $y++) {
    $ratio = $y / $size;
    $r = (int)(17 + (56 - 17) * $ratio);
    $g = (int)(153 + (239 - 153) * $ratio);
    $b = (int)(142 + (125 - 142) * $ratio);
    $color = imagecolorallocate($image, $r, $g, $b);
    imageline($image, 0, $y, $size - 1, $y, $color);
}

$white = imagecolorallocate($image, 255, 255, 255);
$centerX = (int)($size / 2);
$centerY = (int)($size / 2);
$radius = (int)($size * 0.32);

// Draw building/portal icon - simplified storefront
imagesetthickness($image, max(2, (int)($size / 30)));

// Roof triangle
$roofTop = (int)($centerY - $radius * 1.1);
$roofLeft = (int)($centerX - $radius);
$roofRight = (int)($centerX + $radius);
$roofBase = (int)($centerY - $radius * 0.3);
imageline($image, $roofLeft, $roofBase, $centerX, $roofTop, $white);
imageline($image, $centerX, $roofTop, $roofRight, $roofBase, $white);
imageline($image, $roofLeft, $roofBase, $roofRight, $roofBase, $white);

// Walls
$wallBottom = (int)($centerY + $radius);
imageline($image, $roofLeft, $roofBase, $roofLeft, $wallBottom, $white);
imageline($image, $roofRight, $roofBase, $roofRight, $wallBottom, $white);
imageline($image, $roofLeft, $wallBottom, $roofRight, $wallBottom, $white);

// Door
$doorLeft = (int)($centerX - $radius * 0.25);
$doorRight = (int)($centerX + $radius * 0.25);
$doorTop = (int)($centerY + $radius * 0.15);
imagerectangle($image, $doorLeft, $doorTop, $doorRight, $wallBottom, $white);

// Window left
$wl = (int)($roofLeft + $radius * 0.2);
$wr = (int)($doorLeft - $radius * 0.15);
$wt = (int)($centerY - $radius * 0.05);
$wb = (int)($centerY + $radius * 0.35);
imagerectangle($image, $wl, $wt, $wr, $wb, $white);

// Window right
$wl2 = (int)($doorRight + $radius * 0.15);
$wr2 = (int)($roofRight - $radius * 0.2);
imagerectangle($image, $wl2, $wt, $wr2, $wb, $white);

header('Content-Type: image/png');
header('Cache-Control: public, max-age=31536000');
imagepng($image);
imagedestroy($image);
