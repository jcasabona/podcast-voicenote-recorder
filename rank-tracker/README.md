# Podcast Rank Tracker

A self-hosted podcast keyword rank tracker for **Streamlined Solopreneur**. It
checks where the show ranks for a set of target keywords on **Apple Podcasts**
and **Spotify** each week, stores the history, and shows a dashboard — replacing
the tracking function of Ausha PSO / PodSEO at **$0/mo** on Netlify's free tier.

Non-goals (by design): search-volume estimates, difficulty scores, metadata
scoring, Amazon Music (no public API), social clips. Optimization strategy stays
in the `podcast-seo` skill.

---

## Architecture

```
.
├─ netlify.toml                     # functions + build config
├─ config/keywords.json             # keywords + show identity + competitors
├─ public/index.html                # dashboard (static, fetches /api/*)
└─ netlify/
   ├─ functions/
   │  ├─ validate.ts                # M0 gate — iTunes + Spotify reachability
   │  ├─ resolve-ids.ts             # setup helper — resolves Apple/Spotify IDs
   │  ├─ collect.ts                 # scheduled weekly collector (cron)
   │  ├─ collect-background.ts      # manual collector (background, 15-min budget)
   │  └─ api.ts                     # GET /api/latest, /api/history, /api/runs; POST /api/collect
   └─ lib/                          # shared logic (apple, spotify, blobs store, orchestrator)
```

**Collector flow:** for each keyword → query Apple + Spotify search → find the
show's position and the top-10 → note competitor positions → append one snapshot
to Netlify Blobs. The dashboard reads via the API function.

**Why Netlify:** Scheduled Functions (weekly cron, no server), Netlify Blobs
(persistent JSON, no database), static hosting (dashboard at a URL), all on the
free tier.

---

## ⚠️ RISK #1 — validate before trusting anything (Milestone 0)

During research (2026-08-12) the iTunes Search API returned **empty responses
when queried from some cloud/datacenter IPs**. Apple appears to filter certain
ranges. **Before relying on the collector, confirm Apple answers from Netlify's
infrastructure.**

1. Deploy the site (below).
2. Hit **`https://<your-site>.netlify.app/validate`**.
3. You want `"ok": true` and a non-zero `apple.resultCount`. The `spotify` block
   confirms auth too (once you've added the Spotify env vars).

If Apple is blocked from Netlify, use a fallback collector — the storage, API,
and dashboard don't change:

- **Fallback A — GitHub Action:** run the collection on a schedule from an
  Action (different IP pool), and `POST` the snapshot to `/api/collect`, or
  commit the JSON into the repo so Netlify redeploys the data with the site.
- **Fallback B — local/Cowork task:** run the collection as a local script on a
  schedule and `POST` results to `/api/collect`.

The shared logic in `netlify/lib/` (`tracker.ts`, `apple.ts`, `spotify.ts`,
`store.ts`) is written so a fallback runner can call `runCollection()` directly.

**Do not build past M0 until validation passes.**

---

## Setup

### 1. Create the Netlify site

- Connect this repo in Netlify. No base directory needed — the repo root is the
  deploy root, so Netlify reads `netlify.toml`, installs from `package.json`, and
  finds the functions and `public/` dashboard automatically.
- Netlify Blobs is enabled automatically for deployed functions — no setup.

### 2. Create a Spotify developer app (5 min)

1. Go to <https://developer.spotify.com/dashboard> → **Create app** (any name;
   redirect URI can be `http://localhost` — client-credentials doesn't use it).
2. Copy the **Client ID** and **Client Secret**.
3. In Netlify → **Site settings → Environment variables**, add:
   - `SPOTIFY_CLIENT_ID`
   - `SPOTIFY_CLIENT_SECRET`

### 3. Set the manual-trigger token

Add one more env var so the manual collection endpoint is protected:

- `COLLECT_TOKEN` — any long random string.

### 4. Resolve the show IDs (one-time)

The config ships with `appleCollectionId` and `spotifyShowId` set to
`RESOLVE_AT_SETUP`. Resolve them from the deployed site:

```
GET https://<your-site>.netlify.app/resolve-ids
```

It matches the show's Apple `collectionId` by the canonical RSS feed
(`https://feeds.transistor.fm/streamlined`) and the Spotify show id by name +
publisher. Paste the returned `appleCollectionId` and `spotifyShowId` into
`config/keywords.json`, commit, and redeploy.

> You can resolve competitors the same way:
> `…/resolve-ids?name=Some%20Show&feedUrl=https://…/rss&publisher=Publisher%20Name`.

### 5. Finalize keywords and competitors

Edit `config/keywords.json`:

- `keywords` — the ~10 targets from the `podcast-seo` skill (Phase 1B). The
  tracker works with any count; the file ships with sensible placeholders.
- `competitors` — 3–5 shows to track (name + resolved Apple/Spotify IDs).

Commit and redeploy after any config change.

### 6. Run it

- **Wait for the weekly cron** (Tuesdays 12:00 UTC), or
- **Trigger manually:**
  ```
  curl -X POST https://<your-site>.netlify.app/api/collect \
    -H "Authorization: Bearer $COLLECT_TOKEN"
  ```
  This returns `202 collection started` and runs in the background.
- Open the site root to see the **dashboard**.

---

## Configuration reference

### Environment variables

| Var | Required | Purpose |
| :-- | :-- | :-- |
| `SPOTIFY_CLIENT_ID` | for Spotify | Spotify app client id |
| `SPOTIFY_CLIENT_SECRET` | for Spotify | Spotify app client secret |
| `COLLECT_TOKEN` | for manual runs | Bearer token gating `POST /api/collect` |
| `APPLE_THROTTLE_MS` | optional | Delay between Apple calls (default `3000`) |
| `SPOTIFY_THROTTLE_MS` | optional | Delay between Spotify calls (default `300`) |
| `SPOTIFY_SCAN` | optional | How deep to scan Spotify results, max 100 (default `50`) |

If Spotify creds are missing or `spotifyShowId` is unresolved, the collector
runs **Apple-only** and records a note — it won't fail the whole run.

### `config/keywords.json`

```jsonc
{
  "show": {
    "name": "Streamlined Solopreneur",
    "publisher": "Joe Casabona",
    "feedUrl": "https://feeds.transistor.fm/streamlined",
    "appleCollectionId": "RESOLVE_AT_SETUP",
    "spotifyShowId": "RESOLVE_AT_SETUP"
  },
  "keywords": ["…"],
  "competitors": [{ "name": "…", "appleCollectionId": "…", "spotifyShowId": "…" }]
}
```

---

## API

All GET endpoints are public read-only (public search data, nothing sensitive).

| Method | Endpoint | Notes |
| :-- | :-- | :-- |
| `GET` | `/api/latest` | Most recent snapshot + show name + previous run timestamp |
| `GET` | `/api/history?keyword=X[&platform=apple\|spotify]` | Position series; omit `platform` for both |
| `GET` | `/api/runs` | List of run dates |
| `POST` | `/api/collect` | Manual trigger — `Authorization: Bearer $COLLECT_TOKEN` |
| `GET` | `/validate` | M0 gate diagnostics |
| `GET` | `/resolve-ids` | One-time ID resolution helper |

### Data model

One snapshot per run, in Netlify Blobs under `runs/{YYYY-MM-DD}.json`, plus a
`runs/index.json` list so history is served without listing the store:

```json
{
  "runAt": "2026-08-19T12:00:00Z",
  "results": [
    {
      "keyword": "solopreneur systems",
      "platform": "apple",
      "position": 7,
      "scanned": 200,
      "top10": [{ "rank": 1, "name": "…", "id": "…" }],
      "competitorPositions": { "Some Show": 3 }
    }
  ]
}
```

A keyword the show doesn't rank for records `position: null` and the dashboard
shows **`200+`** (or the Spotify scan ceiling) — not an error.

---

## Dashboard

Single static page (no framework), brand-matched (navy `#082C45`, ink
`#1B2F3D`, cream `#F7F4EB`, paper `#FBF8F0`, gold `#F7D677`, Geologica):

- Keyword × platform table: current position, delta vs previous run (▲/▼/—),
  best-ever position, and an inline-SVG sparkline trend.
- Apple / Spotify toggle.
- Competitor comparison table for the latest run.
- Last-updated timestamp.

---

## A caveat to keep in mind

Result order from the iTunes Search API and the Spotify Web API **approximates**
in-app search ranking. The in-app experiences add behavioral and personalized
signals that public APIs don't expose, so treat positions as a close, consistent
proxy for tracking movement over time — not the exact in-app order. This is the
same limitation the paid tools operate under.

---

## Development

```bash
npm install
npm run typecheck      # tsc --noEmit
npm run dev            # netlify dev (needs the Netlify CLI + env vars)
```

## Schedule

`collect.ts` runs on cron `0 12 * * 2` (Tuesdays 12:00 UTC — the day after
Monday episodes drop), declared in the function via
`export const config = { schedule }`.

## Phase 2 (optional, later)

A Cowork scheduled task on Joe's machine fetches `/api/latest` weekly and writes
a delta report (movers, drops, competitor changes) to
`AI Memory/Files/Reports/`, keeping Obsidian as the reading surface without the
tool depending on any one machine.
