<?php

namespace Leantime\Plugins\LeanLib\Services;

/** Resolves Composer metadata and renders its standard, data-only presentation. */
final class PluginMetadataRenderer
{
    /** @return array<string,mixed> */
    public function fromPluginFolder(string $pluginFolder): array
    {
        if (! preg_match('/^[a-zA-Z0-9_.-]{1,100}$/', $pluginFolder)
            || basename($pluginFolder) !== $pluginFolder
            || $pluginFolder === '.' || $pluginFolder === '..') return [];

        $pluginRoot = realpath(ROOT.'/../app/Plugins');
        if ($pluginRoot === false || ! is_dir($pluginRoot)) return [];
        $pluginPath = realpath($pluginRoot.DIRECTORY_SEPARATOR.$pluginFolder);
        if ($pluginPath === false || ! str_starts_with($pluginPath, $pluginRoot.DIRECTORY_SEPARATOR)) return [];
        $composerPath = realpath($pluginPath.DIRECTORY_SEPARATOR.'composer.json');
        if ($composerPath === false || ! str_starts_with($composerPath, $pluginPath.DIRECTORY_SEPARATOR)) return [];

        try {
            $composer = json_decode((string) @file_get_contents($composerPath), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return [];
        }
        if (! is_array($composer)) return [];

        $authors = [];
        foreach (is_array($composer['authors'] ?? null) ? $composer['authors'] : [] as $author) {
            if (! is_array($author) || ! is_string($author['name'] ?? null) || trim($author['name']) === '') continue;
            $email = is_string($author['email'] ?? null) && filter_var(trim($author['email']), FILTER_VALIDATE_EMAIL)
                ? trim($author['email']) : null;
            $profile = $this->webUrl($author['homepage'] ?? null);
            $authors[] = ['name' => trim($author['name']), 'email' => $email, 'homepage' => $profile];
        }

        $support = is_array($composer['support'] ?? null) ? $composer['support'] : [];
        $supportUrl = $this->firstWebUrl($support['issues'] ?? null, $support['docs'] ?? null, $support['forum'] ?? null);
        $funding = $composer['funding'] ?? [];
        $fundingLinks = is_array($funding) ? $funding : [$funding];
        $contributionsUrl = null;
        foreach ($fundingLinks as $fundingLink) {
            $candidate = is_array($fundingLink) ? ($fundingLink['url'] ?? null) : $fundingLink;
            $contributionsUrl = $this->webUrl($candidate);
            if ($contributionsUrl !== null) break;
        }

        $license = $composer['license'] ?? null;
        if (is_array($license)) $license = implode(', ', array_filter($license, 'is_string'));

        return [
            'title' => $this->text($composer['name'] ?? null),
            'description' => $this->text($composer['description'] ?? null),
            'version' => $this->text($composer['version'] ?? null),
            'authors' => $authors,
            'emails' => array_values(array_filter(array_column($authors, 'email'))),
            'license' => $this->text($license),
            'sourceUrl' => $this->webUrl($support['source'] ?? null),
            'homepage' => $this->webUrl($composer['homepage'] ?? null),
            'supportUrl' => $supportUrl,
            'contributionsUrl' => $contributionsUrl,
        ];
    }

    /** Render only Library-owned markup; provider input is data, never template code. */
    public function render(array $metadata): string
    {
        return view()->file(__DIR__.'/../Templates/plugin-metadata.blade.php', [
            'metadata' => $this->normalize($metadata),
        ])->render();
    }

    private function normalize(array $metadata): array
    {
        $authors = $metadata['authors'] ?? [];
        if (is_string($authors)) $authors = json_decode($authors, true) ?: [];
        if (is_object($authors)) $authors = (array) $authors;
        if (! is_array($authors)) $authors = [];

        $normalizedAuthors = [];
        foreach ($authors as $author) {
            if (is_object($author)) $author = (array) $author;
            if (! is_array($author) || ! is_string($author['name'] ?? null) || trim($author['name']) === '') continue;
            $email = is_string($author['email'] ?? null) && filter_var(trim($author['email']), FILTER_VALIDATE_EMAIL)
                ? trim($author['email']) : null;
            $normalizedAuthors[] = [
                'name' => trim($author['name']),
                'email' => $email,
                'homepage' => $this->webUrl($author['homepage'] ?? null),
            ];
        }

        $license = $metadata['license'] ?? null;
        if (is_array($license)) $license = implode(', ', array_filter($license, 'is_string'));

        return [
            'pluginFolder' => $this->text($metadata['pluginFolder'] ?? null),
            'version' => $this->text($metadata['version'] ?? null),
            'authors' => $normalizedAuthors,
            'license' => $this->text($license),
            'homepage' => $this->webUrl($metadata['homepage'] ?? null),
            'sourceUrl' => $this->webUrl($metadata['sourceUrl'] ?? null),
            'supportUrl' => $this->webUrl($metadata['supportUrl'] ?? null),
            'contributionsUrl' => $this->webUrl($metadata['contributionsUrl'] ?? null),
        ];
    }

    private function firstWebUrl(mixed ...$values): ?string
    {
        foreach ($values as $value) {
            $url = $this->webUrl($value);
            if ($url !== null) return $url;
        }
        return null;
    }

    private function webUrl(mixed $value): ?string
    {
        if (! is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false) return null;
        return in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true) ? $value : null;
    }

    private function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
