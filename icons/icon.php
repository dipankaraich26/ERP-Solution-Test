<?php
// Dynamic icon generator - ERP Admin (Purple-blue theme)
$size = isset($_GET['size']) ? (int)$_GET['size'] : 192;
$size = min(max($size, 16), 512);

$image = imagecreatetruecolor($size, $size);

// Purple-blue gradient: #667eea to #764ba2
for ($y = 0; $y < $size; $y++) {
    $ratio = $y / $size;
    $r = (int)(102 + (118 - 102) * $ratio);
    $g = (int)(126 + (75 - 126) * $ratio);
    $b = (int)(234 + (162 - 234) * $ratio);
    $color = imagecolorallocate($image, $r, $g, $b);
    imageline($image, 0, $y, $size - 1, $y, $color);
}

$white = imagecolorallocate($image, 255, 255, 255);
$centerX = (int)($size / 2);
$centerY = (int)($size / 2);
$radius = (int)($size * 0.32);

// Draw gear/cog icon for ERP
imagesetthickness($image, max(2, (int)($size / 30)));

// Outer circle
$outerR = (int)($radius * 1.1);
imageellipse($image, $centerX, $centerY, (int)($outerR * 2), (int)($outerR * 2), $white);

// Inner circle
$innerR = (int)($radius * 0.55);
imageellipse($image, $centerX, $centerY, (int)($innerR * 2), (int)($innerR * 2), $white);

// Draw gear teeth (8 teeth)
$teethCount = 8;
$toothLen = (int)($radius * 0.35);
$thick = max(2, (int)($size / 20));
imagesetthickness($image, $thick);

for ($i = 0; $i < $teethCount; $i++) {
    $angle = ($i * 360 / $teethCount) * M_PI / 180;
    $x1 = (int)($centerX + $outerR * cos($angle));
    $y1 = (int)($centerY + $outerR * sin($angle));
    $x2 = (int)($centerX + ($outerR + $toothLen) * cos($angle));
    $y2 = (int)($centerY + ($outerR + $toothLen) * sin($angle));
    imageline($image, $x1, $y1, $x2, $y2, $white);
}

// Draw grid inside the inner circle (dashboard icon)
imagesetthickness($image, max(1, (int)($size / 50)));
$gridR = (int)($innerR * 0.6);

// Horizontal line
imageline($image, (int)($centerX - $gridR), $centerY, (int)($centerX + $gridR), $centerY, $white);
// Vertical line
imageline($image, $centerX, (int)($centerY - $gridR), $centerX, (int)($centerY + $gridR), $white);

header('Content-Type: image/png');
header('Cache-Control: public, max-age=31536000');
imagepng($image);
imagedestroy($image);
