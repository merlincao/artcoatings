import os, re, sys, urllib.request, urllib.error, ssl, json

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

urls = ["https://www.novacolor.it/", "https://www.sanmarco.it/"]
# 证书关键词（公开来源中常见的认证标识）
CERT_KW = ["ISO 9001", "ISO 14001", "ISO 45001", "OHSAS", "CE ", "CE marking",
           "LEED", "Green Guard", "Greenguard", "EMAS", "UNI EN", "B Corp", "FSC"]

def fetch(url):
    req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0"})
    with urllib.request.urlopen(req, timeout=12, context=ctx) as r:
        return r.read().decode("utf-8", "ignore"), r.geturl()

for u in urls:
    print("====", u)
    try:
        html, final = fetch(u)
        print("final:", final, "len:", len(html))
        # og:image
        og = re.search(r'<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)', html, re.I)
        ogimg = og.group(1) if og else None
        # logo-ish
        logos = re.findall(r'<img[^>]+[^>]*(?:class|alt|src)=["\'][^"\']*(?:logo)[^"\']*["\'][^>]*src=["\']([^"\']+)', html, re.I)
        imgs = re.findall(r'<img[^>]+src=["\']([^"\']+)', html, re.I)
        # 证书出现
        certs = sorted({k for k in CERT_KW if k.lower() in html.lower()})
        print("og:image:", ogimg)
        print("logo imgs:", logos[:3])
        print("first imgs:", imgs[:6])
        print("cert keywords found:", certs)
    except Exception as e:
        print("ERR:", type(e).__name__, e)
