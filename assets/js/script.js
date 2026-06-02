const textarea = document.getElementById('content');
const counter = document.getElementById('counter');
const copyButton = document.getElementById('copyLink');
const newNoteBtn = document.getElementById('newNote');

/*
|--------------------------------------------------------------------------
| Character Counter
|--------------------------------------------------------------------------
*/
if (textarea && counter) {
    const update = () => {
        counter.textContent = `${textarea.value.length} / 100000 characters`;
    };
    textarea.addEventListener('input', update);
    update();
}

/*
|--------------------------------------------------------------------------
| Copy Share Link
|--------------------------------------------------------------------------
*/
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

/*
|--------------------------------------------------------------------------
| New Note Button (replaces inline onclick)
|--------------------------------------------------------------------------
*/
if (newNoteBtn) {
    newNoteBtn.addEventListener('click', () => {
        window.location.href = window.location.pathname;
    });
}