// Apple Podcasts via the iTunes Search API (no auth).
// https://developer.apple.com/library/archive/documentation/AudioVideo/Conceptual/iTuneSearchAPI/
//
// NOTE: result order approximates the in-app Apple Podcasts search ranking.
// The in-app ranking layers on behavioral + personalization signals we can't
// see, so treat positions as a close proxy, not the exact in-app order. This
// is the same limitation the paid tools (Ausha PSO, PodSEO) work under.

import { fetchJson } from "./http.ts";
import type { TopResult } from "./types.ts";

export interface AppleResult {
  collectionId?: number;
  collectionName?: string;
  artistName?: string;
  feedUrl?: string;
}

interface ITunesSearchResponse {
  resultCount: number;
  results: AppleResult[];
}

/** The window we scan; "not found in this window" means unranked (limit+). */
export const APPLE_LIMIT = 200;

export async function searchApple(keyword: string, limit = APPLE_LIMIT): Promise<AppleResult[]> {
  const url =
    "https://itunes.apple.com/search?" +
    new URLSearchParams({
      term: keyword,
      media: "podcast",
      entity: "podcast",
      country: "US",
      limit: String(limit),
    }).toString();
  const data = await fetchJson<ITunesSearchResponse>(url);
  return data.results ?? [];
}

/** 1-based position of collectionId in results, or null if absent. */
export function applePosition(results: AppleResult[], collectionId: string): number | null {
  const idx = results.findIndex((r) => String(r.collectionId) === String(collectionId));
  return idx === -1 ? null : idx + 1;
}

export function appleTop10(results: AppleResult[]): TopResult[] {
  return results.slice(0, 10).map((r, i) => ({
    rank: i + 1,
    name: r.collectionName ?? "(unknown)",
    id: String(r.collectionId ?? ""),
  }));
}

/**
 * Resolve a show's Apple collectionId by matching the canonical RSS feed URL,
 * falling back to a name match. Used once at setup.
 */
export async function resolveAppleId(
  showName: string,
  feedUrl: string,
): Promise<{ collectionId: string; matchedOn: "feedUrl" | "name" } | null> {
  const results = await searchApple(showName, 50);
  const normalize = (u?: string) => (u ?? "").replace(/\/+$/, "").toLowerCase();
  const byFeed = results.find((r) => normalize(r.feedUrl) === normalize(feedUrl));
  if (byFeed?.collectionId) {
    return { collectionId: String(byFeed.collectionId), matchedOn: "feedUrl" };
  }
  const byName = results.find(
    (r) => (r.collectionName ?? "").toLowerCase() === showName.toLowerCase(),
  );
  if (byName?.collectionId) {
    return { collectionId: String(byName.collectionId), matchedOn: "name" };
  }
  return null;
}
