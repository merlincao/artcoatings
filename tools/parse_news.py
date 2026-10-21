# -*- coding: utf-8 -*-
"""
把人工改写好的新闻稿（BBC 写作规范 / 第三方视角）解析成 JSON，供 import-news.php 导入。

输入：tools/data/news_bbc_*.txt（区块格式，见 README 注释）
输出：tools/data/news_bbc.json

区块格式：
    ### <序号>
    TITLE: ...
    CAT: ...
    SRC: ...
    URL: ...
    CONF: ...
    IMG: ...
    STAND: ...
    BODY:
    <p>...</p>
    ...
"""
import glob
import json
import os
import re
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
DATA = os.path.join(HERE, "data")


def parse_file(path):
    items = []
    cur = None
    field = None
    with open(path, "r", encoding="utf-8") as fh:
        for raw in fh:
            line = raw.rstrip("\n")
            if line.startswith("### "):
                if cur:
                    items.append(cur)
                cur = {"no": int(line[4:].strip()), "body": []}
                field = None
                continue
            if cur is None:
                continue
            m = re.match(r"^([A-Z]+):\s?(.*)$", line)
            if m:
                key, val = m.group(1), m.group(2)
                field = None
                if key == "TITLE":
                    cur["title"] = val
                elif key == "CAT":
                    cur["category"] = val
                elif key == "SRC":
                    cur["source"] = val
                elif key == "URL":
                    cur["source_url"] = val
                elif key == "CONF":
                    cur["confidence"] = val
                elif key == "IMG":
                    cur["image"] = val
                elif key == "STAND":
                    cur["standfirst"] = val
                elif key == "BODY":
                    field = "body"
                continue
            if field == "body":
                if line.strip():
                    cur["body"].append(line.strip())
    if cur:
        items.append(cur)

    for it in items:
        it["body"] = "\n".join(it["body"])
    return items


def main():
    files = sorted(glob.glob(os.path.join(DATA, "news_bbc_*.txt")))
    if not files:
        print("找不到 news_bbc_*.txt", file=sys.stderr)
        return 1

    all_items = []
    for f in files:
        got = parse_file(f)
        print("  %s -> %d 条" % (os.path.basename(f), len(got)))
        all_items.extend(got)

    required = ("title", "category", "source", "source_url", "confidence", "image", "standfirst", "body")
    bad = 0
    for it in all_items:
        missing = [k for k in required if not it.get(k)]
        if missing:
            print("  [缺字段] #%s %s -> %s" % (it.get("no"), it.get("title", "")[:40], ",".join(missing)), file=sys.stderr)
            bad += 1

    all_items.sort(key=lambda x: x["no"])
    out = os.path.join(DATA, "news_bbc.json")
    with open(out, "w", encoding="utf-8") as fh:
        json.dump(all_items, fh, ensure_ascii=False, indent=1)
    print("写出 %s，共 %d 条，缺字段 %d 条" % (out, len(all_items), bad))
    return 0 if bad == 0 else 2


if __name__ == "__main__":
    sys.exit(main())
