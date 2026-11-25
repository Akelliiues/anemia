<?php
if (!defined('ANEMIA_STAFF_BOOT')) { http_response_code(404); exit; }

function anemiaDatabase(array $settings): PDO
{
    return new PDO(
        'mysql:host=' . $settings['host'] . ';dbname=' . $settings['database'] . ';charset=utf8mb4',
        $settings['user'],
        $settings['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );
}

function initializeAnemia(PDO $db, array $settings): void
{
    $db->exec("CREATE TABLE IF NOT EXISTS anemia_schema (version INTEGER PRIMARY KEY) ENGINE=InnoDB");
    if ($db->query('SELECT version FROM anemia_schema WHERE version=11')->fetchColumn()) return;
    $lock = $db->query("SELECT GET_LOCK(CONCAT(DATABASE(), ':anemia-schema'), 10)")->fetchColumn();
    if ((int)$lock !== 1) throw new RuntimeException('migration lock unavailable');
    try {
        if ($db->query('SELECT version FROM anemia_schema WHERE version=11')->fetchColumn()) return;
        if (!$db->query('SELECT version FROM anemia_schema WHERE version=1')->fetchColumn()) {
        $db->exec("CREATE TABLE IF NOT EXISTS anemia_province (
            code CHAR(2) PRIMARY KEY, name VARCHAR(100) NOT NULL, region TINYINT UNSIGNED NOT NULL,
            enabled TINYINT(1) NOT NULL DEFAULT 0, INDEX(region)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE IF NOT EXISTS anemia_staff (
            username VARCHAR(40) PRIMARY KEY, salt CHAR(48) NOT NULL, password_hash CHAR(64) NOT NULL,
            role VARCHAR(40) NOT NULL, enabled TINYINT(1) NOT NULL DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE IF NOT EXISTS anemia_login_guard (
            throttle_key CHAR(64) PRIMARY KEY,
            window_start BIGINT UNSIGNED NOT NULL, attempts SMALLINT UNSIGNED NOT NULL
        ) ENGINE=InnoDB");
        $db->exec("CREATE TABLE IF NOT EXISTS anemia_form_version (
            version_key VARCHAR(50) PRIMARY KEY, state VARCHAR(20) NOT NULL DEFAULT 'draft',
            definition LONGTEXT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->beginTransaction();
        $seed = $db->prepare('INSERT IGNORE INTO anemia_province (code,name,region) VALUES (?,?,?)');
        foreach (require __DIR__ . '/provinces.php' as $province) $seed->execute($province);
        $seed = $db->prepare("INSERT IGNORE INTO anemia_staff (username,salt,password_hash,role) VALUES ('anemia-admin',?,?,'account_admin')");
        $seed->execute([$settings['admin_salt'], $settings['admin_hash']]);
        $definition = file_get_contents(__DIR__ . '/assessment-draft.json');
        if ($definition === false) throw new RuntimeException('missing assessment draft');
        $seed = $db->prepare("INSERT IGNORE INTO anemia_form_version (version_key,definition) VALUES ('draft-10q-v1',?)");
        $seed->execute([$definition]);
        $db->exec('INSERT INTO anemia_schema (version) VALUES (1)');
        $db->commit();
        }
        if (!$db->query('SELECT version FROM anemia_schema WHERE version=2')->fetchColumn()) {
            $db->exec("CREATE TABLE IF NOT EXISTS anemia_staff_access (
                username VARCHAR(40) PRIMARY KEY, region TINYINT UNSIGNED NULL, province_code CHAR(2) NULL,
                FOREIGN KEY (username) REFERENCES anemia_staff(username),
                FOREIGN KEY (province_code) REFERENCES anemia_province(code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $db->exec("CREATE TABLE IF NOT EXISTS anemia_access_audit (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, actor VARCHAR(40) NOT NULL,
                target_username VARCHAR(40) NOT NULL, action_name VARCHAR(20) NOT NULL,
                role_name VARCHAR(40) NOT NULL, region TINYINT UNSIGNED NULL, province_code CHAR(2) NULL,
                enabled TINYINT(1) NOT NULL, changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $db->exec('INSERT INTO anemia_schema (version) VALUES (2)');
        }
        if (!$db->query('SELECT version FROM anemia_schema WHERE version=3')->fetchColumn()) {
            $db->beginTransaction();
            $seedUser = $db->prepare("INSERT INTO anemia_staff (username,salt,password_hash,role,enabled) VALUES (?,?,?,'regional_report',0)");
            $seedScope = $db->prepare('INSERT INTO anemia_staff_access (username,region) VALUES (?,?)');
            $seedAudit = $db->prepare("INSERT INTO anemia_access_audit (actor,target_username,action_name,role_name,region,enabled)
                VALUES ('anemia-admin',?,'seed','regional_report',?,0)");
            foreach (require __DIR__ . '/regional-accounts.php' as [$region,$username,$salt,$digest]) {
                $seedUser->execute([$username,$salt,$digest]);
                $seedScope->execute([$username,$region]);
                $seedAudit->execute([$username,$region]);
            }
            $db->exec('INSERT INTO anemia_schema (version) VALUES (3)');
            $db->commit();
        }
        if (!$db->query('SELECT version FROM anemia_schema WHERE version=4')->fetchColumn()) {
        $db->exec("CREATE TABLE IF NOT EXISTS anemia_evaluation (
            submission_id CHAR(64) PRIMARY KEY, form_version VARCHAR(30) NOT NULL,
            audience VARCHAR(20) NOT NULL, province_code CHAR(2) NULL,
            overall TINYINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL,
            CHECK (overall BETWEEN 1 AND 5),
            FOREIGN KEY (province_code) REFERENCES anemia_province(code),
            INDEX (province_code, created_at), INDEX (audience, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE IF NOT EXISTS anemia_evaluation_answer (
            submission_id CHAR(64) NOT NULL, item_key VARCHAR(32) NOT NULL,
            rating TINYINT UNSIGNED NOT NULL, CHECK (rating BETWEEN 1 AND 5),
            PRIMARY KEY (submission_id,item_key),
            FOREIGN KEY (submission_id) REFERENCES anemia_evaluation(submission_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE IF NOT EXISTS anemia_evaluation_suggestion (
            submission_id CHAR(64) NOT NULL, topic_key VARCHAR(32) NOT NULL,
            PRIMARY KEY (submission_id,topic_key),
            FOREIGN KEY (submission_id) REFERENCES anemia_evaluation(submission_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec('INSERT INTO anemia_schema (version) VALUES (4)');
        }
        if (!$db->query('SELECT version FROM anemia_schema WHERE version=5')->fetchColumn()) {
        $db->exec("CREATE TABLE IF NOT EXISTS anemia_draft_activity (
            round_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            event_name VARCHAR(24) CHARACTER SET ascii NOT NULL,
            outcome VARCHAR(8) CHARACTER SET ascii NULL,
            province_code CHAR(2) NULL,
            started_on DATE NOT NULL,
            PRIMARY KEY (round_id,event_name),
            INDEX (started_on,province_code,event_name),
            FOREIGN KEY (province_code) REFERENCES anemia_province(code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec('INSERT INTO anemia_schema (version) VALUES (5)');
        }
        if (!$db->query('SELECT version FROM anemia_schema WHERE version=6')->fetchColumn()) {
        $db->exec("CREATE TABLE IF NOT EXISTS anemia_host_setting (
            setting_key VARCHAR(40) PRIMARY KEY, enabled TINYINT(1) NOT NULL,
            changed_by VARCHAR(40) NULL, changed_at DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("INSERT IGNORE INTO anemia_host_setting (setting_key,enabled) VALUES ('staff_sample',1)");
        $db->exec('INSERT INTO anemia_schema (version) VALUES (6)');
        }
        if (!$db->query('SELECT version FROM anemia_schema WHERE version=7')->fetchColumn()) {
        $db->exec("CREATE TABLE IF NOT EXISTS anemia_evaluation_comment (
            submission_id CHAR(64) PRIMARY KEY, comment_text VARCHAR(500) NOT NULL,
            FOREIGN KEY (submission_id) REFERENCES anemia_evaluation(submission_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec('INSERT INTO anemia_schema (version) VALUES (7)');
        }
        if (!$db->query('SELECT version FROM anemia_schema WHERE version=8')->fetchColumn()) {
        $db->exec("CREATE TABLE IF NOT EXISTS anemia_otp_throttle (
            bucket CHAR(64) CHARACTER SET ascii NOT NULL,window_slot INT UNSIGNED NOT NULL,
            attempts INT UNSIGNED NOT NULL,PRIMARY KEY(bucket,window_slot),INDEX(window_slot)
        ) ENGINE=InnoDB");
        $db->exec("CREATE TABLE IF NOT EXISTS anemia_contact_queue (
            receipt CHAR(19) CHARACTER SET ascii PRIMARY KEY,facility VARCHAR(255) NOT NULL,
            request_kind VARCHAR(16) NOT NULL,contact_name VARCHAR(80) NOT NULL,phone VARCHAR(16) NOT NULL,
            preferred_date DATE NULL,submitted_at DATETIME NOT NULL,state VARCHAR(20) NOT NULL DEFAULT 'pending',
            INDEX(state,submitted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec('INSERT INTO anemia_schema (version) VALUES (8)');
        }
        if (!$db->query('SELECT version FROM anemia_schema WHERE version=9')->fetchColumn()) {
            $db->exec('CREATE TABLE IF NOT EXISTS anemia_contact_demo_queue LIKE anemia_contact_queue');
            $db->exec('INSERT INTO anemia_schema (version) VALUES (9)');
        }
        $db->exec("CREATE TABLE IF NOT EXISTS anemia_demo_report (
            scope_key VARCHAR(12) CHARACTER SET ascii NOT NULL,
            month_key CHAR(7) CHARACTER SET ascii NOT NULL,
            report_data MEDIUMTEXT NOT NULL,
            PRIMARY KEY(scope_key,month_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec('INSERT IGNORE INTO anemia_schema (version) VALUES (10)');
        $db->exec("CREATE TABLE IF NOT EXISTS anemia_request_history (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            receipt CHAR(19) CHARACTER SET ascii NOT NULL,
            state VARCHAR(20) NOT NULL, actor VARCHAR(40) NOT NULL,
            changed_at DATETIME NOT NULL, INDEX(receipt,id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec('INSERT INTO anemia_schema (version) VALUES (11)');
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        throw $error;
    } finally {
        $db->query("SELECT RELEASE_LOCK(CONCAT(DATABASE(), ':anemia-schema'))");
    }
}

function consumeAnemiaAttempt(PDO $db, string $address): bool
{
    $key = hash('sha256', $address);
    $now = time();
    $window = intdiv($now, 300) * 300;
    $statement = $db->prepare('INSERT INTO anemia_login_guard(throttle_key,window_start,attempts) VALUES (?,?,1)
        ON DUPLICATE KEY UPDATE attempts=IF(window_start=VALUES(window_start),LEAST(attempts+1,1000),1),window_start=VALUES(window_start)');
    $statement->execute([$key, $window]);
    $statement = $db->prepare('SELECT attempts FROM anemia_login_guard WHERE throttle_key=?');
    $statement->execute([$key]);
    return (int)$statement->fetchColumn() <= 10;
}
