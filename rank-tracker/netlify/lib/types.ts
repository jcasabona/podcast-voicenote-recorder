// Shared types for the rank tracker.

export type Platform = "apple" | "spotify";

export interface Competitor {
  name: string;
  appleCollectionId: string;
  spotifyShowId: string;
}

export interface ShowConfig {
  name: string;
  publisher?: string;
  feedUrl: string;
  appleCollectionId: string;
  spotifyShowId: string;
}

export interface TrackerConfig {
  show: ShowConfig;
  keywords: string[];
  competitors: Competitor[];
}

/** One entry in a keyword's top-N list for a platform. */
export interface TopResult {
  rank: number;
  name: string;
  id: string;
}

/** The show's result for a single keyword on a single platform. */
export interface KeywordResult {
  keyword: string;
  platform: Platform;
  /** 1-based position of our show, or null when not found in the searched window. */
  position: number | null;
  /** How many results were scanned (defines the "unranked" ceiling, e.g. 200). */
  scanned: number;
  top10: TopResult[];
  /** Map of competitor name -> their 1-based position (null if not found). */
  competitorPositions: Record<string, number | null>;
  /** Present only when the lookup failed for this keyword/platform. */
  error?: string;
}

/** One collection run. Stored in Netlify Blobs under runs/{ISO-date}.json. */
export interface Snapshot {
  runAt: string;
  results: KeywordResult[];
}

/** A single point in a keyword's history series. */
export interface HistoryPoint {
  date: string;
  position: number | null;
}
