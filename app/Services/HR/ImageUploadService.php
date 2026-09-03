<?php

declare(strict_types=1);

namespace App\Services\HR;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageUploadService
{
    private const MAX_FILE_SIZE = 524288; // 512 KB

    public function processAndStore(UploadedFile|string $input, string $directory, int $maxWidth = 1600): string
    {
        if ($input instanceof UploadedFile) {
            $contents = file_get_contents($input->getRealPath());
        } elseif (is_string($input) && str_starts_with($input, 'data:image')) {
            $parts = explode(',', $input, 2);
            $contents = isset($parts[1]) ? base64_decode($parts[1]) : false;
        } else {
            $contents = false;
        }

        if ($contents === false) {
            if ($input instanceof UploadedFile) {
                return $input->store($directory, 'public');
            }

            throw new \InvalidArgumentException('Invalid image input provided.');
        }

        $image = @imagecreatefromstring($contents);
        if ($image === false) {
            if ($input instanceof UploadedFile) {
                return $input->store($directory, 'public');
            }

            throw new \InvalidArgumentException('Failed to decode image content.');
        }

        $origWidth = imagesx($image);
        $origHeight = imagesy($image);

        if ($origWidth > $maxWidth) {
            $newWidth = $maxWidth;
            $newHeight = (int) round(($origHeight / $origWidth) * $maxWidth);

            $resized = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
            imagedestroy($image);
            $image = $resized;
        }

        $filename = $directory.'/'.Str::random(40).'.jpg';
        $quality = 85;

        ob_start();
        imagejpeg($image, null, $quality);
        $data = ob_get_clean();

        while ($data !== false && strlen($data) > self::MAX_FILE_SIZE && $quality > 30) {
            $quality -= 10;
            ob_start();
            imagejpeg($image, null, $quality);
            $data = ob_get_clean();
        }

        imagedestroy($image);

        if ($data === false) {
            return $file->store($directory, 'public');
        }

        Storage::disk('public')->put($filename, $data);

        return $filename;
    }
}
