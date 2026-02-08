<?php
// Dynamic icon generator
$size = isset($_GET['size']) ? (int)$_GET['size'] : 192;
$size = min(max($size, 16), 512);

// Create image
$image = imagecreatetruecolor($size, $size);

// Colors
$purple1 = imagecolorallocate($image, 102, 126, 234);
$purple2 = imagecolorallocate($image, 118, 75, 162);
$white = imagecolorallocate($image, 255, 255, 255);

// Fill background with gradient
for ($y = 0; $y < $size; $y++) {
    $ratio = $y / $size;
    $r = (int)(102 + (118 - 102) * $ratio);
    $g = (int)(126 + (75 - 126) * $ratio);
    $b = (int)(234 + (162 - 234) * $ratio);
    $color = imagecolorallocate($image, $r, $g, $b);
    imageline($image, 0, $y, $size - 1, $y, $color);
}

// Draw a simple clock/checkmark icon
$centerX = (int)($size / 2);
$centerY = (int)($size / 2);
$radius = (int)($size * 0.35);

// Draw circle (clock face)
imagesetthickness($image, max(2, (int)($size / 40)));
imageellipse($image, $centerX, $centerY, $radius * 2, $radius * 2, $white);
imagefilledellipse($image, $centerX, $centerY, (int)($radius * 1.8), (int)($radius * 1.8), $white);

// Draw checkmark inside
$checkColor = $purple1;
$thickness = max(3, (int)($size / 25));
imagesetthickness($image, $thickness);

$checkStartX = (int)($centerX - $radius * 0.4);
$checkStartY = $centerY;
$checkMidX = (int)($centerX - $radius * 0.1);
$checkMidY = (int)($centerY + $radius * 0.3);
$checkEndX = (int)($centerX + $radius * 0.5);
$checkEndY = (int)($centerY - $radius * 0.3);

imageline($image, $checkStartX, $checkStartY, $checkMidX, $checkMidY, $checkColor);
imageline($image, $checkMidX, $checkMidY, $checkEndX, $checkEndY, $checkColor);

// Output
header('Content-Type: image/png');
header('Cache-Control: public, max-age=31536000');
imagepng($image);
imagedestroy($image);
