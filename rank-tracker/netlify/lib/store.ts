// Persistence via Netlify Blobs. One JSON blob per run, plus an index blob so
// the API can serve history without listing the whole store.

import { getStore } from "@netlify/blobs";
import type { HistoryPoint, Platform, Snapshot } from "./types.ts";

const STORE = "rank-tracker";
const INDEX_KEY = "runs/index.json";
const runKey = (date: string) => `runs/${date}.json`;

function store() {
  return getStore(STORE);
}

/** Ordered list (oldest first) of run dates present in the store. */
export async function getIndex(): Promise<string[]> {
  const idx = await store().get(INDEX_KEY, { type: "json" });
  return Array.isArray(idx) ? (idx as string[]) : [];
}

async function setIndex(dates: string[]): Promise<void> {
  await store().setJSON(INDEX_KEY, dates);
}

/** Persist a snapshot and record its date in the index. Idempotent per date. */
export async function saveSnapshot(snapshot: Snapshot): Promise<string> {
  const date = snapshot.runAt.slice(0, 10); // YYYY-MM-DD
  await store().setJSON(runKey(date), snapshot);
  const index = await getIndex();
  if (!index.includes(date)) {
    index.push(date);
    index.sort();
    await setIndex(index);
  }
  return date;
}

export async function getRun(date: string): Promise<Snapshot | null> {
  const run = await store().get(runKey(date), { type: "json" });
  return (run as Snapshot) ?? null;
}

export async function getLatest(): Promise<Snapshot | null> {
  const index = await getIndex();
  if (index.length === 0) return null;
  return getRun(index[index.length - 1]);
}

/** The run immediately before the latest, or null if there's only one. */
export async function getPrevious(): Promise<Snapshot | null> {
  const index = await getIndex();
  if (index.length < 2) return null;
  return getRun(index[index.length - 2]);
}

/** Time series of our position for one keyword/platform across all runs. */
export async function getHistory(
  keyword: string,
  platform: Platform,
): Promise<HistoryPoint[]> {
  const index = await getIndex();
  const points: HistoryPoint[] = [];
  for (const date of index) {
    const run = await getRun(date);
    if (!run) continue;
    const match = run.results.find(
      (r) => r.keyword === keyword && r.platform === platform,
    );
    if (match) points.push({ date, position: match.position });
  }
  return points;
}
