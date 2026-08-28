import { NextRequest, NextResponse } from "next/server";
import {
  SITE_AUTH_COOKIE,
  isSiteAuthConfigured,
  verifySiteAuthToken,
} from "@/lib/site-auth";

function isPublicPath(pathname: string): boolean {
  if (pathname === "/login") return true;
  if (pathname === "/api/auth/login") return true;
  if (pathname.startsWith("/_next/")) return true;
  if (pathname === "/favicon.ico") return true;
  // 静态资源（头像、赞赏码、manifest、PWA）
  if (/\.(?:png|jpg|jpeg|gif|webp|svg|ico|webmanifest|js|css|map|txt|xml)$/i.test(pathname)) {
    return true;
  }
  return false;
}

export async function middleware(request: NextRequest) {
  const { pathname } = request.nextUrl;

  if (isPublicPath(pathname)) {
    // 已登录访问 /login → 回首页
    if (pathname === "/login") {
      const token = request.cookies.get(SITE_AUTH_COOKIE)?.value;
      if (token && (await verifySiteAuthToken(token))) {
        return NextResponse.redirect(new URL("/", request.url));
      }
    }
    return NextResponse.next();
  }

  // 未配置密码时：开发方便可放行；生产建议务必配置 SITE_PASSWORD
  if (!isSiteAuthConfigured()) {
    if (process.env.NODE_ENV === "production") {
      if (pathname.startsWith("/api/")) {
        return NextResponse.json(
          { code: 503, msg: "站点未配置访问密码（SITE_PASSWORD）" },
          { status: 503 }
        );
      }
      const url = request.nextUrl.clone();
      url.pathname = "/login";
      url.searchParams.set("error", "not_configured");
      return NextResponse.redirect(url);
    }
    return NextResponse.next();
  }

  const token = request.cookies.get(SITE_AUTH_COOKIE)?.value;
  if (token && (await verifySiteAuthToken(token))) {
    return NextResponse.next();
  }

  if (pathname.startsWith("/api/")) {
    return NextResponse.json(
      { code: 401, msg: "请先输入访问密码" },
      { status: 401 }
    );
  }

  const loginUrl = request.nextUrl.clone();
  loginUrl.pathname = "/login";
  loginUrl.searchParams.set("next", pathname);
  return NextResponse.redirect(loginUrl);
}

export const config = {
  matcher: ["/((?!_next/static|_next/image).*)"],
};
