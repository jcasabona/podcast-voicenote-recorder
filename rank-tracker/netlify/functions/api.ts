// Read API + manual-trigger dispatch. All GET endpoints are public read-only
// (public search data). POST /api/collect is bearer-protected.
//
//   GET  /api/latest
//   GET  /api/history?keyword=X[&platform=apple|spotify]
//   GET  /api/runs                      (list of run dates)
//   POST /api/collect                   (Authorization: Bearer $COLLECT_TOKEN)

import type { Config } from "@netlify/functions";
import { getHistory, getIndex, getLatest, getPrevious } from "../lib/store.ts";
import { loadConfig } from "../lib/config.ts";
import { checkBearer, error, json } from "../lib/respond.ts";
import type { Platform } from "../lib/types.ts";

function resource(pathname: string): string {
  const parts = pathname.split("/").filter(Boolean); // e.g. ["api","latest"]
  return parts[parts.length - 1] ?? "";
}

async function triggerCollect(req: Request): Promise<Response> {
  if (!checkBearer(req)) {
    return error("unauthorized — send Authorization: Bearer <COLLECT_TOKEN>", 401);
  }
  const token = process.env.COLLECT_TOKEN as string;
  const base = process.env.URL ?? new URL(req.url).origin;
  const target = `${base}/.netlify/functions/collect-background`;
  try {
    // Fire the background worker; it returns 202 immediately (15-min budget).
    await fetch(target, {
      method: "POST",
      headers: { authorization: `Bearer ${token}` },
    });
  } catch (err) {
    return error(`failed to start collection: ${err instanceof Error ? err.message : err}`, 502);
  }
  return json({ ok: true, status: "collection started" }, 202);
}

export default async (req: Request): Promise<Response> => {
  const url = new URL(req.url);
  const res = resource(url.pathname);

  if (req.method === "POST" && res === "collect") {
    return triggerCollect(req);
  }

  if (req.method !== "GET") {
    return error(`method ${req.method} not allowed`, 405);
  }

  switch (res) {
    case "latest": {
      const latest = await getLatest();
      if (!latest) return error("no runs yet", 404);
      const previous = await getPrevious();
      const cfg = loadConfig();
      return json({
        show: { name: cfg.show.name },
        latest,
        previousRunAt: previous?.runAt ?? null,
      });
    }
    case "history": {
      const keyword = url.searchParams.get("keyword");
      if (!keyword) return error("missing ?keyword=", 400);
      const platformParam = url.searchParams.get("platform");
      if (platformParam && platformParam !== "apple" && platformParam !== "spotify") {
        return error("platform must be 'apple' or 'spotify'", 400);
      }
      if (platformParam) {
        const series = await getHistory(keyword, platformParam as Platform);
        return json({ keyword, platform: platformParam, series });
      }
      const [apple, spotify] = await Promise.all([
        getHistory(keyword, "apple"),
        getHistory(keyword, "spotify"),
      ]);
      return json({ keyword, apple, spotify });
    }
    case "runs": {
      const runs = await getIndex();
      return json({ runs });
    }
    default:
      return error(`unknown endpoint /api/${res}`, 404);
  }
};

export const config: Config = { path: "/api/*" };
