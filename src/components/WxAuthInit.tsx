"use client";

/** 已移除微信公众号强制关注；保留空组件避免遗留引用报错 */
export async function showWxAuth(): Promise<boolean> {
  return true;
}

export default function WxAuthInit() {
  return null;
}
