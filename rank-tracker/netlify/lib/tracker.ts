// Collection orchestrator: for each keyword, query Apple + Spotify, find the
// show's position and the top-10, note competitor positions, and persist one
// snapshot. Called by both the scheduled collector and the manual trigger.

import { assertResolvedIds, loadConfig } from "./config.ts";
import {
  APPLE_LIMIT,
  applePosition,
  appleTop10,
  searchApple,
  type AppleResult,
} from "./apple.ts";
import {
  getSpotifyToken,
  hasSpotifyCreds,
  searchSpotifyShows,
  spotifyPosition,
  spotifyScanWindow,
  spotifyTop10,
  type SpotifyShow,
} from "./spotify.ts";
import { sleep, throttleMs } from "./http.ts";
import { saveSnapshot } from "./store.ts";
import type { Competitor, KeywordResult, Snapshot } from "./types.ts";

export interface CollectionSummary {
  runAt: string;
  date: string;
  keywords: number;
  appleResults: number;
  spotifyResults: number;
  spotifySkipped: boolean;
  errors: string[];
}

function appleCompetitorPositions(
  results: AppleResult[],
  competitors: Competitor[],
): Record<string, number | null> {
  const out: Record<string, number | null> = {};
  for (const c of competitors) {
    if (!c.appleCollectionId) continue;
    out[c.name] = applePosition(results, c.appleCollectionId);
  }
  return out;
}

function spotifyCompetitorPositions(
  shows: SpotifyShow[],
  competitors: Competitor[],
): Record<string, number | null> {
  const out: Record<string, number | null> = {};
  for (const c of competitors) {
    if (!c.spotifyShowId) continue;
    out[c.name] = spotifyPosition(shows, c.spotifyShowId);
  }
  return out;
}

export async function runCollection(): Promise<CollectionSummary> {
  const cfg = loadConfig();
  const errors: string[] = [];

  const idProblems = assertResolvedIds(cfg);
  errors.push(...idProblems);

  const appleDelay = throttleMs("APPLE_THROTTLE_MS", 3000);
  const spotifyDelay = throttleMs("SPOTIFY_THROTTLE_MS", 300);
  const spotifyEnabled = hasSpotifyCreds() && cfg.show.spotifyShowId !== "RESOLVE_AT_SETUP";
  if (!spotifyEnabled) {
    errors.push("Spotify skipped (missing creds or unresolved spotifyShowId).");
  }

  const results: KeywordResult[] = [];
  let appleResults = 0;
  let spotifyResults = 0;

  // Apple pass.
  for (let i = 0; i < cfg.keywords.length; i++) {
    const keyword = cfg.keywords[i];
    try {
      const found = await searchApple(keyword);
      results.push({
        keyword,
        platform: "apple",
        position: applePosition(found, cfg.show.appleCollectionId),
        scanned: APPLE_LIMIT,
        top10: appleTop10(found),
        competitorPositions: appleCompetitorPositions(found, cfg.competitors),
      });
      appleResults++;
    } catch (err) {
      results.push({
        keyword,
        platform: "apple",
        position: null,
        scanned: APPLE_LIMIT,
        top10: [],
        competitorPositions: {},
        error: String(err instanceof Error ? err.message : err),
      });
      errors.push(`apple "${keyword}": ${err instanceof Error ? err.message : err}`);
    }
    if (i < cfg.keywords.length - 1) await sleep(appleDelay);
  }

  // Spotify pass.
  if (spotifyEnabled) {
    const scan = spotifyScanWindow();
    let token: string;
    try {
      token = await getSpotifyToken();
    } catch (err) {
      errors.push(`spotify auth: ${err instanceof Error ? err.message : err}`);
      token = "";
    }
    if (token) {
      for (let i = 0; i < cfg.keywords.length; i++) {
        const keyword = cfg.keywords[i];
        try {
          const shows = await searchSpotifyShows(keyword, token, scan);
          results.push({
            keyword,
            platform: "spotify",
            position: spotifyPosition(shows, cfg.show.spotifyShowId),
            scanned: scan,
            top10: spotifyTop10(shows),
            competitorPositions: spotifyCompetitorPositions(shows, cfg.competitors),
          });
          spotifyResults++;
        } catch (err) {
          results.push({
            keyword,
            platform: "spotify",
            position: null,
            scanned: scan,
            top10: [],
            competitorPositions: {},
            error: String(err instanceof Error ? err.message : err),
          });
          errors.push(`spotify "${keyword}": ${err instanceof Error ? err.message : err}`);
        }
        if (i < cfg.keywords.length - 1) await sleep(spotifyDelay);
      }
    }
  }

  const runAt = new Date().toISOString();
  const snapshot: Snapshot = { runAt, results };
  const date = await saveSnapshot(snapshot);

  return {
    runAt,
    date,
    keywords: cfg.keywords.length,
    appleResults,
    spotifyResults,
    spotifySkipped: !spotifyEnabled,
    errors,
  };
}
