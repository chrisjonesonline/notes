<?php

/*
|--------------------------------------------------------------------------
| Security Note
|--------------------------------------------------------------------------
| These defaults are intentionally strict.
| Do not loosen CSP, cookie flags, or file permissions unless you
| fully understand the security implications.
|--------------------------------------------------------------------------
*

/*
|--------------------------------------------------------------------------
| Secure Session Cookies
|--------------------------------------------------------------------------
*/

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => (
        !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
    ),
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
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
}

header(
    "Content-Security-Policy: "
    . "default-src 'self'; "
    . "script-src 'self'; "
    . "style-src 'self'; "
    . "img-src 'self' data:; "
    . "object-src 'none'; "
    . "base-uri 'none'; "
    . "frame-ancestors 'none'; "
    . "form-action 'self'; "
    . "upgrade-insecure-requests;"   // Enforces HTTPS
);

/*
|--------------------------------------------------------------------------
| Configuration
| ←←←←←←←←←←←←←←←←←←←←←←←←←←←←←←←←←←←←←←←←←←
| IMPORTANT: Customize these values for your own deployment!
|--------------------------------------------------------------------------
*/

// REQUIRED: Change to your own domain (used for share links)
$baseUrl = 'https://notes.chrisjones.online';

// Path to store notes
$notesDir = dirname(__DIR__) . '/storage/notes';

// Note size limits
$maxNoteSizeChars = 100000; // UI character limit (displayed to user)
$maxNoteSizeBytes = 500000; // Hard byte limit (prevents storage abuse)

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
| Create New Note
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_note'])) {

    if (
        !isset($_POST['csrf']) ||
        !hash_equals($_SESSION['csrf'], $_POST['csrf'])
    ) {
        http_response_code(403);
        exit('Invalid CSRF token');
    }

    $content = $_POST['content'] ?? '';

    if (mb_strlen($content, 'UTF-8') > $maxNoteSizeChars) {
        exit('Note exceeds maximum character limit.');
    }
    if (strlen($content) > $maxNoteSizeBytes) {
        exit('Note exceeds maximum storage size.');
    }

    $id = bin2hex(random_bytes(16));
    $file = $notesDir . '/' . $id . '.txt';

    if (file_put_contents($file, $content, LOCK_EX) === false) {
        http_response_code(500);
        exit('Failed to save note. Please try again.');
    }
    chmod($file, 0600);

    header('Location: ?id=' . urlencode($id));
    exit;
}

/*
|--------------------------------------------------------------------------
| Save Existing Note
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_note'])) {

    if (
        !isset($_POST['csrf']) ||
        !hash_equals($_SESSION['csrf'], $_POST['csrf'])
    ) {
        http_response_code(403);
        exit('Invalid CSRF token');
    }

    $id = $_POST['id'] ?? '';

    if (!preg_match('/^[a-f0-9]{32}$/', $id)) {
        http_response_code(400);
        exit('Invalid note ID');
    }

    $content = $_POST['content'] ?? '';

    if (mb_strlen($content, 'UTF-8') > $maxNoteSizeChars) {
        exit('Note exceeds maximum character limit.');
    }
    if (strlen($content) > $maxNoteSizeBytes) {
        exit('Note exceeds maximum storage size.');
    }

    $file = $notesDir . '/' . $id . '.txt';

    if (!file_exists($file)) {
        http_response_code(404);
        exit('Note not found');
    }

    if (file_put_contents($file, $content, LOCK_EX) === false) {
        http_response_code(500);
        exit('Failed to save note. Please try again.');
    }

    header('Location: ?id=' . urlencode($id));
    exit;
}

/*
|--------------------------------------------------------------------------
| Load Note
|--------------------------------------------------------------------------
*/

$id = $_GET['id'] ?? null;
$noteContent = '';

if ($id !== null) {

    if (!preg_match('/^[a-f0-9]{32}$/', $id)) {
        http_response_code(400);
        exit('Invalid note ID');
    }

    $file = $notesDir . '/' . $id . '.txt';

    if (!file_exists($file)) {
        http_response_code(404);
        exit('Note not found');
    }

    $content = file_get_contents($file);

    if ($content === false) {
        http_response_code(500);
        exit('Failed to read note');
    }

    $noteContent = $content;
}

$shareUrl = $id
    ? rtrim($baseUrl, '/') . '/?id=' . urlencode($id)
    : null;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Anonymous Cloud Notes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>

<?php if (!$id): ?>

    <!-- CREATE MODE -->
    <h1>Anonymous Cloud Notes</h1>

    <h4>
        ✓ Anonymous ✓ No account required ✓ Instant sharing ✓ Collaborative editing ✓ Free &
        <a href="https://github.com/chrisjonesonline/notes"
           target="_blank"
           rel="noopener noreferrer nofollow">
            Open Source
        </a>
    </h4>

    <p class="small">
        Create a note and receive a secret editable link.
        Anyone with the link can view and edit the note.
    </p>

    <form method="post">

        <input type="hidden" name="csrf"
               value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8') ?>">

        <textarea
            name="content"
            id="content"
            placeholder="Write your note here..."
        ></textarea>

        <div class="counter" id="counter">0 / 100000 characters</div>

        <div class="actions">
            <div class="button-row">
                <button type="submit" name="new_note">
                    Create Note
                </button>
            </div>
        </div>

    </form>

<?php else: ?>

    <!-- EDIT MODE -->
    <h1>Anonymous Cloud Notes</h1>

    <div class="share-box">
        <strong>Your private link:</strong><br><br>
        <a href="<?= htmlspecialchars($shareUrl, ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars($shareUrl, ENT_QUOTES, 'UTF-8') ?>
        </a><br><br>
        <strong>Keep this link safe — it is your only means of accessing this note.</strong><br>
    </div>

    <form method="post">

        <input type="hidden" name="csrf"
               value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8') ?>">

        <input type="hidden" name="id"
               value="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>">

        <textarea
            name="content"
            id="content"
        ><?= htmlspecialchars($noteContent, ENT_QUOTES, 'UTF-8') ?></textarea>

        <div class="counter" id="counter">
            <?= mb_strlen($noteContent, 'UTF-8') ?> / 100000 characters
        </div>

        <div class="actions">
            <div class="button-row">

                <button type="submit" name="save_note">
                    Save Changes
                </button>

                <button type="button" id="copyLink">
                    Copy Link
                </button>

                <button type="button" id="newNote">
                    New Note
                </button>

            </div>
        </div>

    </form>

<?php endif; ?>

<script src="assets/js/script.js"></script>

</body>
</html>