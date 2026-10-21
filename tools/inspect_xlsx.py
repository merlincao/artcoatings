import sys
from openpyxl import load_workbook

path = r"E:\Downloads\20261021.xlsx"
try:
    wb = load_workbook(path, data_only=True, read_only=False)
except Exception as e:
    print("LOAD_ERROR:", e)
    sys.exit(1)

for ws in wb.worksheets:
    print("=== SHEET:", ws.title, "dims:", ws.dimensions, "===")
    # header row (row 1)
    max_col = ws.max_column
    max_row = ws.max_row
    print("max_col:", max_col, "max_row:", max_row)
    headers = []
    for c in range(1, max_col + 1):
        v = ws.cell(row=1, column=c).value
        headers.append(v)
    print("HEADERS:", headers)
    # sample first data row
    if max_row >= 2:
        row2 = []
        for c in range(1, max_col + 1):
            v = ws.cell(row=2, column=c).value
            s = str(v)
            row2.append(s[:60])
        print("ROW2:", row2)
    # check for embedded images
    try:
        imgs = getattr(ws, "_images", [])
        print("EMBEDDED_IMAGES:", len(imgs))
    except Exception as e:
        print("IMG_CHECK_ERR:", e)

    # Scan all cells for image-like URLs / keywords in any column header
    img_hints = []
    for c in range(1, max_col + 1):
        h = str(ws.cell(row=1, column=c).value or "")
        if any(k in h.lower() for k in ["image", "img", "pic", "photo", "图", "证书", "cert", "qualif"]):
            img_hints.append((c, h))
    print("HEADER_HINTS(image/cert):", img_hints)
