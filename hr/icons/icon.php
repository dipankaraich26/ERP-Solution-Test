<?php
// Dynamic icon generator
$size = isset($_GET['size']) ? (int)$_GET['size'] : 192;
$size = min(max($size, 16), 512); // Clamp between 16 and 512

// Create image
$image = imagecreatetruecolor($size, $size);

// Colors
$purple1 = imagecolorallocate($image, 102, 126, 234); // #667eea
$purple2 = imagecolorallocate($image, 118, 75, 162);  // #764ba2
$white = imagecolorallocate($image, 255, 255, 255);

// Fill background with gradient-like effect
for ($y = 0; $y < $size; $y++) {
    $ratio = $y / $size;
    $r = (int)(102 + (118 - 102) * $ratio);
    $g = (int)(126 + (75 - 126) * $ratio);
    $b = (int)(234 + (162 - 234) * $ratio);
    $color = imagecolorallocate($image, $r, $g, $b);
    imageline($image, 0, $y, $size, $y, $color);
}

// Draw a simple clock/checkmark icon
$centerX = $size / 2;
$centerY = $size / 2;
$radius = $size * 0.35;

// Draw circle (clock face)
imagesetthickness($image, max(2, $size / 40));
imageellipse($image, $centerX, $centerY, $radius * 2, $radius * 2, $white);
imagefilledellipse($image, $centerX, $centerY, $radius * 1.8, $radius * 1.8, $white);

// Draw checkmark inside
$checkColor = $purple1;
$thickness = max(3, $size / 25);
imagesetthickness($image, $thickness);

$checkStartX = $centerX - $radius * 0.4;
$checkStartY = $centerY;
$checkMidX = $centerX - $radius * 0.1;
$checkMidY = $centerY + $radius * 0.3;
$checkEndX = $centerX + $radius * 0.5;
$checkEndY = $centerY - $radius * 0.3;

imageline($image, $checkStartX, $checkStartY, $checkMidX, $checkMidY, $checkColor);
imageline($image, $checkMidX, $checkMidY, $checkEndX, $checkEndY, $checkColor);

// Output
header('Content-Type: image/png');
header('Cache-Control: public, max-age=31536000');
imagepng($image);
imagedestroy($image);
