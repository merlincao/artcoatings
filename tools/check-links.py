#!/usr/bin/env python
# -*- coding: utf-8 -*-
"""
静态站导出后体检：把 html/ 当成**站点根目录**，逐一校验所有本地引用是否真的存在。

设计前提（用户明确要求）：
    html 文件夹就是网站的根目录，上传后线上不存在 /html/ 这一层。
    因此页面里的引用必须是相对路径（wp-content/...、../wp-content/...），
    绝不能残留 /html/ 或 http://127.0.0.1:8080 这类绝对地址。

检查项：
    1. 页面 <link>/<script>/<img>/<a> 中的本地引用在磁盘上是否存在
    2. CSS 文件内 url(...) 引用的资源是否存在
    3. 是否残留绝对地址 / 本地调试域名 / /html/ 前缀   ← 本次要抓的重点
    4. 是否有资源文件大小为 0（v1 时代静默写失败的典型后遗症）

用法：
    python tools/check-links.py
退出码：0 = 全部通过；1 = 存在问题
"""
import os
import re
import sys
from urllib.parse import unquote

ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'html')
ROOT = os.path.abspath(ROOT)

# 出现过就说明导出路径处理有漏网之鱼
BAD_PREFIXES = (
    '/html/',
    'http://127.0.0.1',
    'https://127.0.0.1',
    'http://localhost',
    'https://localhost',
    'http://artcoatings.org',
    'https://artcoatings.org',
)

ATTR_RE = re.compile(
    r'''(?:href|src|srcset)\s*=\s*['"]([^'"]+)['"]''',
    re.IGNORECASE,
)
CSS_URL_RE = re.compile(r'''url\(\s*['"]?([^'")]+)['"]?\s*\)''', re.IGNORECASE)

# WordPress 动态端点：静态站上天然不存在（没有 PHP 运行时），
# 单独归类，不计入断链，否则会淹没真正要修的归档页缺失。
DYNAMIC_PREFIXES = (
    'wp-json',
    'wp-admin',
    'wp-login',
    'xmlrpc.php',
    'wp-cron.php',
    '/feed',
    'feed/',
    'comments/feed',
    '?p=',
)

problems = []          # 断链 / 缺失资源
absolute_hits = []     # 残留绝对地址
empty_files = []       # 0 字节资源
dynamic_skips = set()  # 被识别为动态端点而跳过
zh_hits = []           # 页面里出现的中文
non_webp = []          # 非 webp 的图片


def is_dynamic(ref):
    r = clean(ref).lstrip('./')
    r = unquote(r).rstrip('/')
    # feed 既可能出现在中间（.../feed/）也可能是路径末段（.../feed），
    # 只看 '/feed/' 会漏掉归档页自指的 feed 尾链。
    if r == 'feed' or r.endswith('/feed'):
        return True
    for p in DYNAMIC_PREFIXES:
        if r == p.rstrip('/') or r.startswith(p) or ('/' + p) in r:
            return True
    return False


def is_external(u):
    u = u.strip()
    return (
        u.startswith('http://')
        or u.startswith('https://')
        or u.startswith('//')
        or u.startswith('data:')
        or u.startswith('mailto:')
        or u.startswith('tel:')
        or u.startswith('#')
    )


def clean(u):
    """去掉查询串、锚点、srcset 的尺寸描述符。"""
    u = u.strip()
    u = re.split(r'[?#]', u, maxsplit=1)[0]
    u = u.split(' ')[0]          # srcset: "img.png 2x" -> img.png
    return u.strip()


def resolve(base_html, ref):
    """把页面里的相对引用解析成磁盘绝对路径。"""
    ref = clean(ref)
    if not ref:
        return None
    if ref.startswith('/'):
        return os.path.join(ROOT, ref.lstrip('/'))
    return os.path.normpath(os.path.join(os.path.dirname(base_html), ref))


def check_file(path, origin, ref):
    if path is None:
        return
    if not os.path.exists(path):
        problems.append((origin, ref, '文件不存在'))
        return
    if os.path.isdir(path):
        # 目录型引用（如 /company/xxx/）在静态站里应由 index.html 承接
        if not os.path.exists(os.path.join(path, 'index.html')):
            problems.append((origin, ref, '目录无 index.html'))
        return
    if os.path.getsize(path) == 0:
        empty_files.append(path)


def scan_html(path):
    rel = os.path.relpath(path, ROOT)
    try:
        with open(path, 'r', encoding='utf-8', errors='ignore') as f:
            html = f.read()
    except OSError:
        return

    # 3. 残留绝对地址
    for bad in BAD_PREFIXES:
        if bad in html:
            absolute_hits.append((rel, bad))

    # 4. 中文残留：只查「可见文本」，跳过 <script>/<style>
    #    （语言包里存有法/意/德/葡/希腊语译文，那些不是中文，不参与判定）
    visible = re.sub(r'<(script|style)\b.*?</\1>', ' ', html, flags=re.S | re.I)
    visible = re.sub(r'<[^>]+>', ' ', visible)
    for m in re.finditer(r'[\u4e00-\u9fff\u3000-\u303f\uff00-\uffef]{2,}', visible):
        zh_hits.append((rel, m.group(0)))

    for m in ATTR_RE.finditer(html):
        ref = m.group(1)
        if is_external(ref):
            continue
        r = clean(ref)
        if not r:
            continue
        if is_dynamic(r):
            dynamic_skips.add(r)
            continue
        check_file(resolve(path, r), rel, r)


def scan_css(path):
    rel = os.path.relpath(path, ROOT)
    try:
        with open(path, 'r', encoding='utf-8', errors='ignore') as f:
            css = f.read()
    except OSError:
        return
    for m in CSS_URL_RE.finditer(css):
        u = clean(m.group(1))
        if is_external(u) or not u:
            continue
        check_file(resolve(path, u), rel, u)


def main():
    if not os.path.isdir(ROOT):
        print('找不到输出目录: %s' % ROOT)
        return 1

    pages = 0
    assets = 0
    for dirpath, dirnames, filenames in os.walk(ROOT):
        for fn in filenames:
            p = os.path.join(dirpath, fn)
            if fn == 'index.html':
                pages += 1
                scan_html(p)
            elif fn.lower().endswith('.css'):
                assets += 1
                scan_css(p)
            else:
                assets += 1
                # 图片一律 webp（长期规则）
                if re.search(r'\.(png|jpe?g|gif|avif|svg)$', fn, re.I):
                    non_webp.append(os.path.relpath(p, ROOT))
                if os.path.getsize(p) == 0:
                    empty_files.append(p)

    print('=' * 60)
    print('静态站体检（html/ 视为站点根目录）')
    print('=' * 60)
    print('页面: %d    资源: %d' % (pages, assets))
    print()

    print('[1] 本地引用校验')
    if problems:
        print('    [MISS] 发现 %d 处断链:' % len(problems))
        seen = set()
        for origin, ref, why in problems:
            key = (ref, why)
            if key in seen:
                continue
            seen.add(key)
            print('        %-34s <- %s  (%s)' % (ref[:34], origin, why))
        if len(problems) > len(seen):
            print('        ... 另有 %d 处同类引用重复出现' % (len(problems) - len(seen)))
    else:
        print('    [OK] 全部本地引用均可解析到磁盘文件')
    print()

    if dynamic_skips:
        print('    （已忽略 %d 类 WordPress 动态端点：wp-json / feed / xmlrpc / wp-admin 等，'
              '静态站无 PHP 运行时，天然不存在）' % len(dynamic_skips))
    print()

    print('[2] 绝对地址 / 调试域名残留')
    if absolute_hits:
        print('    [MISS] 发现 %d 处:' % len(absolute_hits))
        for rel, bad in absolute_hits[:20]:
            print('        %-40s 含 %s' % (rel, bad))
    else:
        print('    [OK] 无 /html/、127.0.0.1、localhost 等残留')
    print()

    print('[3] 零字节资源')
    if empty_files:
        print('    [MISS] %d 个:' % len(empty_files))
        for p in empty_files[:20]:
            print('        %s' % os.path.relpath(p, ROOT))
    else:
        print('    [OK] 无空文件')
    print()

    print('[4] 页面中文残留（站点须全英文）')
    print('    [MISS] %d 处:' % len(zh_hits) if zh_hits else '    [OK] 无中文')
    for rel, s in zh_hits[:15]:
        print('        %-44s %s' % (rel, s))
    print()

    print('[5] 图片格式（规则：一律 webp）')
    if non_webp:
        print('    [MISS] %d 个非 webp 图片:' % len(non_webp))
        for p in non_webp[:15]:
            print('        %s' % p)
    else:
        print('    [OK] 全部为 webp')
    print()

    ok = (not problems and not absolute_hits
          and not empty_files and not zh_hits and not non_webp)
    print('=' * 60)
    print('总判定: %s' % ('[OK] 通过' if ok else '[MISS] 存在问题'))
    print('=' * 60)
    return 0 if ok else 1


if __name__ == '__main__':
    sys.exit(main())
