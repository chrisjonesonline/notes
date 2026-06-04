/*
|--------------------------------------------------------------------------
| Anonymous Cloud Notes - Client-Side Encryption
|--------------------------------------------------------------------------
| This file handles end-to-end encryption using the Web Crypto API.
| Notes are encrypted in the browser before being sent to the server.
|--------------------------------------------------------------------------
*/

const textarea = document.getElementById('content');
const encryptedData = document.getElementById('encryptedData');
const counter = document.getElementById('counter');
const copyButton = document.getElementById('copyLink');
const newNoteBtn = document.getElementById('newNote');

/* ===================== STATE ===================== */
const hasId = new URLSearchParams(location.search).has('id');
const key = location.hash.slice(1); // Encryption key from URL #hash
const canEdit = hasId && !!key;
const MAX_NOTE_LENGTH = 100000;

/* ===================== HELPERS ===================== */
function bytesToBase64(bytes) {
    let binary = '';
    for (let i = 0; i < bytes.length; i++) {
        binary += String.fromCharCode(bytes[i]);
    }
    return btoa(binary);
}

function updateCounter() {
    counter.textContent = `${textarea.value.length} / ${MAX_NOTE_LENGTH} characters`;
}

function showError(message) {
    alert(message);
}

/* ===================== ENCRYPTION ===================== */
async function generateKey() {
    const rawKey = crypto.getRandomValues(new Uint8Array(32));
    let base64 = btoa(String.fromCharCode(...rawKey));
    return base64
        .replace(/\+/g, '-')
        .replace(/\//g, '_')
        .replace(/=/g, '');
}

async function importKey(keyBase64) {
    const base64 = keyBase64.replace(/-/g, '+').replace(/_/g, '/');
    const binary = atob(base64);
    if (binary.length !== 32) throw new Error(`Invalid key length: ${binary.length}`);
    
    const rawKey = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) rawKey[i] = binary.charCodeAt(i);
    
    return await crypto.subtle.importKey("raw", rawKey, { name: "AES-GCM" }, false, ["encrypt", "decrypt"]);
}

async function encrypt(text, key) {
    const cryptoKey = await importKey(key);
    const encoder = new TextEncoder();
    const iv = crypto.getRandomValues(new Uint8Array(12));
    
    const ct = await crypto.subtle.encrypt({ name: "AES-GCM", iv }, cryptoKey, encoder.encode(text));
    
    const combined = new Uint8Array(12 + ct.byteLength);
    combined.set(iv, 0);
    combined.set(new Uint8Array(ct), 12);
    return bytesToBase64(combined);
}

async function decrypt(b64, key) {
    try {
        const cryptoKey = await importKey(key);
        const combined = Uint8Array.from(atob(b64), c => c.charCodeAt(0));
        const iv = combined.slice(0, 12);
        const ct = combined.slice(12);
        
        const dec = await crypto.subtle.decrypt({ name: "AES-GCM", iv }, cryptoKey, ct);
        return new TextDecoder().decode(dec);
    } catch (e) {
        console.error('Decryption failed:', e);
        return null;
    }
}

/* ===================== MAIN LOGIC ===================== */
textarea.addEventListener('input', updateCounter);

document.addEventListener('DOMContentLoaded', async () => {
    const form = document.querySelector('form');

    /* Form Submission Handler */
    form.addEventListener('submit', async (e) => {
        if (form.dataset.submitting === 'true') return;
        e.preventDefault();

        const text = textarea.value.trim();
        if (!text && !hasId) return;

        if (text.length > MAX_NOTE_LENGTH) {
            showError(`Note cannot exceed ${MAX_NOTE_LENGTH} characters.`);
            return;
        }

        form.dataset.submitting = 'true';

        try {
            if (!hasId) {
                // === CREATE NEW NOTE ===
                const newKey = await generateKey();
                const encrypted = await encrypt(text, newKey);

                const fd = new FormData(form);
                fd.set('content', encrypted);
                fd.append('ajax', '1');

                const res = await fetch('', { 
                    method: 'POST', 
                    body: fd 
                });

                let data;
                try {
                    data = await res.json();
                } catch (e) {
                    throw new Error('Invalid response from server');
                }

                if (!res.ok || !data.success) {
                    throw new Error(data.error || 'Failed to create note');
                }

                location.replace(`?id=${data.id}#${newKey}`);

            } else if (key) {
                // === SAVE EXISTING NOTE ===
                const encrypted = await encrypt(text, key);
                
                form.querySelectorAll('input[name="content"]').forEach(el => el.remove());
                
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'content';
                hidden.value = encrypted;
                form.appendChild(hidden);
                
                form.submit();
            }
        } catch (err) {
            console.error(err);
            showError(err.message || "Request failed. Please try again.");
        } finally {
            form.dataset.submitting = 'false';
        }
    });

    /* ===================== EDIT / VIEW MODE ===================== */
    if (hasId) {
        if (!canEdit) {
            lockUI("⚠️ Missing decryption key in URL.\nUse the full link with the #key part.");
            return;
        }

        document.getElementById('keyDisplay').textContent = key;

        if (encryptedData && encryptedData.value) {
            const decrypted = await decrypt(encryptedData.value, key);
            if (decrypted !== null) {
                textarea.value = decrypted;
            } else {
                lockUI("❌ Failed to decrypt note. Wrong key or corrupted data?");
                return;
            }
        }
        updateCounter();
        textarea.focus();
    } else {
        updateCounter();
    }
});

/* ===================== BUTTONS ===================== */
if (copyButton) {
    copyButton.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(location.href);
            const original = copyButton.textContent;
            copyButton.textContent = 'Copied!';
            setTimeout(() => copyButton.textContent = original, 1500);
        } catch (e) {
            alert('Failed to copy link');
        }
    });
}

if (newNoteBtn) {
    newNoteBtn.addEventListener('click', () => {
        if (confirm('Create a new note? Any unsaved changes will be lost.')) {
            location.href = location.pathname;
        }
    });
}

function lockUI(message = '') {
    textarea.disabled = true;
    if (message) textarea.value = message;
   
    const saveBtn = document.querySelector('button[name="save_note"]');
    if (saveBtn) saveBtn.disabled = true;
    if (copyButton) copyButton.disabled = true;
}