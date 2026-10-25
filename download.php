<?php
require_once __DIR__ . '/includes/bootstrap.php';

/** Download page: the Android app and the open-source code. Open to everyone (logged in or not). */

// Put the real links here when you have them. While a link is empty, its button shows "link coming soon".
$apkUrl    = 'https://github.com/pie223/summeet/releases/latest/download/summeet.apk';   // always the newest release
$fdroidUrl = '';   // the app's page on F-Droid
$repoUrl   = 'https://github.com/pie223/summeet';   // the GitHub repository
$siteUrl   = 'https://summeet.altervista.org';      // where the QR code sends people

$pageTitle = in_app() ? 'Share' : 'Download';
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

<h1><?= in_app() ? 'Share' : 'Download' ?></h1>

<?php if (!in_app()): ?>
<section class="card card-narrow">
    <h2>Get the app</h2>
    <p class="muted">Summeet for Android.</p>
    <p style="display:flex; gap:.7rem; flex-wrap:wrap;">
        <?php $linkButton('⬇️ Download the APK', $apkUrl, true); ?>
        <?php $linkButton('📱 Get it on F-Droid', $fdroidUrl, false); ?>
    </p>
    <p class="muted small" style="margin-bottom:0;">We don't surrender to Google Play. <a href="https://keepandroidopen.org/" rel="noopener">Here</a> is one of the many reasons why you can't download us there.</p>
</section>
<?php endif; ?>

<section class="card card-narrow">
    <?php if (!in_app()): ?><h2>Share Summeet</h2><?php endif; ?>
    <div id="qr-code" style="width:200px; height:200px; margin:0 auto; color:var(--mtn-front);"></div>
    <p class="muted small" id="qr-url" style="text-align:center; word-break:break-all; margin:0.2rem 0 0.9rem;"></p>
    <p style="text-align:center; margin:0;"><button type="button" class="btn btn-ghost btn-sm" id="qr-download">⬇️ Download as PNG</button></p>
</section>

<section class="card card-narrow">
    <h2>Free and open-source</h2>
    <p>Summeet is free and open-source, and you are welcome to add to the code: fix a bug, improve the design, translate it, or propose a new feature.</p>
    <p>📦 The repository is available on
        <?php if ($repoUrl !== ''): ?><a href="<?= h($repoUrl) ?>" rel="noopener" class="link-highlight">GitHub</a><?php else: ?>GitHub (<span class="muted">link coming soon</span>)<?php endif; ?>.
    </p>
    <p><svg class="copyleft-icon" viewBox="0 0 24 24" width="1.1em" height="1.1em" role="img" aria-label="Copyleft" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><title>Copyleft</title><circle cx="12" cy="12" r="10"/><path d="M9.2 9.2a3.8 3.8 0 1 1 0 5.6"/></svg> The code is <strong>copyleft</strong>: you can use, change and share it, and whatever you build on it stays free and open in the same way.</p>
    <p>🤖 This project was largely generated with AI. Like some tesi.</p>
</section>

<script src="assets/js/qr.js?v=<?= (int) @filemtime(__DIR__ . '/assets/js/qr.js') ?>"></script>
<script>
// The QR code points at the address set in $siteUrl at the top of this file.
(function () {
    var box = document.getElementById('qr-code');
    var url = <?= json_encode($siteUrl, JSON_UNESCAPED_SLASHES) ?>;
    var button = document.getElementById('qr-download');
    try {
        box.innerHTML = SummeetQR.svg(url, 1);               // slim white border on screen
        box.setAttribute('role', 'img');
        document.getElementById('qr-url').textContent = url;
    } catch (e) {
        box.parentNode.style.display = 'none';              // never show a broken code
        return;
    }

    // Download: the QR code with the web address underneath, as a PNG picture.
    button.addEventListener('click', function () {
        var m = SummeetQR.matrix(url), n = m.length;
        var px = 16, quiet = 4, side = (n + 2 * quiet) * px, textH = 44;
        var canvas = document.createElement('canvas');
        canvas.width = side; canvas.height = side + textH;
        var ctx = canvas.getContext('2d');
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--mtn-front').trim() || '#2c463f';
        for (var r = 0; r < n; r++) for (var c = 0; c < n; c++) {
            if (m[r][c]) ctx.fillRect((c + quiet) * px, (r + quiet) * px, px, px);
        }
        var size = 40;
        ctx.font = 'bold ' + size + 'px Arial, Helvetica, sans-serif';
        while (ctx.measureText(url).width > side - 40 && size > 12) { size -= 2; ctx.font = 'bold ' + size + 'px Arial, Helvetica, sans-serif'; }
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(url, side / 2, side - 8);
        var data = canvas.toDataURL('image/png');
        // Inside the Android app a normal download does nothing: hand the picture to the app instead.
        if (window.SummeetApp && typeof window.SummeetApp.savePng === 'function') {
            window.SummeetApp.savePng(data.split(',')[1], 'summeet-qr.png');
            return;
        }
        var link = document.createElement('a');
        link.download = 'summeet-qr.png';
        link.href = data;
        document.body.appendChild(link);
        link.click();
        link.remove();
    });
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>