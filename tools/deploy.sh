#!/usr/bin/env bash
# ============================================================================
# artcoatings.org 发布脚本（同步 html/ → GitHub 并推送）
# 静态产物在 html/，由 Cloudflare Pages 以 html/ 为构建输出目录托管。
#
# 用法：
#   export ARTCOATINGS_REPO="/c/Users/cjx/artcoatings"   # 本地 git clone 目录
#   bash tools/deploy.sh
#
# 鉴权（GitHub 已不支持密码推送，必须用 Personal Access Token / PAT）：
#   1) 优先读取环境变量 GH_TOKEN；
#   2) 否则读取 tools/.github_token（已 gitignore，绝不入库）。
#   推送时通过 url.insteadOf 临时注入 token，不会写入任何配置文件。
# ============================================================================
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SRC="$ROOT/html"
REPO="${ARTCOATINGS_REPO:-}"

GH_OWNER="${GH_OWNER:-xzmcart}"
GH_REPO="${GH_REPO:-artcoatings}"
GH_TOKEN="${GH_TOKEN:-}"
if [ -z "$GH_TOKEN" ] && [ -f "$ROOT/tools/.github_token" ]; then
  GH_TOKEN="$(cat "$ROOT/tools/.github_token")"
fi

if [ -z "$REPO" ]; then
  echo "请先设置 ARTCOATINGS_REPO 指向本地 git 仓库目录。"
  exit 1
fi
if [ ! -d "$SRC" ]; then
  echo "未找到静态产物目录：$SRC"
  exit 1
fi

echo "[1/4] 同步 html/ → $REPO/html"
rm -rf "$REPO/html"
cp -a "$SRC" "$REPO/html"

echo "[2/4] 提交"
cd "$REPO"
git add -A
if git diff --cached --quiet; then
  echo "无变更，跳过提交。"
else
  git commit -m "publish: $(date +%F' '%H:%M)"
fi

echo "[3/4] 推送（使用 GitHub Token 鉴权）"
if [ -n "$GH_TOKEN" ]; then
  git -c "url.https://x-access-token:${GH_TOKEN}@github.com/.insteadOf=https://github.com/" push origin
else
  echo "未提供 GH_TOKEN，尝试普通 push（需本地已配置凭据）。"
  git push
fi

echo "[4/4] 完成。Cloudflare Pages 将自动部署。"
