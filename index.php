<?php

/*
|--------------------------------------------------------------------------
| Anonymous Cloud Notes - Main Entry Point
|--------------------------------------------------------------------------
| Single file application. All encryption happens client-side.
| Server only stores encrypted ciphertext.
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Security Note
|--------------------------------------------------------------------------
| These defaults are intentionally strict.
| Do not loosen CSP, cookie flags, or file permissions unless you
| fully understand the security implications.
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Secure Session Cookies
|--------------------------------------------------------------------------
*/
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Strict'
]);

session_start();

if (!isset($_SESSION['initialized'])) {
    session_regenerate_id(true);
    $_SESSION['initialized'] = true;
}

/*
|--------------------------------------------------------------------------
| Security Headers
|--------------------------------------------------------------------------
*/
header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

header(
    "Content-Security-Policy: " .
    "default-src 'self'; " .
    "script-src 'self'; " .
    "style-src 'self'; " .
    "connect-src 'self'; " .
    "object-src 'none'; " .
    "base-uri 'none'; " .
    "frame-ancestors 'none'; " .
    "form-action 'self';"
);

/*
|--------------------------------------------------------------------------
| Configuration - CHANGE FOR SELF-HOSTING
|--------------------------------------------------------------------------
*/
$baseUrl = 'https://notes.chrisjones.online';   // ← CHANGE TO YOUR DOMAIN

$notesDir = dirname(__DIR__) . '/storage/notes';

if (!is_dir($notesDir)) {
    if (!mkdir($notesDir, 0700, true)) {
        http_response_code(500);
        exit('Failed to initialize storage directory');
    }
}

if (!isset($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

/*
|--------------------------------------------------------------------------
| Constants
|--------------------------------------------------------------------------
*/
define('NOTE_TTL', 30 * 24 * 60 * 60);        // 30 days
define('RATE_LIMIT_TTL', 3600);               // 1 hour

/*
|--------------------------------------------------------------------------
| Cleanup Functions
|--------------------------------------------------------------------------
*/
function cleanupOldNotes($notesDir) {
    $threshold = time() - NOTE_TTL;
    if (!is_dir($notesDir)) return;

    foreach (glob($notesDir . '/*.txt') as $file) {
        $modified = @filemtime($file);
        if ($modified !== false && $modified < $threshold) {
            @unlink($file);
        }
    }
}

function cleanupRateLimits($limitDir) {
    $threshold = time() - RATE_LIMIT_TTL;
    if (!is_dir($limitDir)) return;

    foreach (glob($limitDir . '/*.json') as $file) {
        $modified = @filemtime($file);
        if ($modified !== false && $modified < $threshold) {
            @unlink($file);
        }
    }
}

// Run cleanup deterministically on every request
cleanupOldNotes($notesDir);
cleanupRateLimits(dirname(__DIR__) . '/storage/rate_limits');

/*
|--------------------------------------------------------------------------
| Rate Limiting (1 note per hour per IP) - Fully Hardened
|--------------------------------------------------------------------------
*/
function checkRateLimit() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $limitDir = dirname(__DIR__) . '/storage/rate_limits';
    $limitFile = $limitDir . '/' . md5($ip) . '.json';

    if (!is_dir($limitDir)) {
        if (!mkdir($limitDir, 0700, true)) return;
    }

    // Pre-check and clean expired file
    if (is_file($limitFile)) {
        $data = json_decode(file_get_contents($limitFile), true);
        if (is_array($data) && isset($data['reset_time']) && time() > $data['reset_time']) {
            @unlink($limitFile);
        }
    }

    $fp = fopen($limitFile, 'c+');
    if (!$fp) return;

    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        return;
    }

    rewind($fp);
    $content = stream_get_contents($fp);

    $data = ['count' => 0, 'reset_time' => time() + RATE_LIMIT_TTL];

    if ($content) {
        $decoded = json_decode($content, true);
        if (is_array($decoded) && isset($decoded['count'], $decoded['reset_time'])) {
            $data = $decoded;
        }
    }

    if (time() > $data['reset_time']) {
        ftruncate($fp, 0);
        flock($fp, LOCK_UN);
        fclose($fp);
        @unlink($limitFile);
        return;
    }

    if ($data['count'] >= 1) {
        flock($fp, LOCK_UN);
        fclose($fp);
        http_response_code(429);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => 'Rate limit reached. Only 1 new note per hour allowed. Please try again later.'
        ]);
        exit;
    }

    $data['count']++;
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($data));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
}

/*
|--------------------------------------------------------------------------
| Handle POST Requests
|--------------------------------------------------------------------------
| Note: Actual encryption happens in the browser (script.js)
| Server only stores ciphertext.
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'CSRF error']);
        exit;
    }

    if (empty($_POST['id'])) {
        checkRateLimit();
    }

    $content = $_POST['content'] ?? '';
   
    if (strlen($content) > 500000) {
        http_response_code(413);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Note too large']);
        exit;
    }

    $id = $_POST['id'] ?? bin2hex(random_bytes(16));

    if (!preg_match('/^[a-f0-9]{32}$/', $id)) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Invalid note ID']);
        exit;
    }

    $file = $notesDir . '/' . $id . '.txt';
   
    if (file_put_contents($file, $content, LOCK_EX) === false) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Failed to save note']);
        exit;
    }

    @chmod($file, 0600);

    if (($_POST['ajax'] ?? '') === '1') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'id' => $id]);
        exit;
    }

    header('Location: ?id=' . urlencode($id));
    exit;
}

/*
|--------------------------------------------------------------------------
| Load Existing Note
|--------------------------------------------------------------------------
*/
$id = $_GET['id'] ?? null;
$noteContent = '';

if ($id && preg_match('/^[a-f0-9]{32}$/', $id)) {
    $file = $notesDir . '/' . $id . '.txt';
    if (is_file($file) && is_readable($file)) {
        $noteContent = file_get_contents($file) ?: '';
    }
}

$shareUrlBase = $id
    ? rtrim($baseUrl, '/') . '/?id=' . urlencode($id)
    : null;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Anonymous Cloud Notes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<?php if (!$id): ?>
    <!-- CREATE MODE -->
    <h1>Anonymous Cloud Notes</h1>
   
    <p class="tagline">
        ✓ Anonymous ✓ End-to-end encrypted ✓ Privacy-first ✓ Instant sharing ✓ Collaborative editing ✓ Free &
        <a href="https://github.com/chrisjonesonline/notes" target="_blank" rel="noopener noreferrer nofollow">Open Source</a>
    </p>

    <p class="description">
        Create a note and receive a secret editable link. Anyone with the link can view and edit the note.
    </p>

    <form method="post" id="createForm">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8') ?>">
        <textarea 
            id="content"
            name="content"
            placeholder="Write your note here..."
            autocomplete="off"
            autocapitalize="off"
            spellcheck="false"
            aria-label="Note content"></textarea>
        <div class="counter" id="counter">0 / 100000 characters</div>
       
        <div class="actions">
            <div class="button-row">
                <button type="submit" name="new_note">Create Encrypted Note</button>
            </div>
        </div>
    </form>

<?php else: ?>
    <!-- EDIT / VIEW MODE -->
    <h1>Anonymous Cloud Notes</h1>
   
    <div class="share-box">
        <p class="share-title">Your private link:</p>
        <a href="#" id="shareLink">
            <?= htmlspecialchars($shareUrlBase, ENT_QUOTES, 'UTF-8') ?>#<span id="keyDisplay">[key]</span>
        </a>
        <p class="share-warning">
            Keep this link safe — it is required to access this note.
            If lost, the note cannot be recovered.
        </p>
    </div>

    <form method="post" id="editForm">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="id" value="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>">
        <textarea 
            id="content"
            name="content"
            autocomplete="off"
            autocapitalize="off"
            spellcheck="false"
            aria-label="Note content"></textarea>
        <input type="hidden" id="encryptedData" value="<?= htmlspecialchars($noteContent, ENT_QUOTES, 'UTF-8') ?>">
       
        <div class="counter" id="counter">0 / 100000 characters</div>
       
        <div class="actions">
            <div class="button-row">
                <button type="submit" name="save_note">Save</button>
                <button type="button" id="copyLink">Copy Link</button>
                <button type="button" id="newNote">New Note</button>
            </div>
        </div>
    </form>
<?php endif; ?>

<script src="assets/js/script.js"></script>

</body>
</html>