# Anonymous Cloud Notes

**A minimalist, end-to-end encrypted collaborative notepad.**  
No accounts. Privacy-first. No bullshit.

Create a note → get a secret link. Anyone with the link can view and edit it. Notes are encrypted in your browser using **AES-256-GCM** before reaching the server.

### Features
- True end-to-end encryption (server never sees plaintext or key)
- Fully anonymous — no signup or email required
- Cryptographically secure random note IDs and encryption keys
- Collaborative editing
- Hardened rate limiting (1 note per hour per IP)
- Automatic cleanup deletes notes not modified for 30+ days (Deterministically)
- Strict security headers and secure session handling
- Secure file storage with restricted permissions
- Self-hosted and open source
- Clean, mobile-friendly interface

### Security
Notes are encrypted client-side with AES-256-GCM. The encryption key never leaves your browser — it lives only in the URL fragment (`#key`).

Even if the server is compromised or subpoenaed, it only contains encrypted data that is computationally infeasible to decrypt without the key.

#### Important Notes
- The full link is both the access token **and** decryption key
- Losing the link makes the note **permanently unrecoverable**
- Collaboration is trust-based; security is trustless — anyone with the link can view/edit, and the last save wins (no version history or conflict resolution)
- The link should only be shared through secure channels (e.g. Signal, encrypted email, or in-person)
- Encryption protects note content, **not** metadata. The server can still see note IDs, access times, and IP addresses. Use a VPN or Tor for better privacy
- Ephemeral rate-limit metadata — Hashed IP-based rate-limiting data is used for abuse prevention and is deleted automatically after 1 hour (Deterministically)
- Browser security matters. Malicious **extensions** or **compromised devices** can access plaintext before encryption or after decryption. Don't use pwned devices or untrusted browser extensions

### Additional Privacy & Security Protections
The application includes standard security hardening against common web attacks:
- **Strict CSP** — Protection against XSS attacks
- **CSRF Protection** — Prevents cross-site request forgery
- **Clickjacking Protection** — Via X-Frame-Options and CSP frame-ancestors
- **Strong HTTP Headers** — HSTS, X-Content-Type-Options, Referrer-Policy, Permissions-Policy
- **No browser permissions** — No access to camera, mic, or location
- **No database** — Eliminates SQL injection risks
- **No caching** of notes by browser or proxies
- **robots.txt + meta tags** — Prevents search engine indexing
- **Input validation & output encoding** — Prevents malformed input and reduces injection and abuse risks
- **Secure PHP config** — Disables error reporting in production; serves all assets locally

### Self-Hosting (Easy)
1. Put the files in your website directory
2. Create a `storage/notes/` folder outside your website directory with read and write permissions
3. (Optional but recommended) Create a `storage/rate_limits/` folder outside your website directory with read and write permissions
4. Change `$baseUrl` to your domain name in `index.php`
5. Turn on HTTPS (so your site is secure)
6. Done

**Try it:** [https://notes.chrisjones.online](https://notes.chrisjones.online)

Made with ❤️ for privacy.
