/**
 * 站点密码门：HMAC 签名 Cookie（Edge / Node 通用，Web Crypto）
 * 参考常见做法：middleware 拦路由 + /api/auth/login 发 Cookie
 * （同类库：https://github.com/TimMikeladze/next-protect）
 */

export const SITE_AUTH_COOKIE = "site_gate";
export const SITE_AUTH_MAX_AGE = 60 * 60 * 24 * 7; // 7 天

function getPassword(): string {
  return (process.env.SITE_PASSWORD || "").trim();
}

function getSecret(): string {
  return (process.env.AUTH_SECRET || process.env.SITE_PASSWORD || "").trim();
}

export function isSiteAuthConfigured(): boolean {
  return Boolean(getPassword() && getSecret());
}

function toHex(buf: ArrayBuffer): string {
  return [...new Uint8Array(buf)]
    .map((b) => b.toString(16).padStart(2, "0"))
    .join("");
}

function timingSafeEqualStr(a: string, b: string): boolean {
  if (a.length !== b.length) return false;
  let out = 0;
  for (let i = 0; i < a.length; i++) out |= a.charCodeAt(i) ^ b.charCodeAt(i);
  return out === 0;
}

async function hmacHex(message: string, secret: string): Promise<string> {
  const key = await crypto.subtle.importKey(
    "raw",
    new TextEncoder().encode(secret),
    { name: "HMAC", hash: "SHA-256" },
    false,
    ["sign"]
  );
  const sig = await crypto.subtle.sign(
    "HMAC",
    key,
    new TextEncoder().encode(message)
  );
  return toHex(sig);
}

/** 校验用户输入的站点密码 */
export async function verifySitePassword(input: string): Promise<boolean> {
  const expected = getPassword();
  if (!expected) return false;
  // 长度不同时仍做一次 dummy hmac，降低时序差异
  const a = new TextEncoder().encode(input);
  const b = new TextEncoder().encode(expected);
  if (a.length !== b.length) {
    await hmacHex(input, getSecret() || "x");
    return false;
  }
  return timingSafeEqualStr(input, expected);
}

export async function createSiteAuthToken(): Promise<string | null> {
  const secret = getSecret();
  if (!secret) return null;
  const exp = Date.now() + SITE_AUTH_MAX_AGE * 1000;
  const payload = `ok.${exp}`;
  const sig = await hmacHex(payload, secret);
  return `${payload}.${sig}`;
}

export async function verifySiteAuthToken(
  token: string | undefined | null
): Promise<boolean> {
  if (!token) return false;
  const secret = getSecret();
  if (!secret) return false;
  const parts = token.split(".");
  if (parts.length !== 3) return false;
  const [flag, expStr, sig] = parts;
  if (flag !== "ok") return false;
  const exp = Number(expStr);
  if (!Number.isFinite(exp) || Date.now() > exp) return false;
  const expected = await hmacHex(`${flag}.${expStr}`, secret);
  return timingSafeEqualStr(sig, expected);
}

export function siteAuthCookieOptions() {
  return {
    httpOnly: true as const,
    secure: process.env.NODE_ENV === "production",
    sameSite: "lax" as const,
    path: "/",
    maxAge: SITE_AUTH_MAX_AGE,
  };
}
