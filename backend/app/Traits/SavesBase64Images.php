<?php

namespace App\Traits;

use GdImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

trait SavesBase64Images
{
    protected function saveBase64ImageFit(string $dataUrl, string $directory, int $maxWidth, int $maxHeight, string $disk = 'public'): string {
        $image = $this->decodeImage($dataUrl);
        $width = imagesx($image);
        $height = imagesy($image);

        if ($width > $maxWidth || $height > $maxHeight) {
            $scale = min($maxWidth / $width, $maxHeight / $height);
            $image = $this->resize($image, (int) ($width * $scale), (int) ($height * $scale));
        }

        return $this->storePng($image, $directory, $disk);
    }

    protected function saveBase64ImageCover(string $dataUrl, string $directory, int $width, int $height, string $disk = 'public'): string {
        $image = $this->decodeImage($dataUrl);
        $scale = max($width / imagesx($image), $height / imagesy($image));
        $scaled = $this->resize($image, (int) round(imagesx($image) * $scale), (int) round(imagesy($image) * $scale));

        $cropped = imagecrop($scaled, [
            'x' => (int) round((imagesx($scaled) - $width) / 2),
            'y' => (int) round((imagesy($scaled) - $height) / 2),
            'width' => $width,
            'height' => $height,
        ]);
        imagedestroy($scaled);

        return $this->storePng($cropped, $directory, $disk);
    }

    protected function decodeImage(string $dataUrl): GdImage {
        $encoded = substr($dataUrl, strpos($dataUrl, ',') + 1);
        $decoded = base64_decode($encoded, true);
        $image = $decoded !== false ? @imagecreatefromstring($decoded) : false;

        if ($image === false) {
            throw ValidationException::withMessages(['image' => 'The uploaded image is not a valid image file.']);
        }

        return $image;
    }

    protected function resize(GdImage $source, int $width, int $height): GdImage {
        $resized = $this->canvas($width, $height);

        imagecopyresampled($resized, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));
        imagedestroy($source);

        return $resized;
    }

    protected function canvas(int $width, int $height): GdImage {
        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, false);

        return $canvas;
    }

    protected function storePng(GdImage $image, string $directory, string $disk): string {
        imagealphablending($image, false);
        imagesavealpha($image, true);

        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        $path = trim($directory, '/').'/'.Str::uuid().'.png';
        Storage::disk($disk)->put($path, $png);

        return $path;
    }
}
