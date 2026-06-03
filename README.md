# Anonymous Cloud Notes

**A minimalist, end-to-end encrypted collaborative notepad.**  
No accounts. No tracking. No bullshit.

Create a note → get a secret link. Anyone with the link can view and edit it in real-time. Notes are encrypted in your browser using **AES-256-GCM** before reaching the server.

### Features
- True end-to-end encryption (server never sees plaintext or key)
- Fully anonymous — no signup or email required
- Collaborative editing
- Self-hosted and open source
- Clean, mobile-friendly interface

### Security
Notes are encrypted client-side with AES-256-GCM. The encryption key never leaves your browser — it lives only in the URL fragment (`#key`).

Even if the server is compromised or subpoenaed, it only contains encrypted data that is computationally infeasible to decrypt without the key.

#### Important Notes
- The full link is both the access token **and** decryption key
- Losing the link makes the note **permanently unrecoverable**
- **Overwrites are possible** — if multiple people edit at the same time, the last save wins (no version history or conflict resolution)
- The link should only be shared through secure channels (e.g. Signal, encrypted email, or in-person)
- Collaboration is trust-based — anyone with the link can edit
- Encryption protects content, **not** metadata. The server can still see note IDs, access times, and IP addresses. Use a VPN or Tor for better privacy.
- **Browser security matters.** Malicious extensions or compromised devices can access plaintext before encryption or after decryption.

### Additional Privacy & Security Protections
The app was heavily hardened against common attacks:

- **Strict CSP** — Strong protection against XSS attacks
- **CSRF Protection** — Prevents cross-site request forgery
- **Clickjacking Protection** — Via X-Frame-Options and CSP frame-ancestors
- **Strong HTTP Headers** — HSTS, X-Content-Type-Options, Referrer-Policy, Permissions-Policy
- **No browser permissions** — No access to camera, mic, or location
- **No database** — Eliminates SQL injection risks
- **No caching** of notes by browser or proxies
- **robots.txt + meta tags** — Prevents search engine indexing
- **Input sanitization & output encoding** — Reduces injection and abuse risks
- **Secure PHP config** — No error reporting in production, all assets served locally

**Try it:** [https://notes.chrisjones.online](https://notes.chrisjones.online)

Made with ❤️ for privacy.
