<?php
require_once __DIR__ . '/includes/bootstrap.php';

/** Download page: the Android app and the open-source code. Open to everyone (logged in or not). */

// Put the real links here when you have them. While a link is empty, its button shows "link coming soon".
$apkUrl    = '';   // direct download of the .apk file
$fdroidUrl = '';   // the app's page on F-Droid
$repoUrl   = 'https://github.com/pie223/summeet ';   // the GitHub repository

$pageTitle = 'Download';
require __DIR__ . '/includes/header.php';

/** Prints a button, or a greyed-out "coming soon" button while there is no link yet. */
$linkButton = function (string $label, string $url, bool $primary): void {
    if ($url !== '') {
        echo '<a class="btn ' . ($primary ? 'btn-primary' : 'btn-ghost') . '" href="' . h($url) . '" rel="noopener">' . h($label) . '</a>';
    } else {
        echo '<span class="btn btn-ghost" aria-disabled="true" style="opacity:.55; cursor:not-allowed;">' . h($label) . ' · link coming soon</span>';
    }
};
?>

<h1>Download</h1>

<section class="card card-narrow">
    <h2>Get the app</h2>
    <p class="muted">Summeet for Android.</p>
    <p style="display:flex; gap:.7rem; flex-wrap:wrap;">
        <?php $linkButton('⬇️ Download the APK', $apkUrl, true); ?>
        <?php $linkButton('📱 Get it on F-Droid', $fdroidUrl, false); ?>
    </p>
</section>

<section class="card card-narrow">
    <h2>Free and open-source</h2>
    <p>Summeet is free and open-source, and you are welcome to add to the code: fix a bug, improve the design, translate it, or propose a new feature.</p>
    <p>📦 The repository is available on GitHub —
        <?php if ($repoUrl !== ''): ?><a href="<?= h($repoUrl) ?>" rel="noopener"><?= h($repoUrl) ?></a><?php else: ?><span class="muted">link coming soon</span><?php endif; ?>.
    </p>
    <p><svg class="copyleft-icon" viewBox="0 0 24 24" width="1.1em" height="1.1em" role="img" aria-label="Copyleft" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><title>Copyleft</title><circle cx="12" cy="12" r="10"/><path d="M9.2 9.2a3.8 3.8 0 1 1 0 5.6"/></svg> The code is <strong>copyleft</strong>: you can use, change and share it, and whatever you build on it stays free and open in the same way.</p>
    <p>🤖 This project was largely generated with AI. Like some thesis.</p>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
