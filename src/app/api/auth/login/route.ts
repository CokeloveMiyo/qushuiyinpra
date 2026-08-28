import { NextRequest, NextResponse } from "next/server";
import {
  SITE_AUTH_COOKIE,
  createSiteAuthToken,
  isSiteAuthConfigured,
  siteAuthCookieOptions,
  verifySitePassword,
} from "@/lib/site-auth";

export async function POST(request: NextRequest) {
  if (!isSiteAuthConfigured()) {
    return NextResponse.json(
      { ok: false, error: "服务器未配置 SITE_PASSWORD" },
      { status: 503 }
    );
  }

  let password = "";
  try {
    const body = await request.json();
    password = typeof body?.password === "string" ? body.password : "";
  } catch {
    return NextResponse.json({ ok: false, error: "无效请求" }, { status: 400 });
  }

  if (!(await verifySitePassword(password))) {
    return NextResponse.json({ ok: false, error: "密码错误" }, { status: 401 });
  }

  const token = await createSiteAuthToken();
  if (!token) {
    return NextResponse.json({ ok: false, error: "签发失败" }, { status: 500 });
  }

  const res = NextResponse.json({ ok: true });
  res.cookies.set(SITE_AUTH_COOKIE, token, siteAuthCookieOptions());
  return res;
}
