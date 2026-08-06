<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class AppUpdateService
{
    private const REPOSITORY = 'Mukhamadirfan1997/SMARTRKAS';

    /**
     * @return array{tag_name: string, name: string, html_url: string, published_at: string, body: string}|null
     */
    public function latestRelease(): ?array
    {
        $release = Cache::remember('smartrkas.latest-release', 3600, function (): ?array {
            try {
                $response = Http::timeout(5)->get(
                    'https://api.github.com/repos/'.self::REPOSITORY.'/releases/latest'
                );

                if (! $response->ok()) {
                    return null;
                }

                $data = (array) $response->json();

                return [
                    'tag_name' => (string) ($data['tag_name'] ?? ''),
                    'name' => (string) ($data['name'] ?? ''),
                    'html_url' => (string) ($data['html_url'] ?? ''),
                    'published_at' => (string) ($data['published_at'] ?? ''),
                    'body' => (string) ($data['body'] ?? ''),
                ];
            } catch (\Throwable) {
                return null;
            }
        });

        if (! is_array($release)) {
            return null;
        }

        return $release;
    }

    /**
     * @param array{tag_name: string, name: string, html_url: string, published_at: string, body: string}|null $release
     */
    public function isUpdateAvailable(?array $release): bool
    {
        if ($release === null || $release['tag_name'] === '') {
            return false;
        }

        return version_compare(
            $this->normalizeVersion($release['tag_name']),
            $this->normalizeVersion((string) config('app.version', '0.1.0')),
            '>'
        );
    }

    public function forget(): void
    {
        Cache::forget('smartrkas.latest-release');
    }

    private function normalizeVersion(string $version): string
    {
        return ltrim($version, 'vV');
    }
}
