import Image from "next/image";

/** 页内赞赏码（图一），替代原右侧悬浮公众号浮窗 */
export default function TipCard() {
  return (
    <aside className="mx-auto mt-10 max-w-xs text-center">
      <p className="mb-3 text-sm text-secondary">如果好用，扫码支持一下</p>
      <div className="overflow-hidden rounded-2xl border border-border-subtle bg-white p-2 shadow-lg">
        <Image
          src="/tip-qr.png"
          alt="赞赏码"
          width={360}
          height={420}
          className="mx-auto h-auto w-full rounded-xl"
        />
      </div>
    </aside>
  );
}
