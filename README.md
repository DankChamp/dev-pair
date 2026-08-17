# DevPair

<div align="center">

[![CI](https://github.com/DankChamp/dev-pair/actions/workflows/ci.yml/badge.svg)](https://github.com/DankChamp/dev-pair/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-00FF9C.svg)](https://opensource.org/licenses/MIT)
[![HTML5](https://img.shields.io/badge/HTML5-E34F26?logo=html5&logoColor=white)](https://developer.mozilla.org/en-US/docs/Web/HTML)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php&logoColor=white)](https://php.net)
[![JavaScript](https://img.shields.io/badge/Vanilla_JS-ES6%2B-F7DF1E?logo=javascript&logoColor=black)](https://developer.mozilla.org/en-US/docs/Web/JavaScript)

</div>

**Real-time collaborative task board + AI pair programming for two developers. Shared Groq key, multiple projects, server-side sync.**

---

## Features

- **Real-time sync** — 5-second polling, ~1s auto-save
- **AI pair programming** — Shared Groq API key, context-aware assistance
- **Multi-project** — Create/switch/delete projects per session
- **Online presence** — See when your partner is active
- **Zero-config deploy** — Upload 2 files to any PHP hosting

---

## Quick Start

Real-time task board + AI manager for **pash** and **void**. One shared Groq key, multiple projects, everything saved on your MilesWeb server.

## Files

| File | Purpose |
|------|---------|
| `index.html` | Main app |
| `sync.php` | Server sync backend (PHP) |

Data is stored automatically in `devpair_data/` on your server (created on first use).

---

## Upload to MilesWeb

1. Log into **MilesWeb cPanel** → File Manager
2. Open `public_html` (or a subfolder like `public_html/devpair/`)
3. Upload **`index.html`** and **`sync.php`**
4. Make sure the folder is writable so PHP can create `devpair_data/`

Share the same URL with both teammates.

---

## First-time setup

### pash (Developer 1)
1. Open your hosted URL
2. Select **pash**
3. Enter your first project name
4. Paste your **shared Groq API key** (one key for both of you)
5. Click **Enter DevPair**

### void (Developer 2)
1. Open the **same URL**
2. Select **void**
3. Enter the Groq key if not already saved on the server (or leave blank — it loads from the server)
4. Click **Enter DevPair**

Both of you now share the same board, chat history, and projects in real time.

---

## Get a free Groq API key

1. Go to [console.groq.com](https://console.groq.com)
2. Sign up (free tier available)
3. API Keys → Create key
4. Paste once in setup or Settings — saved to `devpair_data/team.json` on your server

---

## How sync works

| Feature | Behavior |
|---------|----------|
| **Realtime** | Pulls updates every **5 seconds** |
| **Auto-save** | Saves to server **~1 second** after any change |
| **Shared state** | Same project = same tasks, chat, activity for both devs |
| **Online status** | Green dot when partner was active in the last 2 minutes |
| **Projects** | Create/switch/delete from top bar or Settings |

---

## Multiple projects

- Use the **project dropdown** in the top bar to switch
- Create new projects in **Settings → Projects**
- Each project has its own tasks, AI chat, and activity log
- All stored server-side in `devpair_data/projects/`

---

## Security notes

- The Groq key lives on your server in `devpair_data/team.json` — restrict folder access if possible
- AI calls go from the browser directly to Groq using that shared key
- Only upload this to a URL you trust (you + your partner)

---

## Troubleshooting

| Problem | Fix |
|---------|-----|
| Sync offline / save fails | Confirm `sync.php` is uploaded and `devpair_data/` is writable |
| AI not working | Add the Groq key in Settings → Save to server |
| Partner shows offline | They need the app open (syncs every 5s) |
| Old data after switch | Pick the correct project from the dropdown |

---

## License

MIT License — see [LICENSE](LICENSE) for details.
