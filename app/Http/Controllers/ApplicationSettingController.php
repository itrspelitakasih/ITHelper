<?php

namespace App\Http\Controllers;

use App\Models\ApplicationSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class ApplicationSettingController extends Controller
{
    public function edit()
    {
        abort_unless(Schema::hasTable('application_settings'), 503, 'Jalankan migration terlebih dahulu.');

        return view('pages.settings.app', [
            'title' => 'Aplikasi',
            'settingAppName' => ApplicationSetting::appName(),
            'settingLogoUrl' => ApplicationSetting::logoUrl(),
        ]);
    }

    public function update(Request $request)
    {
        abort_unless(Schema::hasTable('application_settings'), 503, 'Jalankan migration terlebih dahulu.');

        $request->merge([
            'app_name' => trim((string) $request->input('app_name')),
        ]);

        $data = $request->validate([
            'app_name' => ['required', 'string', 'max:100'],
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
        ]);

        ApplicationSetting::setAppName($data['app_name']);

        if ($request->boolean('remove_logo')) {
            $this->deleteLogo(ApplicationSetting::logoPath());
            ApplicationSetting::setLogoPath(null);
        }

        if ($request->hasFile('logo')) {
            $this->deleteLogo(ApplicationSetting::logoPath());
            ApplicationSetting::setLogoPath($this->storeLogo($request));
        }

        return back()->with('success', 'Pengaturan aplikasi berhasil disimpan.');
    }

    private function storeLogo(Request $request): string
    {
        $directory = public_path('uploads/app-branding');
        File::ensureDirectoryExists($directory);

        $file = $request->file('logo');
        $extension = strtolower($file->getClientOriginalExtension());
        $filename = 'logo-'.now()->format('YmdHis').'.'.$extension;
        $path = $directory.DIRECTORY_SEPARATOR.$filename;

        if ($extension === 'svg' || ! extension_loaded('gd')) {
            $file->move($directory, $filename);

            return 'uploads/app-branding/'.$filename;
        }

        if (! $this->compressRasterLogo($file->getRealPath(), $path, $extension)) {
            $file->move($directory, $filename);
        }

        return 'uploads/app-branding/'.$filename;
    }

    private function compressRasterLogo(string $sourcePath, string $targetPath, string $extension): bool
    {
        $source = match ($extension) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($sourcePath),
            'png' => @imagecreatefrompng($sourcePath),
            'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : false,
            default => false,
        };

        if (! $source) {
            return false;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $maxSide = 512;
        $scale = min(1, $maxSide / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $target = imagecreatetruecolor($targetWidth, $targetHeight);

        if (in_array($extension, ['png', 'webp'], true)) {
            imagealphablending($target, false);
            imagesavealpha($target, true);
            $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
            imagefilledrectangle($target, 0, 0, $targetWidth, $targetHeight, $transparent);
        }

        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        $saved = match ($extension) {
            'jpg', 'jpeg' => imagejpeg($target, $targetPath, 82),
            'png' => imagepng($target, $targetPath, 8),
            'webp' => function_exists('imagewebp') ? imagewebp($target, $targetPath, 82) : false,
            default => false,
        };

        imagedestroy($source);
        imagedestroy($target);

        return $saved;
    }

    private function deleteLogo(?string $path): void
    {
        if (! $path) {
            return;
        }

        if (str_starts_with($path, 'uploads/')) {
            File::delete(public_path($path));
        } else {
            Storage::disk('public')->delete($path);
        }
    }
}
