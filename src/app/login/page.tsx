"use client";

import { FormEvent, useState, Suspense } from "react";
import Image from "next/image";
import { useRouter, useSearchParams } from "next/navigation";
import { siteConfig } from "@/config/site";

function LoginForm() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const nextPath = searchParams.get("next") || "/";
  const configError = searchParams.get("error") === "not_configured";

  const [password, setPassword] = useState("");
  const [error, setError] = useState(
    configError ? "站点未配置访问密码，请在部署环境设置 SITE_PASSWORD" : ""
  );
  const [loading, setLoading] = useState(false);

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    if (!password || loading) return;
    setLoading(true);
    setError("");
    try {
      const res = await fetch("/api/auth/login", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ password }),
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data.ok) {
        setError(data.error || "密码错误");
        setLoading(false);
        return;
      }
      router.replace(nextPath.startsWith("/") ? nextPath : "/");
      router.refresh();
    } catch {
      setError("网络错误，请重试");
      setLoading(false);
    }
  }

  return (
    <div className="relative min-h-screen flex flex-col items-center justify-center px-4">
      <div className="morphing-bg">
        <div className="orb orb-1" />
        <div className="orb orb-2" />
        <div className="orb orb-3" />
      </div>

      <div className="relative z-10 w-full max-w-sm">
        <div className="mb-8 flex flex-col items-center text-center">
          <Image
            src="/avatar.jpg"
            alt=""
            width={96}
            height={96}
            className="mb-4 h-20 w-20 rounded-full bg-[#7ec8e3] object-contain object-center ring-2 ring-white/15"
            priority
          />
          <h1 className="text-xl font-bold text-foreground">{siteConfig.name}</h1>
          <p className="mt-2 text-sm text-muted">请输入访问密码后继续</p>
        </div>

        <form
          onSubmit={onSubmit}
          className="glass-card iridescent-border space-y-4 p-6"
        >
          <label className="block">
            <span className="mb-1.5 block text-xs text-secondary">访问密码</span>
            <input
              type="password"
              name="password"
              autoComplete="current-password"
              autoFocus
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              disabled={configError}
              className="w-full rounded-xl border border-border-subtle bg-glass-2 px-4 py-3 text-sm text-foreground outline-none ring-accent/40 placeholder:text-muted focus:ring-2"
              placeholder="输入密码"
            />
          </label>

          {error && (
            <p className="text-xs text-red-400" role="alert">
              {error}
            </p>
          )}

          <button
            type="submit"
            disabled={loading || configError || !password}
            className="w-full rounded-xl bg-gradient-to-r from-[#7f77dd] to-[#d4537e] px-4 py-3 text-sm font-semibold text-white transition enabled:hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
          >
            {loading ? "验证中…" : "进入站点"}
          </button>
        </form>
      </div>
    </div>
  );
}

export default function LoginPage() {
  return (
    <Suspense
      fallback={
        <div className="flex min-h-screen items-center justify-center text-sm text-muted">
          加载中…
        </div>
      }
    >
      <LoginForm />
    </Suspense>
  );
}
