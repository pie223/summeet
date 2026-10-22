<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    redirect('hikes.php');
}

$errors = [];
$values = [
    'username' => '',
    'display_name' => '',
    'age' => '',
    'gender' => '',
    'gender_custom' => '',
    'political_orientation' => '0',
    'intent' => 'meeting',
    'bio' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($values as $key => $default) {
        if (isset($_POST[$key])) {
            $values[$key] = trim((string) $_POST[$key]);
        }
    }

    $username = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $values['username']));
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    $gender = $values['gender'] === 'other' ? $values['gender_custom'] : $values['gender'];

    if ($username === '' || strlen($username) < 3) {
        $errors[] = 'Username must be at least 3 characters (letters, numbers, underscore only).';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must be alphanumeric: it needs at least one letter and one number.';
    }
    if ($password !== $passwordConfirm) {
        $errors[] = 'Passwords do not match.';
    }
    if ($values['display_name'] === '') {
        $errors[] = 'Please enter a display name.';
    }
    $age = (int) $values['age'];
    if ($age < 18 || $age > 100) {
        $errors[] = 'You must be 18+ to use Summeet, and age must be a realistic number.';
    }
    if ($gender === '') {
        $errors[] = 'Please specify a gender.';
    }
    $orientation = (int) $values['political_orientation'];
    if ($orientation < -100 || $orientation > 100) {
        $orientation = max(-100, min(100, $orientation));
    }
    // Cake question: rank (1 = first, 3 = last) of each cake, all different.
    $cakeRanks = [];
    foreach ([1, 2, 3] as $i) {
        $cakeRanks[$i] = (int) ($_POST['cake'][$i] ?? 0);
    }
    $sortedRanks = array_values($cakeRanks);
    sort($sortedRanks);
    if ($sortedRanks !== [1, 2, 3]) {
        $errors[] = 'Cakes: give each one a different number, 1, 2 and 3.';
    }
    $cakeOrder = implode(',', $cakeRanks);
    $cakeWhy = mb_substr(trim((string) ($_POST['cake_why'] ?? '')), 0, 300);
    if (!in_array($values['intent'], ['dating', 'meeting'], true)) {
        $errors[] = 'Please choose whether you are open to dating or meeting only.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $errors[] = 'That username is already taken.';
        }
    }

    if (!$errors) {
        $stmt = $pdo->prepare("INSERT INTO users
            (username, password_hash, display_name, age, gender, political_orientation, intent, bio, cake_order, cake_why)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $username,
            password_hash($password, PASSWORD_DEFAULT),
            $values['display_name'],
            $age,
            $gender,
            $orientation,
            $values['intent'],
            trim((string) ($_POST['bio'] ?? '')),
            $cakeOrder,
            $cakeWhy,
        ]);
        $_SESSION['user_id'] = (int) $pdo->lastInsertId();
        flash_set('Welcome to Summeet, ' . $values['display_name'] . '! Your profile is live.', 'success');
        redirect('hikes.php');
    }
}

$pageTitle = 'Create your profile';
require __DIR__ . '/includes/header.php';
?>

<section class="auth-card">
    <h1>Create your profile</h1>
    <p class="muted">Takes two minutes. Be honest — the mountain finds you out anyway.</p>

    <?php if ($errors): ?>
        <div class="flash flash-error">
            <ul><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <form method="post" class="form" novalidate>
        <div class="form-row two-col">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" value="<?= h($values['username']) ?>" required minlength="3" pattern="[A-Za-z0-9_]+" placeholder="e.g. marta_trails">
            </div>
            <div class="form-group">
                <label for="display_name">Display name</label>
                <input type="text" id="display_name" name="display_name" value="<?= h($values['display_name']) ?>" required placeholder="What people see">
            </div>
        </div>

        <div class="form-row two-col">
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required minlength="6" pattern="(?=.*[A-Za-z])(?=.*[0-9]).{6,}" title="At least 6 characters, with at least one letter and one number">
            </div>
            <div class="form-group">
                <label for="password_confirm">Confirm password</label>
                <input type="password" id="password_confirm" name="password_confirm" required minlength="6">
            </div>
        </div>
        <p class="muted small" style="margin-top:-0.4rem;">At least 6 characters, with letters and numbers. <strong>If you forget your password, you will lose access to your account.</strong></p>

        <div class="form-row two-col">
            <div class="form-group">
                <label for="age">Age</label>
                <input type="number" id="age" name="age" min="18" max="100" value="<?= h($values['age']) ?>" required>
            </div>
            <div class="form-group">
                <label for="gender">Gender</label>
                <select id="gender" name="gender" onchange="document.getElementById('gender_custom_wrap').style.display = this.value === 'other' ? 'block' : 'none'">
                    <option value="">Select…</option>
                    <?php foreach (['woman' => 'Woman', 'man' => 'Man', 'nonbinary' => 'Non-binary', 'other' => 'Other / self-describe'] as $val => $label): ?>
                        <option value="<?= $val ?>" <?= $values['gender'] === $val ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
                <div id="gender_custom_wrap" style="<?= $values['gender'] === 'other' ? '' : 'display:none' ?>; margin-top:.5rem;">
                    <input type="text" name="gender_custom" placeholder="Self-describe" value="<?= h($values['gender_custom']) ?>">
                </div>
            </div>
        </div>

        <div class="form-group">
            <label>Looking for</label>
            <div class="intent-toggle">
                <label class="intent-option">
                    <input type="radio" name="intent" value="dating" <?= $values['intent'] === 'dating' ? 'checked' : '' ?>>
                    <span><svg class="icon-line" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z"/></svg> Open to dating</span>
                </label>
                <label class="intent-option">
                    <input type="radio" name="intent" value="meeting" <?= $values['intent'] === 'meeting' ? 'checked' : '' ?>>
                    <span><svg class="icon-line" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="8" cy="8" r="3"/><path d="M2.5 20a5.5 5.5 0 0 1 11 0"/><circle cx="17" cy="9" r="2.5"/><path d="M15 14.2A5 5 0 0 1 21.5 20"/></svg> Only meeting / hiking friends</span>
                </label>
            </div>
        </div>

        <div class="form-group">
            <label>Political orientation</label>
            <?php $horseshoeValue = (int) $values['political_orientation']; require __DIR__ . '/includes/horseshoe_slider.php'; ?>
        </div>

        <div class="form-group">
            <label for="bio">About you</label>
            <textarea id="bio" name="bio" rows="4" placeholder="What kind of hikes do you like? What are you hoping to find here?"><?= h($values['bio']) ?></textarea>
        </div>

        <?php $cakeSaved = array_pad(array_map('intval', (array) ($_POST['cake'] ?? [])), 4, 0); ?>
        <div class="form-group">
            <label>In which order would you eat these (vegan) cakes?</label>
            <p class="muted small" style="margin-top:0;">Put 1 for the cake you would eat first, 3 for the last. Each number only once.</p>
            <?php foreach ([1 => 'Cake I like', 2 => 'Cake I really like', 3 => 'Cake I really really like'] as $i => $cakeName): ?>
                <div style="display:flex; align-items:center; gap:.8rem; margin-bottom:.5rem;">
                    <select name="cake[<?= $i ?>]" required style="flex:0 0 5.5rem; width:5.5rem;" aria-label="Order for: <?= h($cakeName) ?>">
                        <option value="">–</option>
                        <?php foreach ([1, 2, 3] as $n): ?>
                            <option value="<?= $n ?>" <?= (int) ($_POST['cake'][$i] ?? 0) === $n ? 'selected' : '' ?>><?= $n ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span><?= h($cakeName) ?></span>
                </div>
            <?php endforeach; ?>
            <label for="cake_why" style="margin-top:.8rem;">Why? <span class="muted small">(optional)</span></label>
            <textarea id="cake_why" name="cake_why" rows="2" maxlength="300"><?= h((string) ($_POST['cake_why'] ?? '')) ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary btn-lg btn-block">Create my profile</button>
    </form>
    <p class="muted small">Already on Summeet? <a href="login.php">Log in</a></p>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>