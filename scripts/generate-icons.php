<?php

$dir = __DIR__.'/public/icons';
if (! is_dir($dir)) {
    mkdir($dir, 0755, true);
}

if (! extension_loaded('gd')) {
    fwrite(STDERR, "GD extension not available\n");
    exit(1);
}

foreach ([192, 512] as $size) {
    $img = imagecreatetruecolor($size, $size);
    $indigo = imagecolorallocate($img, 79, 70, 229);
    $white = imagecolorallocate($img, 255, 255, 255);
    imagefilledrectangle($img, 0, 0, $size, $size, $indigo);
    $font = 5;
    $text = 'ES';
    $tw = imagefontwidth($font) * strlen($text);
    $th = imagefontheight($font);
    imagestring($img, $font, (int) (($size - $tw) / 2), (int) (($size - $th) / 2), $text, $white);
    imagepng($img, "$dir/icon-$size.png");
    imagedestroy($img);
}

echo "Icons created\n";
