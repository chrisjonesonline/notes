<?php

session_start();

/*
|--------------------------------------------------------------------------
| Security Headers
|--------------------------------------------------------------------------
*/

header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

/*
|--------------------------------------------------------------------------
| Configuration
|--------------------------------------------------------------------------
*/

$notesDir = dirname(__DIR__) . '/storage/notes';
$maxNoteSize = 100000; // 100 KB

if (!is_dir($notesDir)) {
    mkdir($notesDir, 0700, true);
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

    if (strlen($content) > $maxNoteSize) {
        exit('Note exceeds maximum size.');
    }

    $id = bin2hex(random_bytes(16));
    $file = $notesDir . '/' . $id . '.txt';

    file_put_contents($file, $content, LOCK_EX);
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

    if (strlen($content) > $maxNoteSize) {
        exit('Note exceeds maximum size.');
    }

    $file = $notesDir . '/' . $id . '.txt';

    if (!file_exists($file)) {
        http_response_code(404);
        exit('Note not found');
    }

    file_put_contents($file, $content, LOCK_EX);

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

    $noteContent = file_get_contents($file);
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    ? 'https'
    : 'http';

$shareUrl = $id
    ? $scheme . '://' . $_SERVER['HTTP_HOST'] .
      strtok($_SERVER['REQUEST_URI'], '?') .
      '?id=' . urlencode($id)
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
	<h4>✓ Anonymous ✓ No account required ✓ Instant sharing ✓ Collaborative editing ✓ Free & <a href="https://github.com/chrisjonesonline/notes" target="_blank" rel="noopener noreferrer nofollow">Open Source</a></h4>

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

        <div class="counter" id="counter">0 / 100000</div>

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
    <h1>Shared Note</h1>

    <div class="share-box">
        <strong>Share this link:</strong><br>
        <a href="<?= htmlspecialchars($shareUrl, ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars($shareUrl, ENT_QUOTES, 'UTF-8') ?>
        </a>
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
            <?= strlen($noteContent) ?> / 100000
        </div>

        <div class="actions">
            <div class="button-row">

                <button type="submit" name="save_note">
                    Save Changes
                </button>

                <button type="button" id="copyLink">
                    Copy Link
                </button>

                <button type="button"
                        onclick="location.href='<?= strtok($_SERVER['REQUEST_URI'], '?') ?>'">
                    New Note
                </button>

            </div>
        </div>

    </form>

<?php endif; ?>

<script>
const textarea = document.getElementById('content');
const counter = document.getElementById('counter');

if (textarea && counter) {
    const update = () => {
        counter.textContent = `${textarea.value.length} / 100000`;
    };

    textarea.addEventListener('input', update);
    update();
}

const copyButton = document.getElementById('copyLink');

if (copyButton) {
    copyButton.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(window.location.href);

            const old = copyButton.textContent;
            copyButton.textContent = 'Copied!';

            setTimeout(() => {
                copyButton.textContent = old;
            }, 1200);

        } catch (e) {
            alert('Unable to copy link');
        }
    });
}
</script>

</body>
</html>