<?php
/**
 * MODX Count & Like AJAX endpoint for MODX Revolution 2.x.
 * GET  ?id=123
 * POST id=123&action=view|toggle_like
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, private');
header('X-Content-Type-Options: nosniff');

function mcl_reply(int $status, array $data): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function mcl_same_origin(): bool
{
    if (isset($_SERVER['HTTP_SEC_FETCH_SITE']) && !in_array($_SERVER['HTTP_SEC_FETCH_SITE'], array('same-origin', 'none'), true)) {
        return false;
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
    if ($host === '' || preg_match('/[\r\n]/', $host)) {
        return false;
    }
    $expected = strtolower($scheme . '://' . $host);
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? trim($_SERVER['HTTP_ORIGIN']) : '';
    if ($origin !== '') {
        return strtolower($origin) === $expected;
    }
    $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
    if ($referer === '') {
        return false;
    }
    $parts = parse_url($referer);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
        return false;
    }
    $ref = strtolower($parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : ''));
    return $ref === $expected;
}

$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '';
if ($method !== 'GET' && $method !== 'POST') {
    header('Allow: GET, POST');
    mcl_reply(405, array('ok' => false, 'error' => 'method_not_allowed'));
}
if ($method === 'POST') {
    if (!mcl_same_origin()) {
        mcl_reply(403, array('ok' => false, 'error' => 'origin_denied'));
    }
    if (isset($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > 1024) {
        mcl_reply(413, array('ok' => false, 'error' => 'request_too_large'));
    }
}

$idInput = $method === 'POST' ? (isset($_POST['id']) ? $_POST['id'] : null) : (isset($_GET['id']) ? $_GET['id'] : null);
$id = filter_var($idInput, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));
if (!$id || $id > 2147483647) {
    mcl_reply(400, array('ok' => false, 'error' => 'invalid_id'));
}
$action = $method === 'POST' ? (isset($_POST['action']) ? (string)$_POST['action'] : '') : '';
if ($method === 'POST' && !in_array($action, array('view', 'toggle_like'), true)) {
    mcl_reply(400, array('ok' => false, 'error' => 'invalid_action'));
}

$root = dirname(__DIR__, 3);
$configFile = $root . '/config.core.php';
if (!is_file($configFile)) {
    mcl_reply(500, array('ok' => false, 'error' => 'modx_config_missing'));
}
require_once $configFile;
if (!defined('MODX_CORE_PATH') || !is_file(MODX_CORE_PATH . 'model/modx/modx.class.php')) {
    mcl_reply(500, array('ok' => false, 'error' => 'modx_bootstrap_missing'));
}
require_once MODX_CORE_PATH . 'model/modx/modx.class.php';

try {
    $modx = new modX();
    if (!$modx->initialize('web')) {
        throw new RuntimeException('MODX initialization failed');
    }
    $resource = $modx->getObject('modResource', (int)$id);
    $now = time();
    if (!$resource || !(int)$resource->get('published') || (int)$resource->get('deleted')
        || ((int)$resource->get('pub_date') > 0 && (int)$resource->get('pub_date') > $now)
        || ((int)$resource->get('unpub_date') > 0 && (int)$resource->get('unpub_date') <= $now)
        || (string)$resource->get('context_key') !== 'web') {
        mcl_reply(404, array('ok' => false, 'error' => 'resource_not_found'));
    }

    $prefix = (string)$modx->getOption('table_prefix');
    if (!preg_match('/^[A-Za-z0-9_]+$/', $prefix)) {
        throw new RuntimeException('Invalid table prefix');
    }
    $statsTable = '`' . $prefix . 'mcl_stats`';
    $viewersTable = '`' . $prefix . 'mcl_viewers`';
    $likesTable = '`' . $prefix . 'mcl_likes`';
    $pdo = $modx->getConnection();
    if (!$pdo instanceof PDO) {
        throw new RuntimeException('No PDO connection');
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $cookie = isset($_COOKIE['mcl_vid']) && is_string($_COOKIE['mcl_vid']) ? $_COOKIE['mcl_vid'] : '';
    if (!preg_match('/^[a-f0-9]{64}$/D', $cookie)) {
        $cookie = bin2hex(random_bytes(32));
        setcookie('mcl_vid', $cookie, array(
            'expires' => time() + 365 * 86400,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax'
        ));
    }
    $hash = hash('sha256', $cookie);
    $id = (int)$id;

    if ($method === 'POST') {
        $pdo->beginTransaction();
        try {
            $insert = $pdo->prepare('INSERT IGNORE INTO ' . $statsTable . ' (resource_id, views, likes) VALUES (?, 0, 0)');
            $insert->execute(array($id));
            $lock = $pdo->prepare('SELECT views, likes FROM ' . $statsTable . ' WHERE resource_id = ? FOR UPDATE');
            $lock->execute(array($id));
            $lock->fetch(PDO::FETCH_ASSOC);

            if ($action === 'view') {
                $query = $pdo->prepare('SELECT last_viewed_at FROM ' . $viewersTable . ' WHERE resource_id = ? AND visitor_hash = ?');
                $query->execute(array($id, $hash));
                $previous = $query->fetchColumn();
                if ($previous === false) {
                    $insertView = $pdo->prepare('INSERT INTO ' . $viewersTable . ' (resource_id, visitor_hash, last_viewed_at) VALUES (?, ?, UTC_TIMESTAMP())');
                    $insertView->execute(array($id, $hash));
                    $pdo->prepare('UPDATE ' . $statsTable . ' SET views = views + 1 WHERE resource_id = ?')->execute(array($id));
                } else {
                    $updateView = $pdo->prepare('UPDATE ' . $viewersTable . ' SET last_viewed_at = UTC_TIMESTAMP() WHERE resource_id = ? AND visitor_hash = ? AND last_viewed_at <= UTC_TIMESTAMP() - INTERVAL 12 HOUR');
                    $updateView->execute(array($id, $hash));
                    if ($updateView->rowCount() > 0) {
                        $pdo->prepare('UPDATE ' . $statsTable . ' SET views = views + 1 WHERE resource_id = ?')->execute(array($id));
                    }
                }
            } else {
                $existing = $pdo->prepare('SELECT 1 FROM ' . $likesTable . ' WHERE resource_id = ? AND visitor_hash = ?');
                $existing->execute(array($id, $hash));
                if ($existing->fetchColumn()) {
                    $pdo->prepare('DELETE FROM ' . $likesTable . ' WHERE resource_id = ? AND visitor_hash = ?')->execute(array($id, $hash));
                    $pdo->prepare('UPDATE ' . $statsTable . ' SET likes = GREATEST(likes - 1, 0) WHERE resource_id = ?')->execute(array($id));
                } else {
                    $pdo->prepare('INSERT INTO ' . $likesTable . ' (resource_id, visitor_hash, created_at) VALUES (?, ?, UTC_TIMESTAMP())')->execute(array($id, $hash));
                    $pdo->prepare('UPDATE ' . $statsTable . ' SET likes = likes + 1 WHERE resource_id = ?')->execute(array($id));
                }
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    $query = $pdo->prepare('SELECT views, likes FROM ' . $statsTable . ' WHERE resource_id = ?');
    $query->execute(array($id));
    $row = $query->fetch(PDO::FETCH_ASSOC);
    $likedQuery = $pdo->prepare('SELECT 1 FROM ' . $likesTable . ' WHERE resource_id = ? AND visitor_hash = ?');
    $likedQuery->execute(array($id, $hash));
    mcl_reply(200, array(
        'ok' => true,
        'id' => $id,
        'views' => $row ? (int)$row['views'] : 0,
        'likes' => $row ? (int)$row['likes'] : 0,
        'liked' => (bool)$likedQuery->fetchColumn()
    ));
} catch (Throwable $e) {
    error_log('[MODX Count & Like] ' . $e->getMessage());
    mcl_reply(500, array('ok' => false, 'error' => 'server_error'));
}
