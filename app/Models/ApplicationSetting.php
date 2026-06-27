<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Throwable;

class ApplicationSetting extends Model
{
    protected $fillable = ['key', 'value'];

    private static ?string $cachedAppName = null;
    private static bool $logoLoaded = false;
    private static ?string $cachedLogoPath = null;

    public static function appName(): string
    {
        if (self::$cachedAppName !== null) {
            return self::$cachedAppName;
        }

        $name = null;

        try {
            $name = self::query()->where('key', 'app_name')->value('value');
        } catch (Throwable) {
            $name = null;
        }

        return self::$cachedAppName = trim((string) $name) ?: config('app.name', 'Laravel');
    }

    public static function logoPath(): ?string
    {
        if (self::$logoLoaded) {
            return self::$cachedLogoPath;
        }

        $path = null;

        try {
            $path = self::query()->where('key', 'app_logo_path')->value('value');
        } catch (Throwable) {
            $path = null;
        }

        self::$logoLoaded = true;
        self::$cachedLogoPath = trim((string) $path) ?: null;

        return self::$cachedLogoPath;
    }

    public static function logoUrl(): ?string
    {
        $path = self::logoPath();

        if (! $path) {
            return null;
        }

        return str_starts_with($path, 'uploads/')
            ? asset($path)
            : asset('storage/'.$path);
    }

    public static function setAppName(string $name): void
    {
        $name = trim($name);

        self::query()->updateOrCreate(
            ['key' => 'app_name'],
            ['value' => $name]
        );

        self::$cachedAppName = $name;
    }

    public static function setLogoPath(?string $path): void
    {
        self::query()->updateOrCreate(
            ['key' => 'app_logo_path'],
            ['value' => $path]
        );

        self::$logoLoaded = true;
        self::$cachedLogoPath = $path;
    }
}
