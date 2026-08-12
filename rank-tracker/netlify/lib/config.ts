import type { TrackerConfig } from "./types.ts";
// Bundled with each function (esbuild inlines JSON imports). The leading
// underscore keys in the JSON (e.g. `_comment`) are ignored by this loader.
import raw from "../../config/keywords.json";

export function loadConfig(): TrackerConfig {
  const cfg = raw as unknown as TrackerConfig;
  const competitors = (cfg.competitors ?? []).filter(
    (c) => c.name && !c.name.startsWith("PLACEHOLDER"),
  );
  return {
    show: cfg.show,
    keywords: cfg.keywords ?? [],
    competitors,
  };
}

export function assertResolvedIds(cfg: TrackerConfig): string[] {
  const problems: string[] = [];
  if (!cfg.show.appleCollectionId || cfg.show.appleCollectionId === "RESOLVE_AT_SETUP") {
    problems.push("show.appleCollectionId is not resolved (see README > One-time setup).");
  }
  if (!cfg.show.spotifyShowId || cfg.show.spotifyShowId === "RESOLVE_AT_SETUP") {
    problems.push("show.spotifyShowId is not resolved (see README > One-time setup).");
  }
  return problems;
}
