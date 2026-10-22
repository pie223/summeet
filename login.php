<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    redirect('hikes.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = strtolower(trim($_POST['username'] ?? ''));
    $password = $_POST['password'] ?? '';

    // Brute-force protection: after 5 wrong attempts from the same address or for the same
    // username in 15 minutes, logging in is paused for a while.
    $ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    $pdo->exec("DELETE FROM login_attempts WHERE created_at < (NOW() - INTERVAL 1 DAY)");
    $recent = $pdo->prepare("SELECT COUNT(*) FROM login_attempts
        WHERE created_at > (NOW() - INTERVAL 15 MINUTE) AND (ip = ? OR username = ?)");
    $recent->execute([$ip, $username]);

    if ((int) $recent->fetchColumn() >= 5) {
        $error = 'Too many wrong attempts. Please wait 15 minutes and try again.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $pdo->prepare('DELETE FROM login_attempts WHERE ip = ? OR username = ?')->execute([$ip, $username]);
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
            redirect('hikes.php');
        } else {
            $pdo->prepare('INSERT INTO login_attempts (ip, username) VALUES (?, ?)')->execute([$ip, substr($username, 0, 50)]);
            $error = 'Wrong username or password.';
        }
    }
}

$pageTitle = 'Log in';
require __DIR__ . '/includes/header.php';
?>

<section class="auth-card auth-card-narrow">
    <h1>Welcome back</h1>
    <?php if ($error): ?>
        <div class="flash flash-error"><?= h($error) ?></div>
    <?php endif; ?>
    <form method="post" class="form">
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" required autofocus value="<?= h($_POST['username'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-primary btn-lg btn-block">Log in</button>
    </form>
    <p class="muted small">
        No account? <a href="register.php">Create one</a>
    </p>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>