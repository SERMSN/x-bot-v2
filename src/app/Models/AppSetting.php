<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class AppSetting extends Model
{
    use HasFactory;

    public const KEY_ADMIN_PANEL_PRIMARY_COLOR = "ADMIN_PANEL_PRIMARY_COLOR";
    public const KEY_ADMIN_PANEL_NAVIGATION_LAYOUT = "ADMIN_PANEL_NAVIGATION_LAYOUT";
    public const KEY_MAIN_PANEL_PRIMARY_COLOR = "MAIN_PANEL_PRIMARY_COLOR";
    public const KEY_MAIN_PANEL_NAVIGATION_LAYOUT = "MAIN_PANEL_NAVIGATION_LAYOUT";

    public const NAVIGATION_TOP = "top";
    public const NAVIGATION_LEFT = "left";

    protected $table = "app_settings";

    protected $fillable = ["key", "name", "value"];

    protected static function booted(): void
    {
        static::saved(function (self $setting): void {
            Cache::forget(self::cacheKey($setting->key));
        });

        static::deleted(function (self $setting): void {
            Cache::forget(self::cacheKey($setting->key));
        });
    }

    public static function getValue(
        string $key,
        ?string $default = null,
    ): ?string {
        try {
            if (!Schema::hasTable(new self()->getTable())) {
                return $default;
            }

            return Cache::remember(
                self::cacheKey($key),
                now()->addMinutes(10),
                fn() => self::query()->where("key", $key)->value("value") ??
                    $default,
            );
        } catch (\Throwable $e) {
            return $default;
        }
    }

    private static function cacheKey(string $key): string
    {
        return "app_setting:{$key}";
    }
}
