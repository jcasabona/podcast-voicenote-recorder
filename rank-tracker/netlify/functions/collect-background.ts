// Background collector for manual/on-demand runs. Netlify runs any function
// whose name ends in `-background` asynchronously (returns 202 immediately,
// up to a 15-minute budget) — plenty of headroom for the throttled passes,
// unlike the ~10s synchronous limit.
//
// This is invoked by POST /api/collect after that endpoint checks the bearer
// token. It re-checks the token so it can't be driven directly by an
// unauthenticated caller.

import { runCollection } from "../lib/tracker.ts";
import { checkBearer } from "../lib/respond.ts";

export default async (req: Request): Promise<Response> => {
  if (!checkBearer(req)) {
    console.warn("[collect-background] refused: bad or missing bearer token");
    return new Response("unauthorized", { status: 401 });
  }
  const summary = await runCollection();
  console.log("[collect-background] manual run complete", JSON.stringify(summary));
  return new Response("ok", { status: 200 });
};
