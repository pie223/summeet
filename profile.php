<?php
require_once __DIR__ . '/includes/bootstrap.php';

$user = require_login();
$errors = [];

// ---------- Delete my account ----------
// Cascading foreign keys also remove this user's hikes, requests and chat messages.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_account') {
    if (password_verify((string) ($_POST['confirm_password'] ?? ''), $user['password_hash'])) {
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$user['id']]);
        $_SESSION = [];
        flash_set('Your account and everything linked to it were deleted. Goodbye, and thanks for hiking with us.', 'info');
        redirect('index.php');
    }
    $errors[] = 'Wrong password: your account was not deleted.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $display_name = trim($_POST['display_name'] ?? '');
    $age = (int) ($_POST['age'] ?? 0);
    $gender = trim($_POST['gender'] ?? '');
    $gender = $gender === 'other' ? trim($_POST['gender_custom'] ?? '') : $gender;
    $orientation = max(-100, min(100, (int) ($_POST['political_orientation'] ?? 0)));
    $intent = $_POST['intent'] ?? 'meeting';
    $bio = trim($_POST['bio'] ?? '');
    // Cake question: rank (1 = first, 3 = last) of each of the three cakes, all different.
    $cakeRanks = [];
    foreach ([1, 2, 3] as $i) {
        $cakeRanks[$i] = (int) ($_POST['cake'][$i] ?? 0);
    }
    $cake_order = implode(',', $cakeRanks);
    $cake_why = mb_substr(trim($_POST['cake_why'] ?? ''), 0, 300);

    if ($display_name === '') $errors[] = 'Display name cannot be empty.';
    if ($age < 18 || $age > 100) $errors[] = 'Age must be between 18 and 100.';
    if ($gender === '') $errors[] = 'Please specify a gender.';
    if (mb_strlen($bio) < 100) $errors[] = 'Please write at least 100 characters (a couple of lines) in "About you".';
    $sortedRanks = array_values($cakeRanks);
    sort($sortedRanks);
    if ($sortedRanks !== [1, 2, 3]) $errors[] = 'Cakes: give each one a different number, 1, 2 and 3.';
    if (!in_array($intent, ['dating', 'meeting'], true)) $errors[] = 'Invalid intent.';

    if (!$errors) {
        $stmt = $pdo->prepare("UPDATE users SET display_name=?, age=?, gender=?, political_orientation=?, intent=?, bio=?, cake_order=?, cake_why=? WHERE id=?");
        $stmt->execute([$display_name, $age, $gender, $orientation, $intent, $bio, $cake_order, $cake_why, $user['id']]);
        flash_set('Profile updated.', 'success');
        redirect('profile.php');
    }

    // keep entered values on error
    $user = array_merge($user, compact('display_name', 'age', 'gender', 'orientation', 'intent', 'bio', 'cake_order', 'cake_why'));
    $user['political_orientation'] = $orientation;
}

// My hikes and my requests summary
$myHikes = $pdo->prepare('SELECT * FROM hikes WHERE creator_id = ? ORDER BY hike_date ASC');
$myHikes->execute([$user['id']]);
$myHikes = $myHikes->fetchAll();

$myRequests = $pdo->prepare("SELECT r.*, h.title, h.mountain_name, h.hike_date FROM hike_requests r
    JOIN hikes h ON h.id = r.hike_id WHERE r.user_id = ? ORDER BY r.created_at DESC");
$myRequests->execute([$user['id']]);
$myRequests = $myRequests->fetchAll();

// Hikes I suggested: upcoming first (soonest on top), then past ones (most recent first).
$today = date('Y-m-d');
usort($myHikes, function ($a, $b) use ($today) {
    $ap = $a['hike_date'] < $today; $bp = $b['hike_date'] < $today;
    if ($ap !== $bp) return $ap <=> $bp;
    return $ap ? strcmp($b['hike_date'], $a['hike_date']) : strcmp($a['hike_date'], $b['hike_date']);
});
$VISIBLE = 3;   // how many items show before the rest collapses

$pageTitle = 'My profile';
require __DIR__ . '/includes/header.php';
?>

<div class="profile-layout">
<section class="card">
    <h1><?= h($user['display_name']) ?></h1>
    <p class="muted">@<?= h($user['username']) ?> · Member since <?= h(date('M Y', strtotime($user['created_at']))) ?></p>

    <?php if ($errors): ?>
        <div class="flash flash-error">
            <ul><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <form method="post" class="form">
        <div class="form-row two-col">
            <div class="form-group">
                <label for="display_name">Display name</label>
                <input type="text" id="display_name" name="display_name" value="<?= h($user['display_name']) ?>" required>
            </div>
            <div class="form-group">
                <label for="age">Age</label>
                <input type="number" id="age" name="age" min="18" max="100" value="<?= h((string) $user['age']) ?>" required>
            </div>
        </div>

        <div class="form-group">
            <label for="gender">Gender</label>
            <?php $knownGenders = ['woman' => 'Woman', 'man' => 'Man', 'nonbinary' => 'Non-binary'];
            $isKnown = array_key_exists($user['gender'], $knownGenders); ?>
            <select id="gender" name="gender" onchange="document.getElementById('gender_custom_wrap').style.display = this.value === 'other' ? 'block' : 'none'">
                <?php foreach ($knownGenders as $val => $label): ?>
                    <option value="<?= $val ?>" <?= $user['gender'] === $val ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
                <option value="other" <?= !$isKnown ? 'selected' : '' ?>>Other / self-describe</option>
            </select>
            <div id="gender_custom_wrap" style="<?= !$isKnown ? '' : 'display:none' ?>; margin-top:.5rem;">
                <input type="text" name="gender_custom" placeholder="Self-describe" value="<?= !$isKnown ? h($user['gender']) : '' ?>">
            </div>
        </div>

        <div class="form-group">
            <label>Looking for</label>
            <div class="intent-toggle">
                <label class="intent-option">
                    <input type="radio" name="intent" value="dating" <?= $user['intent'] === 'dating' ? 'checked' : '' ?>>
                    <span>💞 Open to dating</span>
                </label>
                <label class="intent-option">
                    <input type="radio" name="intent" value="meeting" <?= $user['intent'] === 'meeting' ? 'checked' : '' ?>>
                    <span>🤝 Only meeting / hiking friends</span>
                </label>
            </div>
        </div>

        <div class="form-group">
            <label>Political orientation</label>
            <?php $horseshoeValue = (int) $user['political_orientation']; require __DIR__ . '/includes/horseshoe_slider.php'; ?>
        </div>

        <div class="form-group">
            <label for="bio">About you</label>
            <textarea id="bio" name="bio" rows="4" required minlength="100" placeholder="Tell us a bit about yourself: at least 100 characters, a couple of lines"><?= h($user['bio']) ?></textarea>
        </div>

        <?php $cakeSaved = array_pad(explode(',', (string) ($user['cake_order'] ?? '')), 3, ''); ?>
        <div class="form-group">
            <label>In which order would you eat these (vegan) cakes?</label>
            <p class="muted small" style="margin-top:0;">Put 1 for the cake you would eat first, 3 for the last. Each number only once.</p>
            <?php foreach ([1 => 'Cake I like', 2 => 'Cake I really like', 3 => 'Cake I really really like'] as $i => $cakeName): ?>
                <div style="display:flex; align-items:center; gap:.8rem; margin-bottom:.5rem;">
                    <select name="cake[<?= $i ?>]" required style="flex:0 0 5.5rem; width:5.5rem;" aria-label="Order for: <?= h($cakeName) ?>">
                        <option value="">–</option>
                        <?php foreach ([1, 2, 3] as $n): ?>
                            <option value="<?= $n ?>" <?= (string) $cakeSaved[$i - 1] === (string) $n ? 'selected' : '' ?>><?= $n ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span>🍰 <?= h($cakeName) ?></span>
                </div>
            <?php endforeach; ?>
            <label for="cake_why" style="margin-top:.8rem;">Why? <span class="muted small">(optional)</span></label>
            <textarea id="cake_why" name="cake_why" rows="2" maxlength="300"><?= h((string) ($user['cake_why'] ?? '')) ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary">Save changes</button>
    </form>

    <details <?= ($_POST['action'] ?? '') === 'delete_account' ? 'open' : '' ?> style="margin-top:2rem; padding-top:1rem; border-top:1px solid var(--border);">
        <summary style="cursor:pointer; color:var(--danger); font-weight:600;">Delete my account</summary>
        <form method="post" class="form" style="margin-top:1rem;"
              onsubmit="return confirm('Delete your account for good? This cannot be undone.');">
            <p class="muted small">This permanently deletes your profile, <strong>all the hikes you proposed</strong> (with their requests and group chats), your join requests and your chat messages. It cannot be undone.</p>
            <input type="hidden" name="action" value="delete_account">
            <div class="form-group">
                <label for="confirm_password">Type your password to confirm</label>
                <input type="password" id="confirm_password" name="confirm_password" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-danger">Delete my account</button>
        </form>
    </details>
</section>

<aside class="profile-side">
    <div class="card">
        <h2>Hikes you suggested</h2>
        <?php if (!$myHikes): ?>
            <p class="muted small">You haven't suggested a hike yet. <a href="create_hike.php">Suggest one →</a></p>
        <?php else: ?>
            <?php foreach ([array_slice($myHikes, 0, $VISIBLE), array_slice($myHikes, $VISIBLE)] as $part => $chunk): if (!$chunk) continue; ?>
                <?php if ($part === 1): ?><details><summary class="small" style="cursor:pointer; margin-top:.5rem;">Show <?= count($chunk) ?> more</summary><?php endif; ?>
                <ul class="mini-list">
                    <?php foreach ($chunk as $h2): $spots = hike_spots($pdo, (int) $h2['id'], (int) $h2['max_participants']); ?>
                        <li>
                            <a href="hike.php?id=<?= (int) $h2['id'] ?>"><?= h($h2['title']) ?></a>
                            <span class="muted small"><?= h($h2['mountain_name']) ?> · <?= h(date('j M', strtotime($h2['hike_date']))) ?> · <?= $spots['approved'] ?>/<?= (int) $h2['max_participants'] ?> joined<?= $h2['hike_date'] < $today ? ' · past' : '' ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($part === 1): ?></details><?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Your join requests</h2>
        <?php if (!$myRequests): ?>
            <p class="muted small">You haven't asked to join a hike yet. <a href="hikes.php">Browse hikes →</a></p>
        <?php else: ?>
            <?php foreach ([array_slice($myRequests, 0, $VISIBLE), array_slice($myRequests, $VISIBLE)] as $part => $chunk): if (!$chunk) continue; ?>
                <?php if ($part === 1): ?><details><summary class="small" style="cursor:pointer; margin-top:.5rem;">Show <?= count($chunk) ?> more</summary><?php endif; ?>
                <ul class="mini-list">
                    <?php foreach ($chunk as $r): ?>
                        <li>
                            <a href="hike.php?id=<?= (int) $r['hike_id'] ?>"><?= h($r['title']) ?></a>
                            <span class="status-pill status-<?= h($r['status']) ?>"><?= h(ucfirst($r['status'])) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($part === 1): ?></details><?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</aside>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>