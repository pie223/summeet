<?php
require_once __DIR__ . '/includes/bootstrap.php';

// This is the marketing/landing page. It's always shown here — including
// to logged-in users who click the "Summeet" brand link — the hero actions
// below just adapt depending on whether someone is already signed in.
$user = current_user();

$pageTitle = 'Meet at the peak';
require __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <div class="hero-text">
        <h1>Meet at the peak</h1>
        <p class="hero-sub">
            Summeet is a dating&amp;meeting app, but instead of the bar you hit the mountain.
            No profile swiping, no algorithm choosing for you, no money to pay to some company.
        </p>
        <div class="hero-actions">
            <?php if ($user): ?>
                <a href="hikes.php" class="btn btn-primary btn-lg">Browse hikes</a>
                <a href="create_hike.php" class="btn btn-ghost btn-lg">Suggest a hike</a>
            <?php else: ?>
                <a href="register.php" class="btn btn-primary btn-lg">Create your profile</a>
                <a href="login.php" class="btn btn-ghost btn-lg">I already have an account</a>
            <?php endif; ?>
            <?php if (!in_app()): ?>
            <a href="download.php" class="btn btn-ghost btn-lg">Download</a>
            <?php endif; ?>
            <a href="feedback.php" class="btn btn-ghost btn-lg">Feedback &amp; report</a>
        </div>
    </div>
    <div class="hero-art" aria-hidden="true">
        <svg viewBox="0 0 600 400" xmlns="http://www.w3.org/2000/svg">
            <rect width="600" height="400" fill="none"/>
            <polygon class="mtn-back" points="0,400 120,180 220,320 306,150 420,400"/>
            <polygon class="mtn-front" points="150,400 320,120 470,400"/>
            <polygon class="mtn-tip" points="320,120 298.8,155 338.6,155"/>
            <circle class="mtn-sun" cx="500" cy="80" r="40"/>
        </svg>
    </div>
</section>

<section class="features">
    <div class="feature-card">
        <h3>19 minutes coffee</h3>
        <div class="feature-icon"><svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 9h11v4a5 5 0 0 1-5 5h-1a5 5 0 0 1-5-5z"/><path d="M16 10h1.5a2.5 2.5 0 0 1 0 5H16"/><path d="M8 3.5v2M11.5 3.5v2"/></svg></div>
        <p>Meet up for a coffee in town some days before. Put a timer: 19 minutes. You will know the rest on the hike, if you are still down.</p>
    </div>
    <div class="feature-card">
        <h3>At least 3</h3>
        <div class="feature-icon"><svg viewBox="0 0 40 24" width="60" height="36" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="8" cy="8" r="3"/><path d="M2.5 21a5.5 5.5 0 0 1 11 0"/><circle cx="20" cy="8" r="3"/><path d="M14.5 21a5.5 5.5 0 0 1 11 0"/><circle cx="32" cy="8" r="3"/><path d="M26.5 21a5.5 5.5 0 0 1 11 0"/></svg></div>
        <p>The minimum number of people for a hike is 3. That adds safety, and maybe fun.</p>
    </div>
    <div class="feature-card">
        <h3>Group chat</h3>
        <div class="feature-icon"><svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 4h14a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1h-8l-4 4v-4H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1z"/></svg></div>
        <p>Once you are approved in a hike, all hikes members are added on the common chat.</p>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>