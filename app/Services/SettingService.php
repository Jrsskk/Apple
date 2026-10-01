<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingService
{
    private const DEFAULTS = [
        'app_display_name' => 'EduSync',
        'maintenance_mode' => 'false',
        'max_upload_size_mb' => '20',
        'sync_enabled' => 'true',
        'deadline_reminder_hours' => '24',
        'allow_teacher_create_subjects' => 'true',
        'session_timeout_minutes' => '120',
        'default_password_policy' => 'min_8_chars',
    ];

    public function all(): array
    {
        $stored = Setting::pluck('value', 'key')->all();

        return array_merge(self::DEFAULTS, $stored);
    }

    public function get(string $key, ?string $default = null): ?string
    {
        return Cache::remember("setting.{$key}", 3600, function () use ($key, $default) {
            $setting = Setting::where('key', $key)->first();

            return $setting?->value ?? self::DEFAULTS[$key] ?? $default;
        });
    }

    public function set(string $key, ?string $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("setting.{$key}");
    }

    public function updateMany(array $settings): void
    {
        foreach ($settings as $key => $value) {
            $this->set($key, is_bool($value) ? ($value ? 'true' : 'false') : (string) $value);
        }
    }

    public function definitions(): array
    {
        return [
            ['key' => 'app_display_name', 'label' => 'Application Display Name', 'type' => 'text'],
            ['key' => 'maintenance_mode', 'label' => 'Maintenance Mode', 'type' => 'boolean'],
            ['key' => 'max_upload_size_mb', 'label' => 'Max Upload Size (MB)', 'type' => 'number'],
            ['key' => 'sync_enabled', 'label' => 'Enable Offline Sync', 'type' => 'boolean'],
            ['key' => 'deadline_reminder_hours', 'label' => 'Deadline Reminder (hours before)', 'type' => 'number'],
            ['key' => 'allow_teacher_create_subjects', 'label' => 'Allow Teachers to Create Subjects', 'type' => 'boolean'],
            ['key' => 'session_timeout_minutes', 'label' => 'Session Timeout (minutes)', 'type' => 'number'],
        ];
    }
}
