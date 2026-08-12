// M0 gate — RISK #1 validation.
//
// Deploy this and hit /validate (or /.netlify/functions/validate) FROM NETLIFY.
// It confirms two things before any of the rest is worth building:
//   1. The iTunes Search API answers from Netlify's IPs (research saw empty
//      responses from some datacenter IPs — this proves whether Netlify is OK).
//   2. Spotify client-credentials auth succeeds with the configured env vars.
//
// If Apple is blocked here, fall back to the GitHub Action collector (see
// README > RISK #1 fallbacks). Do not build past M0 until this returns ok:true.

import type { Config } from "@netlify/functions";
import { loadConfig } from "../lib/config.ts";
import { searchApple } from "../lib/apple.ts";
import { getSpotifyToken, hasSpotifyCreds, searchSpotifyShows } from "../lib/spotify.ts";
import { json } from "../lib/respond.ts";

export default async (): Promise<Response> => {
  const cfg = loadConfig();
  const sampleKeyword = cfg.keywords[0] ?? "solopreneur";

  // --- Apple ---
  const apple: Record<string, unknown> = { ok: false };
  try {
    const results = await searchApple(sampleKeyword, 50);
    const feedSeen = results.some(
      (r) => (r.feedUrl ?? "").replace(/\/+$/, "") === cfg.show.feedUrl.replace(/\/+$/, ""),
    );
    apple.ok = results.length > 0;
    apple.sampleKeyword = sampleKeyword;
    apple.resultCount = results.length;
    apple.firstResult = results[0]?.collectionName ?? null;
    apple.showFeedSeenInResults = feedSeen;
  } catch (err) {
    apple.error = String(err instanceof Error ? err.message : err);
  }

  // --- Spotify ---
  const spotify: Record<string, unknown> = { ok: false };
  if (!hasSpotifyCreds()) {
    spotify.skipped = "Set SPOTIFY_CLIENT_ID and SPOTIFY_CLIENT_SECRET to test Spotify.";
  } else {
    try {
      const token = await getSpotifyToken();
      const shows = await searchSpotifyShows(sampleKeyword, token, 10);
      spotify.ok = true;
      spotify.authOk = true;
      spotify.sampleResultCount = shows.length;
      spotify.firstResult = shows[0]?.name ?? null;
    } catch (err) {
      spotify.error = String(err instanceof Error ? err.message : err);
    }
  }

  const ok = apple.ok === true;
  return json(
    {
      ok,
      note: ok
        ? "M0 gate passed for Apple. Check the spotify block too before M1."
        : "M0 gate FAILED for Apple from Netlify — use a fallback collector (see README).",
      apple,
      spotify,
    },
    ok ? 200 : 502,
  );
};

export const config: Config = { path: "/validate" };
