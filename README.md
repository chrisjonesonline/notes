# Anonymous Cloud Notes

**A minimalist, end-to-end encrypted collaborative notepad.**  
No accounts. No tracking. No bullshit.

Create a note → get a secret link. Anyone with the link can view and edit it in real-time. Notes are encrypted in your browser using **AES-256-GCM** before reaching the server.

### Features
- True end-to-end encryption (server never sees plaintext or key)
- Fully anonymous — no signup or email required
- Real-time collaborative editing
- Self-hosted and open source
- Clean, mobile-friendly interface

### Security
Notes are encrypted client-side with AES-256-GCM. The encryption key never leaves your browser — it is stored only in the URL fragment (`#key`).

The server stores only encrypted data. Even if the server is compromised or subpoenaed, without the key, the ciphertext is computationally infeasible to decrypt.

#### Important Notes
- The full link is both the access token **and** decryption key
- Losing the link makes the note permanently unrecoverable
- Encryption protects content, **not** metadata (use VPN/Tor for full privacy)
- Collaboration is trust-based — anyone with the link can edit

### Additional Privacy & Security Protections
- **Strict CSP** — Blocks XSS attacks
- **CSRF Protection** — Prevents cross-site request forgery
- **Clickjacking Protection** — Via X-Frame-Options and CSP frame-ancestors
- **Strong HTTP Headers** — HSTS, X-Content-Type-Options, Referrer-Policy, Permissions-Policy
- **No database** — Eliminates SQL injection risks
- **No browser permissions** — No access to camera, mic, or location
- **No caching** of notes by browser or proxies
- **robots.txt + meta tags** — Prevents search engine indexing
- **Input sanitization & rate limiting** — Reduces injection and abuse risks
- **Secure PHP config** — No error reporting in production, all assets served locally

### Project Structure
```text
root/
├── yourdomain/
│   ├── index.php
│   └── assets/
│       ├── css/
│       │   └── style.css
│       └── js/
│           └── script.js
│
├── storage/
│   └── notes/
```

### Self-Hosting
1. Upload the project files to a PHP 8+ web server.
2. Ensure the web server can write to the `storage/notes` directory.
3. Point your domain to the `yourdomain` directory.
4. Enable HTTPS.

### Roadmap
* [ ] Markdown support
* [ ] Live collaboration presence indicators
* [ ] Note expiration / self-destruct options
* [ ] Optional read-only sharing links
* [ ] PWA / offline support

**Try it:** https://notes.chrisjones.online

Made with ❤️ for privacy.
