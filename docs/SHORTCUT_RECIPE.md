# Butler iPhone Shortcut — Build Recipe

Build this once in Shortcuts.app, share it as an iCloud link,
then set `SHORTCUT_INSTALL_URL` in `.env` to that link.

---

## What the Shortcut does

```
First run  →  prompt for 6-char code  →  claim token  →  store it
Every run  →  ask "what to log?"      →  send to Butler →  show reply
```

The user never sees a token, URL, or header.

---

## Build steps (Shortcuts.app)

**Name the shortcut:** `Butler`

### Block 1 — Read stored token

| # | Action | Settings |
|---|--------|----------|
| 1 | **Get File** | iCloud Drive · `/Shortcuts/butler_token.txt` · ✅ Allow Error |
| 2 | **Set Variable** | Variable: `token` |

### Block 2 — First-run pairing (if no token)

| # | Action | Settings |
|---|--------|----------|
| 3 | **If** | `token` · is · empty |
| 4 | **Ask for Input** | "Masukkan kode pairing dari Telegram (6 huruf):" · Numeric: No |
| 5 | **Set Variable** | Variable: `pairingCode` |
| 6 | **Get Contents of URL** | (see payload below) |
| 7 | **Get Dictionary Value** | Key: `success` · from Step 6 |
| 8 | **If** | Step 7 · is not · `1` |
| 9 | **Show Alert** | "Kode tidak valid atau sudah kadaluarsa. Ketik /pair\_iphone di Telegram untuk kode baru." |
| 10 | **Stop Shortcut** | |
| 11 | **End If** | |
| 12 | **Get Dictionary Value** | Key: `token` · from Step 6 · Set Variable: `token` |
| 13 | **Save File** | `token` · to iCloud Drive `/Shortcuts/butler_token.txt` · ✅ Overwrite |
| 14 | **Show Notification** | "Butler terpasang! Coba ketik sesuatu." |
| 15 | **End If** | |

**Step 6 — Claim URL payload:**
```
URL:     https://YOUR_APP_URL/api/pair/claim
Method:  POST
Headers:
  Content-Type: application/json
  Accept:       application/json
Body (JSON):
  {
    "code":        pairingCode (variable),
    "device_name": "iPhone"
  }
```

### Block 3 — Send message

| # | Action | Settings |
|---|--------|----------|
| 16 | **Ask for Input** | "Butler, apa yang ingin kamu catat?" |
| 17 | **Set Variable** | Variable: `userMessage` |
| 18 | **If** | `userMessage` · is · empty |
| 19 | **Stop Shortcut** | |
| 20 | **End If** | |
| 21 | **Get Contents of URL** | (see payload below) |
| 22 | **Get Dictionary Value** | Key: `data` · from Step 21 · Set Variable: `responseData` |
| 23 | **Get Dictionary Value** | Key: `response` · from `responseData` · Set Variable: `reply` |
| 24 | **If** | `reply` · is not · empty |
| 25 | **Show Result** | `reply` |
| 26 | **Otherwise** | |
| 27 | **Show Result** | "Dicatat!" |
| 28 | **End If** | |

**Step 21 — Message URL payload:**
```
URL:     https://YOUR_APP_URL/api/shortcut/message
Method:  POST
Headers:
  Authorization: Bearer [token variable]
  Content-Type:  application/json
  Accept:        application/json
Body (JSON):
  {
    "message": userMessage (variable),
    "source":  "iphone_shortcut"
  }
```

---

## Replace `YOUR_APP_URL`

Set this to your production domain (e.g. `https://butler.yourdomain.com`).
For local dev with ngrok, use the current `APP_URL` from `.env`.

---

## Distribute

1. Build the Shortcut in Shortcuts.app on your iPhone.
2. Tap the Shortcut name → **Share** → **Copy iCloud Link**.
3. Paste the link into `.env`:
   ```
   SHORTCUT_INSTALL_URL=https://www.icloud.com/shortcuts/XXXXXXXXXXXXXXXXXXXXXXXX
   ```
4. Run `php artisan config:cache` (or `docker exec laravel_app php artisan config:cache`).
5. The "📲 Instal Shortcut" button in `/pair_iphone` replies will now link directly to it.

---

## Token rotation (lost phone / revoked device)

1. User sends `/my_devices` in Telegram.
2. User taps **Hapus: iPhone** in the inline keyboard.
3. Butler revokes the device and its Sanctum token.
4. On the next Shortcut run, the stored token returns 401.
5. User deletes `butler_token.txt` from iCloud Drive (or taps the "Re-pair" action).
6. User sends `/pair_iphone` in Telegram to get a new code.
7. Shortcut detects empty token on next run → prompts for new code → re-pairs.

**Optional — add a "Reset" Shortcut action:**
Add a second Shortcut named "Butler Reset" that contains only:
- Delete File: iCloud Drive `/Shortcuts/butler_token.txt`
- Show Alert: "Token dihapus. Buka Butler dan masukkan kode baru dari Telegram."

---

## Siri integration

After install, say: **"Hey Siri, run Butler"**
Or rename the Shortcut to a phrase: **"Hey Siri, catat pengeluaran"**

---

## Token storage notes

- `butler_token.txt` is stored in the user's **personal** iCloud Drive.
- It is not shared between devices unless both devices use the same iCloud account.
- Each physical device should claim its own pairing code and store its own token.
- To pair a second iPhone: send `/pair_iphone` again → new code → new device record.
