"use client";
import { useState, useEffect } from "react";
import VideoParserForm from "@/components/VideoParserForm";
import PlatformIcon from "@/components/PlatformIcon";
import TipCard from "@/components/TipCard";
import {
  BilibiliVideo,
  DouyinVideo,
  KuaishouVideo,
  WeiboVideo,
  XhsVideo,
  QsMusicVideo,
  PipigxVideo,
  PpxiaVideo,
  GenericParsedVideo,
} from "@/components/videos";
import { ApiResponse } from "@/types/api";
import { VIDEO_PLATFORMS, type VideoPlatformKey } from "@/config/video-platforms";
import { siteConfig } from "@/config/site";

const PLATFORM_NAMES = Object.values(VIDEO_PLATFORMS).map((p) => p.name);

const STEPS = [
  {
    title: "复制分享链接",
    desc: "在抖音、快手、B站等 App 内点「分享」，复制链接（支持短链与带文字分享文案）。",
  },
  {
    title: "粘贴到解析框",
    desc: "回到本页把链接粘贴进输入框，可一次粘贴多个，每行一个。",
  },
  {
    title: "预览并保存",
    desc: "点击「开始解析」，自动识别平台，结果可在线预览并保存到本地。",
  },
];

function renderPlatformResult(result: ApiResponse) {
  switch (result.platform) {
    case "bilibili":
      return <BilibiliVideo data={result} />;
    case "douyin":
      return <DouyinVideo data={result} />;
    case "kuaishou":
      return <KuaishouVideo data={result} />;
    case "weibo":
      return <WeiboVideo data={result} />;
    case "xhs":
      return <XhsVideo data={result} />;
    case "qsmusic":
      return <QsMusicVideo data={result} />;
    case "pipigx":
      return <PipigxVideo data={result} />;
    case "ppxia":
      return <PpxiaVideo data={result} />;
    default:
      return <GenericParsedVideo data={result} />;
  }
}

export default function Home() {
  const [result, setResult] = useState<ApiResponse | null>(null);
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);
  const [mounted, setMounted] = useState(false);
  const [pickedPlatform, setPickedPlatform] = useState<VideoPlatformKey | "auto" | null>(null);
  const [pickNonce, setPickNonce] = useState(0);
  const [activePlatform, setActivePlatform] = useState<VideoPlatformKey | "auto">("auto");

  useEffect(() => {
    setMounted(true);
  }, []);

  const handleParseResult = (data: ApiResponse | null, errorMsg: string = "") => {
    setResult(data);
    setError(errorMsg);
  };

  return (
    <>
      <div className="morphing-bg">
        <div className="orb orb-1" />
        <div className="orb orb-2" />
        <div className="orb orb-3" />
      </div>

      <div className="relative" style={{ zIndex: 1 }}>
        <div className="mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-12 lg:px-8">
          <header className="mb-8 text-center reveal">
            <h1 className="mb-2 text-3xl font-bold glow-text sm:text-4xl">
              <span className="gradient-text">{siteConfig.name}</span>
            </h1>
            <p className="mx-auto max-w-md text-sm text-muted">
              支持 {PLATFORM_NAMES.length}+ 平台 · 粘贴链接即可解析预览下载
            </p>
            <div className="mt-4 flex flex-wrap justify-center gap-2 text-xs">
              <span className="rounded-full bg-glass-2 px-3 py-1 text-secondary">✓ 免安装</span>
              <span className="rounded-full bg-glass-2 px-3 py-1 text-secondary">✓ 无水印</span>
              <span className="rounded-full bg-glass-2 px-3 py-1 text-secondary">✓ 多平台</span>
            </div>
          </header>

          <nav
            className="mb-8 grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-7"
            aria-label="选择平台进行解析"
          >
            <button
              type="button"
              onClick={() => {
                setPickedPlatform("auto");
                setPickNonce((n) => n + 1);
              }}
              aria-pressed={activePlatform === "auto"}
              title="自动识别平台并解析"
              className={`flex min-w-0 cursor-pointer items-center justify-center gap-1.5 rounded-xl border px-2 py-2 text-xs transition ${
                activePlatform === "auto"
                  ? "border-accent/60 bg-accent/10 font-medium text-foreground"
                  : "border-glass-2 bg-glass-2 text-secondary hover:-translate-y-0.5 hover:bg-glass-3 hover:text-foreground"
              }`}
            >
              <span className="relative flex h-2 w-2 shrink-0">
                <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75" />
                <span className="relative inline-flex h-2 w-2 rounded-full bg-emerald-500" />
              </span>
              <span className="truncate">自动识别</span>
            </button>

            {Object.entries(VIDEO_PLATFORMS).map(([key, p]) => {
              const k = key as VideoPlatformKey;
              const active = activePlatform === k;
              const label = p.name.replace(" (Twitter)", "");
              return (
                <a
                  key={key}
                  href={`/platform/${key}`}
                  onClick={(e) => {
                    e.preventDefault();
                    setPickedPlatform(k);
                    setPickNonce((n) => n + 1);
                  }}
                  title={`解析${p.name}`}
                  aria-pressed={active}
                  className={`flex min-w-0 cursor-pointer items-center justify-center gap-1.5 rounded-xl border px-2 py-2 text-xs transition ${
                    active
                      ? "border-accent/60 bg-accent/10 font-medium text-foreground"
                      : "border-glass-2 bg-glass-2 text-secondary hover:-translate-y-0.5 hover:bg-glass-3 hover:text-foreground"
                  }`}
                >
                  <PlatformIcon platform={k} size={16} />
                  <span className="truncate">{label}</span>
                </a>
              );
            })}
          </nav>

          <div className="mx-auto max-w-3xl">
            <div className={`reveal reveal-delay-2 ${mounted ? "opacity-100" : "opacity-0"}`}>
              <VideoParserForm
                onResult={handleParseResult}
                setLoading={setLoading}
                loading={loading}
                pickedPlatform={pickedPlatform}
                pickNonce={pickNonce}
                onPlatformChange={setActivePlatform}
              />
            </div>

            {error && (
              <div className="reveal mt-8 max-w-3xl">
                <div className="glass-card iridescent-border border-l-4 border-l-red-500 p-6">
                  <div className="flex items-start gap-4">
                    <div className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-red-500/20">
                      <svg
                        className="h-5 w-5 text-red-500"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        strokeWidth={2}
                      >
                        <path
                          strokeLinecap="round"
                          strokeLinejoin="round"
                          d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"
                        />
                      </svg>
                    </div>
                    <div className="flex-1">
                      <h3 className="mb-1 font-semibold text-red-400">解析失败</h3>
                      <p className="text-sm text-red-300/80">{error}</p>
                      <p className="mt-3 text-xs leading-relaxed text-muted">
                        请确认链接完整有效；部分内容受平台风控可能暂时无法解析，可换网络后重试。
                      </p>
                    </div>
                    <button
                      onClick={() => setError("")}
                      className="rounded-lg p-1 transition-colors hover:bg-red-500/10"
                    >
                      <svg
                        className="h-5 w-5 text-red-400"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        strokeWidth={2}
                      >
                        <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                      </svg>
                    </button>
                  </div>
                </div>
              </div>
            )}

            {result && (result.code === 1 || result.code === 200) && (
              <div className="reveal mt-8 max-w-3xl">
                <div className="glass-card iridescent-border">
                  <div className="border-b border-border-subtle bg-glass-2 px-6 py-4">
                    <div className="flex items-center justify-between">
                      <div className="flex items-center gap-3">
                        <div className="h-2 w-2 animate-pulse rounded-full bg-green-500" />
                        <span className="text-sm font-medium text-primary">解析成功</span>
                      </div>
                      <button
                        onClick={() => setResult(null)}
                        className="rounded-lg p-2 transition-colors hover:bg-glass-3 group"
                      >
                        <svg
                          className="h-5 w-5 text-muted transition-colors group-hover:text-primary"
                          fill="none"
                          stroke="currentColor"
                          viewBox="0 0 24 24"
                          strokeWidth={2}
                        >
                          <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                      </button>
                    </div>
                  </div>
                  <div className="p-6" style={{ touchAction: "manipulation" }}>
                    {renderPlatformResult(result)}
                  </div>
                </div>
              </div>
            )}

            <section className="glass-card iridescent-border mt-8 p-4 sm:p-6">
              <h2 className="text-lg font-bold text-foreground">三步解析下载</h2>
              <div className="mt-4 grid gap-4 sm:grid-cols-3">
                {STEPS.map((s, i) => (
                  <div
                    key={s.title}
                    className="flex items-start gap-3 rounded-xl border border-border-subtle bg-glass-2 p-4"
                  >
                    <div className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-accent/10 text-sm font-bold text-accent">
                      {i + 1}
                    </div>
                    <div>
                      <div className="text-sm font-semibold text-primary">{s.title}</div>
                      <div className="mt-1 text-xs leading-relaxed text-secondary">{s.desc}</div>
                    </div>
                  </div>
                ))}
              </div>
            </section>

            <TipCard />
          </div>
        </div>
      </div>
    </>
  );
}
