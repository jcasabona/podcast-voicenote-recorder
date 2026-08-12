// JSON response helpers shared by the HTTP functions.

const JSON_HEADERS = {
  "content-type": "application/json; charset=utf-8",
  // Dashboard and API live on the same origin, but keep reads open — it's
  // public search data with nothing sensitive.
  "access-control-allow-origin": "*",
  "cache-control": "no-store",
};

export function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body, null, 2), { status, headers: JSON_HEADERS });
}

export function error(message: string, status = 500, extra: Record<string, unknown> = {}): Response {
  return json({ ok: false, error: message, ...extra }, status);
}

/** Constant-time-ish bearer check against the COLLECT_TOKEN env var. */
export function checkBearer(req: Request): boolean {
  const expected = process.env.COLLECT_TOKEN;
  if (!expected) return false;
  const header = req.headers.get("authorization") ?? "";
  const token = header.replace(/^Bearer\s+/i, "");
  return token.length > 0 && token === expected;
}
