<?php
declare(strict_types=1);
if (!defined('ANEMIA_STAFF_BOOT')) { http_response_code(404); exit; }

function workspaceIcon(string $name): string
{
    if ($name === 'drop') return '<img class="portal-heart-mark" src="portal-heart-clear.png" width="40" height="40" alt="">';
    $mouths = ['rating-1'=>'M7.5 16q4.5-5 9 0','rating-2'=>'M8.5 15.5q3.5-3 7 0',
        'rating-3'=>'M8.5 15h7','rating-4'=>'M8.5 14q3.5 4 7 0','rating-5'=>'M7.5 13.5h9q-4.5 7-9 0Z'];
    $path = isset($mouths[$name])
        ? '<circle cx="12" cy="12" r="9"/><path d="M8.5 9h.01M15.5 9h.01' . $mouths[$name] . '"/>'
        : match ($name) {
        'moon' => '<path d="M20 15A9 9 0 0 1 9 4a9 9 0 1 0 11 11Z"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M2 12h2m16 0h2M5 5l1.5 1.5m11 11L19 19M5 19l1.5-1.5m11-11L19 5"/>',
        'layout' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18m-12 0v11"/>',
        'pointer' => '<path d="M11 13 9 6a1.5 1.5 0 0 1 3-1l2 6 4-1a2 2 0 0 1 2 2l-1 7H9l-4-6a1.5 1.5 0 0 1 2-2l4 2Z"/>',
        'spark' => '<path d="M12 3v18M3 12h18M6 6l12 12M18 6 6 18"/>',
        'message' => '<path d="M20 5H4v11h4v4l5-4h7zM8 10h8m-8 3h6"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="3"/><path d="M7 3v4m10-4v4M3 10h18m-13 5h2m4 0h2"/>',
        'scan' => '<path d="M3 8V3h5m8 0h5v5m0 8v5h-5m-8 0H3v-5M7 7h3v3H7zM14 7h3v3h-3zM7 14h3v3H7zm7 0h3v3h-3z"/>',
        'back' => '<path d="M19 12H5m6 6-6-6 6-6"/>',
        'filter' => '<path d="M3 5h18l-7 8v5l-4 2v-7z"/>',
        'print' => '<path d="M7 8V3h10v5M7 17H4V9h16v8h-3M7 14h10v7H7z"/>',
        'download' => '<path d="M12 3v12m-4-4 4 4 4-4M5 17v4h14v-4"/>',
        'close' => '<path d="m6 6 12 12M18 6 6 18"/>',
        'school-add' => '<path d="M2 10l7-5 7 5M4 9v11h10V9M2 20h14M7 20v-5h4v5M17 6h6m-3-3v6"/><circle cx="9" cy="11" r="1"/>',
        'home' => '<path d="m3 10 9-7 9 7M5 9v12h14V9m-9 12v-6h4v6"/>',
        'send' => '<path d="m3 4 18 8-18 8 3-8-3-8Zm3 8h15"/>',
        'save' => '<path d="M5 3h12l4 4v14H3V3h2Zm2 0v6h10V3M7 21v-7h10v7"/>',
        'power' => '<path d="M12 2v9m5-6a8 8 0 1 1-10 0"/>',
        'book' => '<path d="M12 6v15M3 4c4-1 7 0 9 2 2-2 5-3 9-2v15c-4-1-7 0-9 2-2-2-5-3-9-2z"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5m0-8h.01"/>',
        'next' => '<path d="m10 6 6 6-6 6"/>',
        'phone' => '<path d="M7 3H4a1 1 0 0 0-1 1c0 9 8 17 17 17a1 1 0 0 0 1-1v-3l-5-2-2 2a14 14 0 0 1-7-7l2-2z"/>',
        'chart' => '<path d="M3 3v18h18M8 16v-3m5 3V8m5 8V5"/>',
        'users' => '<circle cx="9" cy="8" r="3"/><path d="M3 20v-2a5 5 0 0 1 5-5h2a5 5 0 0 1 5 5v2M17 5a3 3 0 0 1 0 6m1 3a4 4 0 0 1 3 4v2"/>',
        'person' => '<circle cx="12" cy="8" r="3"/><path d="M5 21v-2a5 5 0 0 1 5-5h4a5 5 0 0 1 5 5v2"/>',
        'leaf' => '<path d="M4 20c2-5 5-8 10-11m6-5c1 8-2 14-9 14-4 0-6-2-6-5 0-6 7-7 15-9Z"/>',
        'star' => '<path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.6l6.2-.9L12 3Z"/>',
        'flower' => '<path d="M12 8c-4-6-10 0-5 4-5 4 1 10 5 5 4 5 10-1 5-5 5-4-1-10-5-4Z"/><circle cx="12" cy="12" r="1.5"/>',
        'edit' => '<path d="m15 5 4 4M4 20l4-.8L20 7l-4-4L4.8 15.9 4 20Z"/>',
        'lock' => '<rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3m-4 4v3"/>',
        'eye' => '<path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"/><circle cx="12" cy="12" r="2.5"/>',
        'eye-off' => '<path d="m3 3 18 18M10.5 6.1c.5-.1 1-.1 1.5-.1 6.5 0 10 6 10 6a17 17 0 0 1-3 3.6M6.3 7.8A17 17 0 0 0 2 12s3.5 6 10 6c1.6 0 3-.4 4.2-.9"/>',
        'login' => '<path d="M14 4h5v16h-5M4 12h10m-4-4 4 4-4 4"/>',
        'history' => '<path d="M3 10a9 9 0 1 1 2 8m-2-8V5m0 5h5m4-3v5l3 2"/>',
        'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9m-8 12a2 2 0 0 0 4 0"/>',
        'play' => '<path d="m9 5 11 7-11 7z"/>',
        'logout' => '<path d="M11 4H5v16h6m3-12 4 4-4 4m-5-4h9"/>',
        'map' => '<path d="M3 6l6-3 6 3 6-3v15l-6 3-6-3-6 3zM9 3v15M15 6v15"/>',
        'province' => '<path d="M4 21V5h16v16M2 21h20M8 9h2m4 0h2m-8 4h2m4 0h2m-6 8v-4h4v4"/>',
        'ready' => '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',
        default => '<path d="M5 3h10l4 4v14H5zM14 3v5h5M8 12h8m-8 4h8"/>',
    };
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
}

function workspaceBack(string $fallback, bool $useHistory = true): void
{
    ?>
    <a class="back floating-back" data-page-back href="<?= htmlspecialchars($fallback,ENT_QUOTES,'UTF-8') ?>" aria-label="ย้อนกลับ" title="ย้อนกลับ"><?= workspaceIcon('back') ?></a>
    <?php if ($useHistory): ?>
    <script>
    document.currentScript.previousElementSibling.addEventListener('click', event => {
     if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || history.length < 2) return;
     const entries = window.navigation?.entries();
     const previous = entries?.find(entry => entry.index === window.navigation.currentEntry.index - 1)?.url || document.referrer;
     if (!previous) return;
     const url = new URL(previous);
     if (url.origin !== location.origin || url.href === location.href) return;
     if (location.pathname.endsWith('/dashboard.php') && ['/staff.php','/login','/login.php'].some(suffix=>url.pathname.endsWith(suffix))) return;
     event.preventDefault();
     history.back();
    });
    </script>
    <?php endif;
}
