#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
从各品牌官网抓取图片与公开证书信息，下载到本地，并产出 enriched JSON。
用法：
  python tools/scrape_assets.py            # 全部
  python tools/scrape_assets.py --limit 5  # 仅前 5 家（调试）

依赖：仅标准库（urllib / ssl / re / json）。会读取环境 HTTPS_PROXY/HTTP_PROXY。
产物：
  tools/data/images/<slug>/0.jpg ...       # 下载的图片（首张为特色图）
  tools/data/companies_enriched.json       # 在原 companies.json 基础上增加
                                            #   images: [相对路径...], certs: [...]
"""
import os, re, sys, json, ssl, argparse, shutil, urllib.request, urllib.error, urllib.parse

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SRC = os.path.join(ROOT, "tools", "data", "companies.json")
OUT = os.path.join(ROOT, "tools", "data", "companies_enriched.json")
IMG_DIR = os.path.join(ROOT, "tools", "data", "images")

CERT_KW = ["iso 9001", "iso 14001", "iso 45001", "ohsas 18001",
           "ce marking", "ce certified", "ce certification",
           "leed", "emas", "greenguard", "green guard", "fsc ", "b corp",
           "uni en", "en 15824", "en 1504"]

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

# 代理（若有）
proxy = os.environ.get("HTTPS_PROXY") or os.environ.get("HTTP_PROXY")
opener = None
if proxy:
    opener = urllib.request.build_opener(
        urllib.request.ProxyHandler({"http": proxy, "https": proxy}),
        urllib.request.HTTPSHandler(context=ctx),
        urllib.request.HTTPHandler(),
    )
else:
    opener = urllib.request.build_opener(urllib.request.HTTPSHandler(context=ctx))


def fetch(url, binary=False, timeout=12):
    req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0 (compatible; ArtCoatingsBot/1.0)"})
    with opener.open(req, timeout=timeout) as r:
        data = r.read() if binary else r.read().decode("utf-8", "ignore")
        ctype = r.headers.get("Content-Type", "")
        return data, ctype


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


CDN_SUFFIXES = (".storeden.net", ".cloudfront.net", ".akamaihd.net",
                ".cloudinary.com", ".imgix.net", ".amazonaws.com", ".cdn.")


def is_good_img(u, base):
    u = (u or "").strip()
    if not u or (" " in u) or re.search(r"[\t\r\n]", u):
        return False
    if u.startswith("data:image"):
        return False
    if re.search(r"\.(svg)(\?|$)", u, re.I):
        return False
    if "spinner" in u or "loading" in u or "icon" in u.lower():
        return False
    pu, pb = urllib.parse.urlparse(u), urllib.parse.urlparse(base)
    if pu.scheme not in ("http", "https") or not pu.netloc:
        return False
    if pu.netloc == pb.netloc:          # 同域优先
        return True
    if pu.netloc.endswith(CDN_SUFFIXES) or any(s in pu.netloc for s in CDN_SUFFIXES):
        return True
    return False


def scrape(website):
    out = {"featured": "", "gallery": [], "certs": []}
    try:
        html, _ = fetch(website, timeout=12)
    except Exception as e:
        print("  fetch err:", e)
        return out
    # og:image
    m = re.search(r'<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']', html, re.I)
    featured = abs_url(m.group(1), website) if m else ""

    imgs = []
    if featured and is_good_img(featured, website):
        imgs.append(featured)

    # 直接 / 懒加载 / srcset 图片
    for m in re.finditer(r'<img[^>]+\b(?:src|data-src|data-lazy-src|data-original)=["\']([^"\']+)["\']', html, re.I):
        u = abs_url(m.group(1), website)
        if is_good_img(u, website) and u not in imgs:
            imgs.append(u)
    for m in re.finditer(r'srcset=["\']([^"\']+)["\']', html, re.I):
        for part in m.group(1).split(','):
            tok = part.strip().split()
            if not tok:
                continue
            u = abs_url(tok[0], website)
            if is_good_img(u, website) and u not in imgs:
                imgs.append(u)
            break  # 每个 srcset 取第一张即可
    # 内联 background-image
    for m in re.finditer(r'background-image\s*:\s*url\(\s*["\']?([^"\')]+)["\']?\s*\)', html, re.I):
        u = abs_url(m.group(1), website)
        if is_good_img(u, website) and u not in imgs:
            imgs.append(u)

    # 证书
    low = html.lower()
    out["certs"] = sorted({k.upper() for k in CERT_KW if k in low})
    out["featured"] = featured
    out["gallery"] = imgs[:3]
    return out


def download(img_url):
    try:
        data, ctype = fetch(img_url, binary=True, timeout=10)
        if not data or len(data) < 1500:  # 跳过极小（占位/图标）
            return None
        ext = ".jpg"
        if "png" in ctype:
            ext = ".png"
        elif "webp" in ctype:
            ext = ".webp"
        else:
            p = urllib.parse.urlparse(img_url).path
            e = os.path.splitext(p)[1].lower()
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

    # 非破坏性：覆盖写入，不再整体清空（避免误删与批量删除保护）
    os.makedirs(IMG_DIR, exist_ok=True)

    # --missing：保留已抓到的图片与证书
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
        sc = scrape(website)
        rec["certs"] = sc["certs"]
        if sc["gallery"]:
            d = os.path.join(IMG_DIR, slug)
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
        else:
            print("  no images found")

    with open(OUT, "w", encoding="utf-8") as f:
        json.dump(data, f, ensure_ascii=False, indent=2)
    print(f"\n完成 -> {OUT}（{len(data)} 家）")


if __name__ == "__main__":
    main()
