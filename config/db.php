<?php
/**
 * Summeet - database (MySQL / MariaDB). This is the only database file you need to edit.
 *
 * With XAMPP the settings below work as they are. Just create an empty database once:
 * open http://localhost/phpmyadmin, click "New", name it "summeet" and create it.
 * The tables and the demo accounts are then created automatically on the first visit.
 */

// ---- Your settings (XAMPP defaults) ----
$dbHost = '127.0.0.1';
$dbPort = '3306';
$dbName = 'summeet';
$dbUser = 'root';
$dbPass = '';           // XAMPP's root user has no password by default

// Raise this number whenever the database structure changes, so the setup runs once more.
const SUMMEET_SCHEMA_VERSION = 7;

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    die(
        'Database connection failed: ' . htmlspecialchars($e->getMessage()) .
        '. Make sure MySQL is running in XAMPP, that a database called "' . htmlspecialchars($dbName) .
        '" exists in phpMyAdmin, and that the settings at the top of config/db.php are correct.'
    );
}

// One cheap query per page: is the database set up and up to date? Only if not, run the setup.
$installedVersion = 0;
try {
    $installedVersion = (int) $pdo->query("SELECT v FROM app_meta WHERE k = 'schema'")->fetchColumn();
} catch (PDOException $e) {
    $installedVersion = 0;   // app_meta does not exist yet: first run
}
if ($installedVersion < SUMMEET_SCHEMA_VERSION) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS app_meta (k VARCHAR(30) PRIMARY KEY, v INT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    summeet_install_schema($pdo, $dbName);
    $stmt = $pdo->prepare("INSERT INTO app_meta (k, v) VALUES ('schema', ?) ON DUPLICATE KEY UPDATE v = VALUES(v)");
    $stmt->execute([SUMMEET_SCHEMA_VERSION]);
}

/**
 * Creates the tables, applies upgrades and adds the demo data. Runs only when needed (see above).
 */
function summeet_install_schema(PDO $pdo, string $dbName): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            display_name VARCHAR(100) NOT NULL,
            age INT NOT NULL,
            gender VARCHAR(50) NOT NULL,
            political_orientation INT NOT NULL DEFAULT 0,
            intent VARCHAR(20) NOT NULL DEFAULT 'meeting',
            bio TEXT,
            cake_order VARCHAR(10) NOT NULL DEFAULT '',
            cake_why VARCHAR(300) NOT NULL DEFAULT '',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS hikes (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            creator_id INT UNSIGNED NOT NULL,
            title VARCHAR(150) NOT NULL,
            mountain_name VARCHAR(150) NOT NULL,
            region VARCHAR(150) DEFAULT '',
            description TEXT,
            difficulty VARCHAR(20) NOT NULL DEFAULT 'moderate',
            hike_date DATE NOT NULL,
            min_participants INT NOT NULL DEFAULT 2,
            max_participants INT NOT NULL DEFAULT 4,
            route_points TEXT,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_hikes_creator FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS hike_requests (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            hike_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            message TEXT,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_hike_user (hike_id, user_id),
            CONSTRAINT fk_requests_hike FOREIGN KEY (hike_id) REFERENCES hikes(id) ON DELETE CASCADE,
            CONSTRAINT fk_requests_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS hike_messages (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            hike_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            body TEXT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_hike_messages (hike_id, id),
            CONSTRAINT fk_messages_hike FOREIGN KEY (hike_id) REFERENCES hikes(id) ON DELETE CASCADE,
            CONSTRAINT fk_messages_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS hike_chat_reads (
            hike_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            last_read_id INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (hike_id, user_id),
            CONSTRAINT fk_reads_hike FOREIGN KEY (hike_id) REFERENCES hikes(id) ON DELETE CASCADE,
            CONSTRAINT fk_reads_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS community_messages (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            body TEXT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_community_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Migration: add route_points to any database created before the maps
    // feature existed, so upgrading doesn't require dropping tables.
    $colCheck = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = ? AND table_name = 'hikes' AND column_name = 'route_points'"
    );
    $colCheck->execute([$dbName]);
    if ((int) $colCheck->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE hikes ADD COLUMN route_points TEXT AFTER max_participants");
    }

    // Migration: drop avatar_emoji from any database created before the avatar
    // feature was removed.
    $avatarColCheck = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = ? AND table_name = 'users' AND column_name = 'avatar_emoji'"
    );
    $avatarColCheck->execute([$dbName]);
    if ((int) $avatarColCheck->fetchColumn() > 0) {
        $pdo->exec("ALTER TABLE users DROP COLUMN avatar_emoji");
    }

    // Migration: cake question columns for databases created before it existed.
    foreach (['cake_order' => "VARCHAR(10) NOT NULL DEFAULT ''", 'cake_why' => "VARCHAR(300) NOT NULL DEFAULT ''"] as $col => $def) {
        $chk = $pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = ? AND table_name = 'users' AND column_name = ?"
        );
        $chk->execute([$dbName, $col]);
        if ((int) $chk->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE users ADD COLUMN {$col} {$def}");
        }
    }

    // Small helper: add an index only if it does not exist yet.
    $ensureIndex = function (string $table, string $name, string $columns) use ($pdo, $dbName): void {
        $chk = $pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema = ? AND table_name = ? AND index_name = ?"
        );
        $chk->execute([$dbName, $table, $name]);
        if ((int) $chk->fetchColumn() === 0) {
            $pdo->exec("CREATE INDEX {$name} ON {$table} ({$columns})");
        }
    };

    // Rate limiting for log-in attempts.
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS login_attempts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ip VARCHAR(45) NOT NULL,
            username VARCHAR(50) NOT NULL DEFAULT '',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Indexes for the columns the site filters and sorts by most.
    $ensureIndex('hikes', 'idx_hikes_date', 'hike_date');
    $ensureIndex('hike_requests', 'idx_requests_user_status', 'user_id, status');
    $ensureIndex('hike_requests', 'idx_requests_hike_status', 'hike_id, status');
    $ensureIndex('login_attempts', 'idx_login_ip', 'ip, created_at');
    $ensureIndex('login_attempts', 'idx_login_user', 'username, created_at');

    // Demo accounts and hikes, only when the database has no users yet (first run).
    if ((int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0) {
        require_once __DIR__ . '/seed.php';   // demo data (password for all demo accounts: summeet123)
        seed_demo_data($pdo);
    }
}