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
const key = location.hash.slice(1);           // Encryption key from URL #hash
const canEdit = hasId && !!key;
const MAX_NOTE_LENGTH = 100000;

/* ===================== HELPERS ===================== */

/**
 * Convert bytes to base64 (URL-safe)
 */
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

function lockUI(message = '') {
    textarea.disabled = true;
    if (message) textarea.value = message;
    
    const saveBtn = document.querySelector('button[name="save_note"]');
    if (saveBtn) saveBtn.disabled = true;
    if (copyButton) copyButton.disabled = true;
}

/* ===================== ENCRYPTION ===================== */

/**
 * Generate a random encryption key
 */
async function generateKey() {
    const rawKey = crypto.getRandomValues(new Uint8Array(32));
    let base64 = btoa(String.fromCharCode(...rawKey));
    return base64
        .replace(/\+/g, '-')
        .replace(/\//g, '_')
        .replace(/=/g, '');
}

/**
 * Import base64 key for Web Crypto API
 */
async function importKey(keyBase64) {
    const base64 = keyBase64.replace(/-/g, '+').replace(/_/g, '/');
    const binary = atob(base64);
    
    if (binary.length !== 32) {
        throw new Error(`Invalid key length: ${binary.length} bytes (expected 32)`);
    }

    const rawKey = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) {
        rawKey[i] = binary.charCodeAt(i);
    }

    return await crypto.subtle.importKey(
        "raw", 
        rawKey, 
        { name: "AES-GCM" }, 
        false, 
        ["encrypt", "decrypt"]
    );
}

/**
 * Encrypt text using AES-GCM
 */
async function encrypt(text, key) {
    const cryptoKey = await importKey(key);
    const encoder = new TextEncoder();
    const iv = crypto.getRandomValues(new Uint8Array(12));
    
    const ct = await crypto.subtle.encrypt(
        { name: "AES-GCM", iv }, 
        cryptoKey, 
        encoder.encode(text)
    );
    
    const combined = new Uint8Array(12 + ct.byteLength);
    combined.set(iv, 0);
    combined.set(new Uint8Array(ct), 12);
    return bytesToBase64(combined);
}

/**
 * Decrypt ciphertext using AES-GCM
 */
async function decrypt(b64, key) {
    try {
        const cryptoKey = await importKey(key);
        
        const combined = Uint8Array.from(atob(b64), c => c.charCodeAt(0));
        const iv = combined.slice(0, 12);
        const ct = combined.slice(12);

        const dec = await crypto.subtle.decrypt(
            { name: "AES-GCM", iv }, 
            cryptoKey, 
            ct
        );
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

    /* Early validation of decryption key */
    if (hasId && key) {
        try {
            let base64 = key.replace(/-/g, '+').replace(/_/g, '/');
            const padded = base64 + '='.repeat((4 - base64.length % 4) % 4);
            atob(padded);
        } catch (e) {
            lockUI("⚠️ Invalid decryption key in URL.");
            return;
        }
    }

    /* Fix share link to include full URL with #key */
    const shareLink = document.getElementById('shareLink');
    if (shareLink && key) {
        shareLink.href = location.href;
    }

    /* Form Submission Handler */
    form.addEventListener('submit', async (e) => {
        if (form.dataset.submitting === 'true') return;
        e.preventDefault();

        const text = textarea.value;

        if (!text.trim() && !hasId) return;

        if (text.length > MAX_NOTE_LENGTH) {
            alert(`Note cannot exceed ${MAX_NOTE_LENGTH} characters.`);
            return;
        }

        form.dataset.submitting = 'true';

        try {
            if (!hasId) {
                // === CREATE NEW NOTE ===
                const newKey = await generateKey();
                
                let encrypted;
                try {
                    encrypted = await encrypt(text, newKey);
                } catch (e) {
                    alert("Encryption failed. Please try again.");
                    return;
                }

                const fd = new FormData(form);
                fd.set('content', encrypted);
                fd.append('ajax', '1');

                const res = await fetch('', { method: 'POST', body: fd });
                const data = await res.json();

                if (data.success) {
                    location.replace(`?id=${data.id}#${newKey}`);
                } else {
                    alert('Failed to create note.');
                }
            } else if (key) {
                // === SAVE EXISTING NOTE ===
                let encrypted;
                try {
                    encrypted = await encrypt(text, key);
                } catch (e) {
                    alert("Encryption failed. Please try again.");
                    return;
                }
                
                // Replace content with encrypted version
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
            alert("Request failed. Please try again.");
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