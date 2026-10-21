# -*- coding: utf-8 -*-
"""
从新闻原链接抓取配图（og:image / twitter:image / 正文首图）。

抓不到的条目会在 news_images.json 里标记 need_generate=true，
之后由 mcp 图像生成补齐 —— 不静默留空，也不拿占位图冒充原图。

用法：
  python tools/scrape_news_images.py
  python tools/scrape_news_images.py --proxy http://127.0.0.1:7890
"""
import argparse
import gzip
import io
import json
import os
import re
import ssl
import sys
import urllib.parse
import urllib.request
import zlib

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(HERE)
DATA = os.path.join(HERE, "data")
RAW = os.path.join(DATA, "news_images_raw")

UA = ("Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
      "(KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36")

# 明显不是新闻配图的图（站点 logo / 图标 / 空白占位）
BAD_RE = re.compile(r"(logo|favicon|icon|sprite|avatar|blank|spacer|pixel)", re.I)
# 有效图片格式的 magic bytes
MAGIC = {
    b"\xff\xd8\xff": "jpg",
    b"\x89PNG\r\n\x1a\n": "png",
    b"RIFF": "webp",
    b"GIF8": "gif",
    b"II*\x00": "tiff",
    b"MM\x00*": "tiff",
}


def build_opener(proxy):
    handlers = []
    if proxy:
        handlers.append(urllib.request.ProxyHandler({"http": proxy, "https": proxy}))
    ctx = ssl.create_default_context()
    ctx.check_hostname = False
    ctx.verify_mode = ssl.CERT_NONE
    handlers.append(urllib.request.HTTPSHandler(context=ctx))
    return urllib.request.build_opener(*handlers)


def fetch(opener, url, timeout=20):
    req = urllib.request.Request(url, headers={
        "User-Agent": UA,
        "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,image/*;q=0.8,*/*;q=0.5",
        "Accept-Language": "en-US,en;q=0.9",
        "Accept-Encoding": "gzip, deflate",
        "Connection": "close",
    })
    resp = opener.open(req, timeout=timeout)
    data = resp.read()
    enc = (resp.headers.get("Content-Encoding") or "").lower()
    if "gzip" in enc:
        try:
            data = gzip.GzipFile(fileobj=io.BytesIO(data)).read()
        except Exception:
            pass
    elif "deflate" in enc:
        try:
            data = zlib.decompress(data, -zlib.MAX_WBITS)
        except Exception:
            pass
    return resp.getcode(), resp.headers.get("Content-Type", ""), data


def pick_image(html, base):
    """按优先级挑一张图：og:image > twitter:image > 正文里较大的 <img>。"""
    cands = []

    for pat in (
        r'<meta[^>]+property=["\']og:image(?::url)?["\'][^>]+content=["\']([^"\']+)["\']',
        r'<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image(?::url)?["\']',
        r'<meta[^>]+name=["\']twitter:image(?::src)?["\'][^>]+content=["\']([^"\']+)["\']',
        r'<meta[^>]+content=["\']([^"\']+)["\'][^>]+name=["\']twitter:image(?::src)?["\']',
        r'<link[^>]+rel=["\']image_src["\'][^>]+href=["\']([^"\']+)["\']',
    ):
        for m in re.finditer(pat, html, re.I):
            u = urllib.parse.urljoin(base, m.group(1).strip())
            if u and not BAD_RE.search(u):
                cands.append(u)

    # 兜底：正文里的图片，带 width 的优先，挑尺寸大的
    for m in re.finditer(r'<img\b[^>]*src=["\']([^"\']+)["\'][^>]*>', html, re.I):
        tag = m.group(0)
        if BAD_RE.search(tag):
            continue
        w = re.search(r'width=["\']?(\d+)', tag)
        u = urllib.parse.urljoin(base, m.group(1).strip())
        if u:
            cands.append((int(w.group(1)) if w else 0, u))

    scored = []
    for c in cands:
        if isinstance(c, tuple):
            scored.append(c)
        else:
            scored.append((1000, c))
    scored.sort(key=lambda x: -x[0])
    return scored[0][1] if scored else None


def sniff(data):
    for sig, ext in MAGIC.items():
        if data[:len(sig)] == sig:
            return ext
    return None


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--proxy", default=os.environ.get("HTTPS_PROXY") or os.environ.get("HTTP_PROXY") or "")
    ap.add_argument("--force", action="store_true", help="已存在的图也重新抓")
    args = ap.parse_args()

    with open(os.path.join(DATA, "news_bbc.json"), "r", encoding="utf-8") as fh:
        items = json.load(fh)

    os.makedirs(RAW, exist_ok=True)
    opener = build_opener(args.proxy)
    out = []
    ok = miss = fail = 0

    for it in items:
        no = it["no"]
        slug = re.sub(r"[^a-z0-9]+", "-", it["title"].lower()).strip("-")[:70]
        dest = os.path.join(RAW, "%02d-%s" % (no, slug))
        rec = {"no": no, "title": it["title"], "url": it.get("source_url", ""), "file": None, "status": ""}

        existing = None
        for ext in ("jpg", "png", "webp", "gif"):
            p = dest + "." + ext
            if os.path.exists(p) and os.path.getsize(p) > 0:
                existing = p
                break
        if existing and not args.force:
            rec["file"] = existing
            rec["status"] = "cached"
            out.append(rec)
            ok += 1
            print("  [%02d] 已有 %s" % (no, os.path.basename(existing)))
            continue

        url = it.get("source_url", "")
        if not url:
            rec["status"] = "no-url"
            out.append(rec)
            miss += 1
            continue

        try:
            code, ctype, data = fetch(opener, url)
            if code >= 400 or not data:
                raise RuntimeError("HTTP %s" % code)
            if "html" not in ctype.lower() and not data[:512].lstrip()[:15].lower().startswith(b"<!doctype"):
                # 直接就是图片
                ext = sniff(data)
                if ext:
                    path = dest + "." + ext
                    with open(path, "wb") as fh:
                        fh.write(data)
                    rec["file"], rec["status"] = path, "direct"
                    out.append(rec)
                    ok += 1
                    print("  [%02d] 直取图片 %s" % (no, os.path.basename(path)))
                    continue
                raise RuntimeError("非 HTML 非图片")

            html = data.decode("utf-8", errors="ignore")
            img_url = pick_image(html, url)
            if not img_url:
                raise RuntimeError("页面无可用图片")

            code2, ctype2, img = fetch(opener, img_url)
            if code2 >= 400 or not img:
                raise RuntimeError("图片下载失败 %s" % code2)
            ext = sniff(img)
            if not ext:
                ext = "jpg"
            if len(img) < 3000:
                raise RuntimeError("图片过小(%d B)，判为占位" % len(img))
            path = dest + "." + ext
            with open(path, "wb") as fh:
                fh.write(img)
            rec["file"], rec["status"] = path, "og"
            rec["image_url"] = img_url
            ok += 1
            print("  [%02d] OK  %s" % (no, os.path.basename(path)))
        except Exception as e:
            rec["status"] = "fail: %s" % e
            miss += 1 if "无可用图片" in str(e) else 0
            fail += 1 if "无可用图片" not in str(e) else 0
            print("  [%02d] --  %s" % (no, e))
        out.append(rec)

    with open(os.path.join(DATA, "news_images.json"), "w", encoding="utf-8") as fh:
        json.dump(out, fh, ensure_ascii=False, indent=1)

    need = [r["no"] for r in out if not r.get("file")]
    print("\n抓到 %d 张 / 共 %d 条；失败 %d；需补生成 %d 条" % (ok, len(items), fail, len(need)))
    if need:
        print("需补图编号：" + ", ".join(str(n) for n in need))
    return 0


if __name__ == "__main__":
    sys.exit(main())
