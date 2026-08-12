// Scheduled collector — runs weekly on cron (Tuesdays 12:00 UTC, the day after
// Monday episodes drop). Netlify runs scheduled functions with the extended
// (background) timeout, so the throttled Apple + Spotify passes have room.
//
// For a manual/on-demand run, POST /api/collect (bearer-protected) which kicks
// off the collect-background function instead.

import type { Config } from "@netlify/functions";
import { runCollection } from "../lib/tracker.ts";
import { json } from "../lib/respond.ts";

export default async (): Promise<Response> => {
  const summary = await runCollection();
  console.log("[collect] scheduled run complete", JSON.stringify(summary));
  return json({ ok: summary.errors.length === 0, summary });
};

export const config: Config = { schedule: "0 12 * * 2" };
