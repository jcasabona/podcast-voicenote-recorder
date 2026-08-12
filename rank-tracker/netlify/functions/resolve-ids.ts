// One-time setup helper. Resolves the show's Apple collectionId (by matching
// the canonical RSS feed URL) and Spotify show id (by name + publisher), so you
// can paste them into config/keywords.json.
//
//   GET /resolve-ids
//   GET /resolve-ids?name=Some%20Show&feedUrl=https://.../rss&publisher=Someone
//
// Also works for resolving competitor ids — pass their name/feedUrl/publisher.

import type { Config } from "@netlify/functions";
import { loadConfig } from "../lib/config.ts";
import { resolveAppleId } from "../lib/apple.ts";
import { getSpotifyToken, hasSpotifyCreds, resolveSpotifyId } from "../lib/spotify.ts";
import { json } from "../lib/respond.ts";

export default async (req: Request): Promise<Response> => {
  const cfg = loadConfig();
  const url = new URL(req.url);
  const name = url.searchParams.get("name") ?? cfg.show.name;
  const feedUrl = url.searchParams.get("feedUrl") ?? cfg.show.feedUrl;
  const publisher = url.searchParams.get("publisher") ?? cfg.show.publisher;

  const apple: Record<string, unknown> = {};
  try {
    const res = await resolveAppleId(name, feedUrl);
    if (res) {
      apple.appleCollectionId = res.collectionId;
      apple.matchedOn = res.matchedOn;
    } else {
      apple.error = `No Apple result matched feedUrl "${feedUrl}" or name "${name}".`;
    }
  } catch (err) {
    apple.error = String(err instanceof Error ? err.message : err);
  }

  const spotify: Record<string, unknown> = {};
  if (!hasSpotifyCreds()) {
    spotify.skipped = "Set SPOTIFY_CLIENT_ID and SPOTIFY_CLIENT_SECRET to resolve the Spotify id.";
  } else {
    try {
      const token = await getSpotifyToken();
      const res = await resolveSpotifyId(name, publisher, token);
      if (res) {
        spotify.spotifyShowId = res.showId;
        spotify.matchedOn = res.matchedOn;
      } else {
        spotify.error = `No Spotify show matched name "${name}"${publisher ? ` + publisher "${publisher}"` : ""}.`;
      }
    } catch (err) {
      spotify.error = String(err instanceof Error ? err.message : err);
    }
  }

  return json({
    ok: Boolean(apple.appleCollectionId || spotify.spotifyShowId),
    query: { name, feedUrl, publisher },
    hint: "Paste appleCollectionId and spotifyShowId into config/keywords.json, then redeploy.",
    apple,
    spotify,
  });
};

export const config: Config = { path: "/resolve-ids" };
