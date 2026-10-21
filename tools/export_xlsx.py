# -*- coding: utf-8 -*-
"""
Excel(20261021.xlsx) → companies.json 抽数脚本
把「品牌名单50家」结构化为导入用的 JSON，与 WP 导入脚本解耦。
用法：python tools/export_xlsx.py
"""
import json
import os
import re
import openpyxl

SRC = r"E:\Downloads\20261021.xlsx"
OUT = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "tools", "data", "companies.json")


def slugify(text):
    text = re.sub(r"[^A-Za-z0-9\s\-]", "", str(text)).strip().lower()
    text = re.sub(r"[\s]+", "-", text)
    return text or "company"


def main():
    wb = openpyxl.load_workbook(SRC, data_only=True)
    ws = wb["品牌名单50家"]
    rows = list(ws.iter_rows(values_only=True))
    header = rows[0]

    records = []
    for r in rows[1:]:
        if r[0] is None and r[1] is None:
            continue
        rec = {
            "seq": r[0],
            "brand": (r[1] or "").strip(),
            "company": (r[2] or "").strip(),
            "country": (r[3] or "").strip(),
            "website": (r[4] or "").strip(),
            "address": (r[5] or "").strip(),
            "phone": (r[6] or "").strip(),
            "email": (r[7] or "").strip(),
            "x_link": (r[8] or "").strip(),
            "products": (r[9] or "").strip(),
            "source": (r[10] or "").strip(),
            "notes": (r[11] or "").strip(),
        }
        rec["slug"] = slugify(rec["brand"])
        records.append(rec)

    os.makedirs(os.path.dirname(OUT), exist_ok=True)
    with open(OUT, "w", encoding="utf-8") as f:
        json.dump(records, f, ensure_ascii=False, indent=2)

    print("导出记录数:", len(records))
    print("首条:", records[0]["brand"], "|", records[0]["website"])
    print("输出:", OUT)


if __name__ == "__main__":
    main()
