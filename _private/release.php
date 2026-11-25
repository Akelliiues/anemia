<?php
declare(strict_types=1);
if (!defined('ANEMIA_STAFF_BOOT')) { http_response_code(404); exit; }

const ANEMIA_RELEASE_COMPONENTS = ['public'=>'หน้าบ้าน','staff'=>'หลังบ้าน','research'=>'การประเมินโครงการ'];

function anemiaRelease(string $json): ?array
{
    $release = json_decode($json, true, 8);
    $components = $release['components'] ?? null;
    $isVersion = static fn($value): bool => is_string($value) && preg_match('/^\d{1,6}\.\d{1,6}\.\d{1,6}$/D', $value) === 1;
    if (($release['format'] ?? null) !== 1 || !$isVersion($release['version'] ?? null) || !is_array($components)) return null;
    if (array_key_exists('build', $release) &&
        (!is_string($release['build']) || preg_match('/^\d{14}$/D', $release['build']) !== 1)) return null;
    foreach (ANEMIA_RELEASE_COMPONENTS as $key => $label) {
        if (!$isVersion($components[$key] ?? null)) return null;
    }
    return $release;
}

function anemiaReadCentralRelease(string $url): ?array
{
    $parts = parse_url($url);
    if (!$parts || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) return null;
    $context = stream_context_create(['http'=>['timeout'=>3,'follow_location'=>0,'max_redirects'=>0]]);
    $json = @file_get_contents($url, false, $context, 0, 8193);
    return is_string($json) && strlen($json) <= 8192 ? anemiaRelease($json) : null;
}
