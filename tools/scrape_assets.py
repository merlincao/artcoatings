#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
从各品牌官网抓取图片与公开证书信息，下载到本地，并产出 enriched JSON。

v2 增强（针对 21 家首轮抓不到的站点）：
  - 更完整的浏览器请求头 + 域名变体重试（去 www / 加 www / http 回退），绕开部分 403
  - 图片来源扩到：og:image / twitter:image / itemprop=image / JSON-LD(logo,image) /
    link[rel=image_src] / img(src 与 data-* 懒加载) / srcset / 内联 background-image
  - 放宽 CDN 白名单：改为「排除明显的占位/追踪图」，不再因跨域而丢弃
  - 仍抓不到时，用 apple-touch-icon 兜底（180px PNG，卡片可用）

用法：
  python tools/scrape_assets.py            # 全部
  python tools/scrape_assets.py --missing  # 仅抓尚无图片的品牌（保留已有成果）
  python tools/scrape_assets.py --limit 5  # 仅前 5 家（调试）

依赖：仅标准库（urllib / ssl / re / json）。会读取环境 HTTPS_PROXY/HTTP_PROXY。
"""
import os, re, sys, json, ssl, argparse, urllib.request, urllib.error, urllib.parse

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SRC = os.path.join(ROOT, "tools", "data", "companies.json")
OUT = os.path.join(ROOT, "tools", "data", "companies_enriched.json")
IMG_DIR = os.path.join(ROOT, "tools", "data", "images")

CERT_KW = ["iso 9001", "iso 14001", "iso 45001", "ohsas 18001",
           "ce marking", "ce certified", "ce certification",
           "leed", "emas", "greenguard", "green guard", "fsc ", "b corp",
           "uni en", "en 15824", "en 1504"]

HDRS = {
    "User-Agent": ("Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
                   "(KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36"),
    "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8",
    "Accept-Language": "en-GB,en;q=0.9,it;q=0.8,fr;q=0.7",
    "Cache-Control": "no-cache",
}

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

proxy = os.environ.get("HTTPS_PROXY") or os.environ.get("HTTP_PROXY")
if proxy:
    opener = urllib.request.build_opener(
        urllib.request.ProxyHandler({"http": proxy, "https": proxy}),
        urllib.request.HTTPSHandler(context=ctx),
        urllib.request.HTTPHandler(),
    )
else:
    opener = urllib.request.build_opener(urllib.request.HTTPSHandler(context=ctx))


def fetch(url, binary=False, timeout=12):
    req = urllib.request.Request(url, headers=HDRS)
    with opener.open(req, timeout=timeout) as r:
        data = r.read() if binary else r.read().decode("utf-8", "ignore")
        return data, r.headers.get("Content-Type", "")


def abs_url(u, base):
    u = (u or "").strip()
    if re.match(r"^https?://", u, re.I):
        return u
    if u.startswith("//"):
        return "https:" + u
    pu = urllib.parse.urlparse(base)
    if u.startswith("/"):
        return f"{pu.scheme}://{pu.netloc}{u}"
    return f"{base.rstrip('/')}/{u.lstrip('/')}"


BAD_KW = ("spinner", "loading", "placeholder", "blank.", "1x1", "pixel.",
          "tracking", "spacer", "transparent")


def is_good_img(u, base):
    """宽松：只要是可下载的真实图片就行（跨域 CDN 不再丢弃）。"""
    u = (u or "").strip()
    if not u or re.search(r"[\s\t\r\n]", u):
        return False
    if u.startswith("data:") or u.startswith("blob:"):
        return False
    if re.search(r"\.(svg|ico)(\?|$)", u, re.I):
        return False
    low = u.lower()
    if any(k in low for k in BAD_KW):
        return False
    pu = urllib.parse.urlparse(u)
    if pu.scheme not in ("http", "https") or not pu.netloc:
        return False
    return True


def site_variants(website):
    """构造若干变体，逐个尝试，绕开部分站点的 403 / 跳转问题。"""
    vs = [website]
    pu = urllib.parse.urlparse(website)
    path = pu.path or "/"
    if pu.netloc.startswith("www."):
        vs.append(f"{pu.scheme}://{pu.netloc[4:]}{path}")
    else:
        vs.append(f"{pu.scheme}://www.{pu.netloc}{path}")
    if website.startswith("https://"):
        vs.append("http://" + website[len("https://"):])
    seen, out = set(), []
    for v in vs:
        if v not in seen:
            seen.add(v)
            out.append(v)
    return out


def fetch_html(website):
    last = None
    for v in site_variants(website):
        try:
            html, _ = fetch(v, timeout=14)
            if html and len(html) > 500:
                return html, v
        except Exception as e:
            last = e
    if last:
        print("  fetch err:", last)
    return None, None


def scrape(website):
    out = {"featured": "", "gallery": [], "certs": []}
    html, used = fetch_html(website)
    if not html:
        return out

    imgs = []

    def add(u):
        if not u:
            return
        u = abs_url(u, used or website)
        if is_good_img(u, website) and u not in imgs:
            imgs.append(u)

    # 1) 社交/结构化 meta（优先，通常是品牌主视觉）
    for pat in (
        r'<meta[^>]+property=["\']og:image(?::secure_url)?["\'][^>]+content=["\']([^"\']+)["\']',
        r'<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image(?::secure_url)?["\']',
        r'<meta[^>]+name=["\']twitter:image(?::src)?["\'][^>]+content=["\']([^"\']+)["\']',
        r'<meta[^>]+itemprop=["\']image["\'][^>]+content=["\']([^"\']+)["\']',
        r'<link[^>]+rel=["\']image_src["\'][^>]+href=["\']([^"\']+)["\']',
    ):
        m = re.search(pat, html, re.I)
        if m:
            add(m.group(1))
            break

    # 2) JSON-LD 里的 logo / image
    for m in re.finditer(r'"(?:logo|image|thumbnailUrl|contentUrl)"\s*:\s*"([^"]+)"', html):
        add(m.group(1))

    # 3) img（含各类懒加载属性）
    for m in re.finditer(
        r'<img[^>]+?\b(?:src|data-src|data-lazy-src|data-original|data-echo|data-image)=["\']([^"\']+)["\']',
        html, re.I):
        add(m.group(1))

    # 4) srcset（每个取第一个候选）
    for m in re.finditer(r'srcset=["\']([^"\']+)["\']', html, re.I):
        for part in m.group(1).split(","):
            tok = part.strip().split()
            if tok:
                add(tok[0])
                break

    # 5) 内联 background-image
    for m in re.finditer(r'background(?:-image)?\s*:\s*url\(\s*["\']?([^"\')]+)["\']?\s*\)', html, re.I):
        add(m.group(1))

    # 6) 兜底：apple-touch-icon（180px PNG）
    if not imgs:
        for m in re.finditer(
            r'<link[^>]+rel=["\']apple-touch-icon(?:-precomposed)?["\'][^>]+href=["\']([^"\']+)["\']',
            html, re.I):
            add(m.group(1))
            break

    low = html.lower()
    out["certs"] = sorted({k.upper() for k in CERT_KW if k in low})
    out["featured"] = imgs[0] if imgs else ""
    out["gallery"] = imgs[:3]
    return out


def download(img_url):
    try:
        data, ctype = fetch(img_url, binary=True, timeout=14)
        if not data or len(data) < 1200:
            return None
        ext = ".jpg"
        c = (ctype or "").lower()
        if "png" in c:
            ext = ".png"
        elif "webp" in c:
            ext = ".webp"
        else:
            e = os.path.splitext(urllib.parse.urlparse(img_url).path)[1].lower()
            if e in (".jpg", ".jpeg", ".png", ".webp"):
                ext = e
        return data, ext
    except Exception as e:
        print("  img err:", e)
        return None


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--limit", type=int, default=0)
    ap.add_argument("--missing", action="store_true", help="仅抓取尚未获得图片的品牌")
    args = ap.parse_args()

    with open(SRC, encoding="utf-8") as f:
        data = json.load(f)
    if args.limit:
        data = data[: args.limit]

    os.makedirs(IMG_DIR, exist_ok=True)

    enriched_prev = {}
    if args.missing and os.path.isfile(OUT):
        try:
            enriched_prev = {r.get("slug"): r for r in json.load(open(OUT, encoding="utf-8"))}
        except Exception:
            enriched_prev = {}

    for rec in data:
        brand = rec.get("brand", "")
        slug = rec.get("slug") or re.sub(r"[^a-z0-9]+", "-", brand.lower()).strip("-")
        website = rec.get("website", "")
        print(f"== {brand} ({website or 'no site'})")
        rec["images"] = []
        rec["certs"] = []
        if not website:
            continue
        if args.missing and slug in enriched_prev and enriched_prev[slug].get("images"):
            rec["images"] = enriched_prev[slug]["images"]
            rec["certs"] = enriched_prev[slug].get("certs", [])
            print("  已有图片，跳过")
            continue

        # --missing 时先确认磁盘上真的有文件
        d = os.path.join(IMG_DIR, slug)
        have_on_disk = os.path.isdir(d) and len(os.listdir(d)) > 0
        if have_on_disk:
            rec["images"] = [os.path.relpath(os.path.join(d, f), ROOT).replace("\\", "/")
                             for f in sorted(os.listdir(d))]
            rec["certs"] = (enriched_prev.get(slug) or {}).get("certs", [])
            print("  磁盘已有图片，跳过")
            continue

        sc = scrape(website)
        rec["certs"] = sc["certs"]
        if sc["gallery"]:
            os.makedirs(d, exist_ok=True)
            for i, u in enumerate(sc["gallery"]):
                res = download(u)
                if res:
                    body, ext = res
                    fn = os.path.join(d, f"{i}{ext}")
                    with open(fn, "wb") as g:
                        g.write(body)
                    rel = os.path.relpath(fn, ROOT).replace("\\", "/")
                    rec["images"].append(rel)
                    print(f"  saved {rel}")
        if not rec["images"]:
            print("  no images found")

    with open(OUT, "w", encoding="utf-8") as f:
        json.dump(data, f, ensure_ascii=False, indent=2)
    print(f"\n完成 -> {OUT}（{len(data)} 家）")


if __name__ == "__main__":
    main()
