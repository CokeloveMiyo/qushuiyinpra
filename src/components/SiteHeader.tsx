"use client";

import Image from "next/image";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { siteConfig } from "@/config/site";

export default function SiteHeader() {
  const pathname = usePathname();
  if (pathname === "/login") return null;

  return (
    <header className="sticky top-0 z-50 w-full border-b border-border-subtle bg-[rgba(11,11,20,0.92)] backdrop-blur-md">
      {/* 三栏：左品牌 / 中导航 / 右 QQ，贴齐视口左右 */}
      <div className="grid h-16 w-full grid-cols-[1fr_auto_1fr] items-center gap-2 px-3 sm:px-5">
        {/* 最左侧：头像完整显示 + 站名 */}
        <Link
          href="/"
          className="flex min-w-0 items-center justify-self-start gap-2.5"
        >
          <span className="relative flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-full bg-[#7ec8e3] ring-2 ring-white/20">
            <Image
              src="/avatar.jpg"
              alt={siteConfig.name}
              width={96}
              height={96}
              className="h-full w-full object-contain object-center"
              priority
            />
          </span>
          <span className="truncate text-sm font-semibold tracking-wide text-foreground sm:text-base">
            {siteConfig.name}
          </span>
        </Link>

        {/* 正中间：视频解析（加大字号） */}
        <Link
          href="/"
          className="justify-self-center rounded-xl bg-emerald-500/15 px-5 py-2 text-base font-bold tracking-wide text-emerald-400 ring-1 ring-emerald-500/40 transition hover:bg-emerald-500/25 sm:px-6 sm:text-lg"
        >
          视频解析
        </Link>

        {/* 右侧显眼 QQ 群入口 */}
        <div className="justify-self-end">
          <a
            href={siteConfig.qqGroupUrl}
            target="_blank"
            rel="noopener noreferrer"
            title="联系我"
            className="inline-flex items-center gap-2 rounded-full bg-[#12B7F5] px-3 py-2 text-sm font-semibold text-white shadow-[0_0_20px_rgba(18,183,245,0.35)] transition hover:brightness-110 sm:px-4"
          >
            <Image
              src="/logos/qq.svg"
              alt=""
              width={20}
              height={20}
              className="h-5 w-5 brightness-0 invert"
              unoptimized
            />
            <span className="hidden sm:inline">联系我</span>
            <span className="sm:hidden">联系</span>
          </a>
        </div>
      </div>
    </header>
  );
}
