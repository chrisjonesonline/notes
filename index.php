<?php

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

header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self';");

/*
|--------------------------------------------------------------------------
| Configuration
| ←←←←←←←←←←←←←←←←←←←←←←←←←←←←←←←←←←←←←←←←←←
| IMPORTANT: Customize these values for your own deployment!
|--------------------------------------------------------------------------
*/

// REQUIRED: Change to your own domain
$baseUrl = 'https://notes.chrisjones.online';

// Storage directory for encrypted notes
$notesDir = dirname(__DIR__) . '/storage/notes';

if (!is_dir($notesDir)) {
    mkdir($notesDir, 0700, true);
}

if (!isset($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

/*
|--------------------------------------------------------------------------
| Handle POST Requests (Create or Save)
|--------------------------------------------------------------------------
| Note: Actual encryption happens in the browser (script.js)
| Server only stores ciphertext.
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        http_response_code(403);
        exit('CSRF error');
    }

    $content = $_POST['content'] ?? '';
    
    // Basic server-side size limit (ciphertext)
    if (strlen($content) > 500000) {
        http_response_code(413);
        exit('Note too large');
    }

    $id = $_POST['id'] ?? bin2hex(random_bytes(16));
    $file = $notesDir . '/' . $id . '.txt';
    
    file_put_contents($file, $content, LOCK_EX);
    chmod($file, 0600);

    // AJAX response for new notes (used by JS to append #key)
    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
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
    if (file_exists($file)) {
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
    <h4>
    ✓ Anonymous ✓ End-to-end encrypted ✓ Instant sharing ✓ Collaborative editing ✓ Free & 
    <a href="https://github.com/chrisjonesonline/notes" 
        target="_blank" 
        rel="noopener noreferrer nofollow">Open Source</a>
    </h4>
	<p>Create a note and receive a secret editable link. Anyone with the link can view and edit the note.</p>

    <form method="post" id="createForm">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
        <textarea id="content" placeholder="Write your note here..."></textarea>
        <div class="counter" id="counter">0 / 100000 characters</div>
        
        <!-- Button container for consistent mobile styling -->
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
        <strong>Your private link:</strong><br><br>
        <a href="<?= htmlspecialchars($shareUrlBase) ?>" id="shareLink">
            <?= htmlspecialchars($shareUrlBase) ?>#<span id="keyDisplay">[key]</span>
        </a><br><br>
       <strong>Keep this link safe — it is required to access this note. If lost, the note cannot be recovered.</strong><br>
    </div>

    <form method="post" id="editForm">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
        <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>">

        <textarea id="content"></textarea>
        
        <!-- Hidden field containing encrypted data -->
        <input type="hidden" id="encryptedData" value="<?= htmlspecialchars($noteContent) ?>">
        
        <div class="counter" id="counter">0 / 100000 characters</div>
        
        <!-- Button container for proper styling -->
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