<?php
require_once __DIR__ . '/includes/bootstrap.php';

/** Feedback & report page. Open to everyone (logged in or not). */
// Address that feedback and reports are sent to.
$contactEmail = 'summeet_4@proton.me';

$pageTitle = 'Feedback & report';
require __DIR__ . '/includes/header.php';
?>

<h1>Feedback &amp; report</h1>

<section class="card card-narrow">
    <h2>Tell us something</h2>
    <p class="muted">An idea, something that doesn't work, or a person or hike that shouldn't be here.</p>

    <form id="feedback-form" class="form" data-email="<?= h($contactEmail) ?>">
        <div class="form-group">
            <label>What is it?</label>
            <div class="intent-toggle">
                <label class="intent-option">
                    <input type="radio" name="kind" value="Feedback" checked>
                    <span>💡 Feedback</span>
                </label>
                <label class="intent-option">
                    <input type="radio" name="kind" value="Report">
                    <span>🚩 Report a problem</span>
                </label>
            </div>
        </div>
        <div class="form-group">
            <label for="message">Your message</label>
            <textarea id="message" name="message" rows="6" required minlength="10" maxlength="3000"></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Send</button>
        <p class="muted small">Sending opens your email app with the message ready, addressed to <?= h($contactEmail) ?>.</p>
    </form>
</section>

<script>
// No server or database involved: the form just prepares an email in the visitor's mail app.
document.getElementById('feedback-form').addEventListener('submit', function (e) {
    e.preventDefault();
    e.stopImmediatePropagation();      // skip the generic "Please wait…" button handler
    var f = e.target;
    var kind = f.querySelector('input[name="kind"]:checked').value;
    var body = f.message.value.trim();
    window.location.href = 'mailto:' + f.dataset.email
        + '?subject=' + encodeURIComponent((kind === 'Report' ? '[URGENT] ' : '') + 'Summeet ' + kind.toLowerCase())
        + '&body=' + encodeURIComponent(body);
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>