// Spotify via the Web API (client-credentials flow).
// Requires env vars SPOTIFY_CLIENT_ID and SPOTIFY_CLIENT_SECRET (free dev app).

import { fetchJson } from "./http.ts";
import type { TopResult } from "./types.ts";

export interface SpotifyShow {
  id: string;
  name: string;
  publisher?: string;
}

interface SpotifySearchResponse {
  shows: { items: (SpotifyShow | null)[]; total: number };
}

/** Spotify caps `limit` at 50 per page; we paginate with offset to this ceiling. */
const PAGE = 50;

// Module-scoped token cache (functions can stay warm between invocations).
let cachedToken: { value: string; expiresAt: number } | null = null;

export function hasSpotifyCreds(): boolean {
  return Boolean(process.env.SPOTIFY_CLIENT_ID && process.env.SPOTIFY_CLIENT_SECRET);
}

export async function getSpotifyToken(): Promise<string> {
  const id = process.env.SPOTIFY_CLIENT_ID;
  const secret = process.env.SPOTIFY_CLIENT_SECRET;
  if (!id || !secret) {
    throw new Error("Missing SPOTIFY_CLIENT_ID / SPOTIFY_CLIENT_SECRET env vars.");
  }
  const now = Date.now();
  if (cachedToken && cachedToken.expiresAt > now + 10_000) {
    return cachedToken.value;
  }
  const basic = Buffer.from(`${id}:${secret}`).toString("base64");
  const data = await fetchJson<{ access_token: string; expires_in: number }>(
    "https://accounts.spotify.com/api/token",
    {
      method: "POST",
      headers: {
        Authorization: `Basic ${basic}`,
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: "grant_type=client_credentials",
    },
  );
  cachedToken = {
    value: data.access_token,
    expiresAt: now + data.expires_in * 1000,
  };
  return cachedToken.value;
}

/** Search shows, scanning up to `scan` results (paginating in pages of 50). */
export async function searchSpotifyShows(
  keyword: string,
  token: string,
  scan = PAGE,
): Promise<SpotifyShow[]> {
  const items: SpotifyShow[] = [];
  for (let offset = 0; offset < scan; offset += PAGE) {
    const limit = Math.min(PAGE, scan - offset);
    const url =
      "https://api.spotify.com/v1/search?" +
      new URLSearchParams({
        q: keyword,
        type: "show",
        market: "US",
        limit: String(limit),
        offset: String(offset),
      }).toString();
    const data = await fetchJson<SpotifySearchResponse>(url, {
      headers: { Authorization: `Bearer ${token}` },
    });
    const page = (data.shows?.items ?? []).filter((s): s is SpotifyShow => Boolean(s?.id));
    items.push(...page);
    if (page.length < limit || items.length >= (data.shows?.total ?? items.length)) break;
  }
  return items;
}

/** The scan window (also the "unranked" ceiling), overridable via env. */
export function spotifyScanWindow(): number {
  const raw = process.env.SPOTIFY_SCAN;
  const n = raw ? Number(raw) : NaN;
  return Number.isFinite(n) && n > 0 ? Math.min(n, 100) : PAGE;
}

export function spotifyPosition(shows: SpotifyShow[], showId: string): number | null {
  const idx = shows.findIndex((s) => s.id === showId);
  return idx === -1 ? null : idx + 1;
}

export function spotifyTop10(shows: SpotifyShow[]): TopResult[] {
  return shows.slice(0, 10).map((s, i) => ({ rank: i + 1, name: s.name, id: s.id }));
}

/** Resolve a show's Spotify id by name + publisher. Used once at setup. */
export async function resolveSpotifyId(
  showName: string,
  publisher: string | undefined,
  token: string,
): Promise<{ showId: string; matchedOn: "name+publisher" | "name" } | null> {
  const shows = await searchSpotifyShows(showName, token, PAGE);
  const nameEq = (a?: string, b?: string) => (a ?? "").toLowerCase() === (b ?? "").toLowerCase();
  if (publisher) {
    const exact = shows.find((s) => nameEq(s.name, showName) && nameEq(s.publisher, publisher));
    if (exact) return { showId: exact.id, matchedOn: "name+publisher" };
  }
  const byName = shows.find((s) => nameEq(s.name, showName));
  if (byName) return { showId: byName.id, matchedOn: "name" };
  return null;
}
