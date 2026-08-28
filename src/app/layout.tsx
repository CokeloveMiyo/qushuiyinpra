import type { Metadata, Viewport } from "next";
import "./globals.css";
import SiteHeader from "@/components/SiteHeader";
import { siteConfig } from "@/config/site";

export const metadata: Metadata = {
  metadataBase: new URL(siteConfig.url),
  title: {
    default: `${siteConfig.name} - 短视频解析下载`,
    template: `%s - ${siteConfig.name}`,
  },
  description:
    "私人短视频解析工具，支持抖音、快手、B站、微博、小红书等平台，粘贴链接即可预览与下载。",
  keywords: [
    "视频解析",
    "短视频解析",
    "视频下载",
    "无水印视频下载",
    "去水印",
    "抖音解析",
    "B站解析",
    "小红书解析",
    siteConfig.name,
  ],
  manifest: "/manifest.webmanifest",
  authors: [{ name: siteConfig.name }],
  openGraph: {
    title: `${siteConfig.name} - 短视频解析下载`,
    description:
      "私人短视频解析工具，支持抖音、快手、B站、微博、小红书等平台，粘贴链接即可预览与下载。",
    url: siteConfig.url,
    siteName: siteConfig.name,
    type: "website",
    locale: "zh_CN",
    images: [{ url: "/avatar.jpg", width: 512, height: 512, alt: siteConfig.name }],
  },
  twitter: {
    card: "summary",
    title: `${siteConfig.name} - 短视频解析下载`,
    description:
      "私人短视频解析工具，支持抖音、快手、B站、微博、小红书等平台，粘贴链接即可预览与下载。",
    images: ["/avatar.jpg"],
  },
  robots: {
    index: false,
    follow: false,
  },
  alternates: {
    canonical: siteConfig.url,
  },
};

export const viewport: Viewport = {
  themeColor: "#0b0b14",
  width: "device-width",
  initialScale: 1,
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="zh-CN" className="scroll-smooth">
      <head>
        <link rel="icon" href="/avatar.jpg" />
        <link rel="apple-touch-icon" href="/avatar.jpg" />
      </head>
      <body className="antialiased min-h-screen flex flex-col noise-overlay">
        <SiteHeader />
        <main className="flex-1">{children}</main>
      </body>
    </html>
  );
}
