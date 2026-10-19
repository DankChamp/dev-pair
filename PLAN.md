# DevPair — Multi-Dev Workspace Plan

## Goal (from `goal.md`)
1. Scrap the hardcoded 2-dev model → workspace for **multiple devs** on the same projects/codebase.
2. **Git-like version control**: always committing on a **single branch**, undo specific changes, **no branches or pull requests**.
3. **Profile section**: each dev writes what they can do; the integrated AI uses profiles to judge/split tasks and set deadlines.

## Current state
- `index.html` — single-file SPA: setup (hardcoded pash/dev1 vs void/dev2), kanban board, Groq AI chat, overview, settings; synced via `sync.php` + localStorage
- `sync.php` — flat-file JSON backend (`devpair_data/team.json`, `projects/*.json`); has a `version` counter but **no history/undo**
- Everything revolves around exactly 2 hardcoded devs (`DEV_NAMES = { dev1: 'pash', dev2: 'void' }`)

## Plan (4 phases; work on a feature branch, commit per phase, merge to `main`)

### Phase 1 — Multi-dev profiles (replace hardcoded devs)
- Team state becomes `members[]`: `{id, name, color, skills}` per dev
- Setup screen: enter your name + what you can do (skills/bio); join existing team or create new
- Profile section: edit your own profile; view teammates' profiles + skills
- `sync.php`: members stored in `team.json`; add list/save member endpoints

### Phase 2 — Git-like single-branch version control
- Each project gets linear `commits[]` history: `{id, message, author, ts, version}`
- Every auto-save appends one commit (single branch only — no branch/PR UI anywhere)
- History panel: browse commits, inspect a commit, **"Revert to this commit"** restores tasks/messages/activity to that point (recorded as a new commit so nothing is ever lost)

### Phase 3 — AI task splitting from profiles
- AI system prompt includes all members' profiles/skills
- AI assigns tasks based on who can do what, sets deadlines according to skills
- Update `aiSplitTasks()` + quick prompts to leverage profiles

### Phase 4 — Polish, README, deploy notes
- Update `README.md` (multi-dev setup, profiles, version control/undo)
- Edge cases: empty/rejoining teams, conflict handling with history
- Commit everything and merge to `main`

## Notes
- Keep the zero-build, single-file + PHP style of the project (deploy to any PHP host).
- "Undo specific changes" = revert a project to a past commit, never destructive (creates a new commit).
- No branches, no PRs, no merging — one linear history per project.