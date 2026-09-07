<?php

namespace App\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Illuminate\Support\Str;

trait FileUploadTrait
{
    /**
     * @param Request $request - Incoming HTTP request.
     * @param string $fieldName - Name of the file input field in the HTML form.
     * @param string $directory - Folder inside 'storage/app/public/'.
     * @param int|null $width - Maximum width of the image (optional).
     * @param int|null $height - Maximum height of the image (optional).
     * @param int $quality - Image quality (0-100).
     * @param bool $forceWebp - If true, converts all images to WebP format.
     * @return string|null - Saved file path or null.
     */
    public function handleFileUpload(
        Request $request,
        string $fieldName,
        string $directory,
        ?int $width = null,
        ?int $height = null,
        int $quality = 90,
        bool $forceWebp = false
    ): ?string {
        if (!$request->hasFile($fieldName)) {
            return null;
        }

        $file = $request->file($fieldName);
        $originalFileName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $originalFileName = Str::slug($originalFileName);
        $fileName = time() . '_' . $originalFileName;

        if (str_starts_with($file->getMimeType(), 'image/')) {
            $manager = new ImageManager(new Driver());
            $image = $manager->read($file);


            if ($width || $height) {
                // The scaleDown method prevents the image from being enlarged and maintains the aspect ratio
                $image->scaleDown(width: $width, height: $height);
            }

            if ($forceWebp) {
                $fileName .= '.webp';
                $encodedImage = $image->toWebp($quality);
            } else {
                $fileName .= '.' . $file->getClientOriginalExtension();
                $encodedImage = match ($file->getMimeType()) {
                    'image/jpeg', 'image/jpg' => $image->toJpeg($quality),
                    'image/webp' => $image->toWebp($quality),
                    'image/gif' => $image->toGif(),
                    default => $image->toPng(),
                };
            }

            $filePath = "{$directory}/{$fileName}";
            Storage::disk('public')->put($filePath, (string) $encodedImage);
            return $filePath;
        }

        $fileName .= '.' . $file->getClientOriginalExtension();
        $filePath = "{$directory}/{$fileName}";
        return $file->storeAs($directory, $fileName, 'public');
    }

    /**
     * Deletes a specific file from storage.
     *
     * @param string|null $path - Path to the file (e.g., 'posts/image.jpg')
     */
     public function deleteFile(?string $pathOrUrl): void
    {
        if (!$pathOrUrl) {
            return;
        }

        $actualPath = $pathOrUrl;

        if (Str::startsWith($pathOrUrl, ['http://', 'https://'])) {

            $path = parse_url($pathOrUrl, PHP_URL_PATH);

            $actualPath = str_replace('/storage/', '', $path);
        }

        if (Storage::disk('public')->exists($actualPath)) {
            Storage::disk('public')->delete($actualPath);
        }
    }
}
