@echo off
REM ============================================================================
REM artcoatings.org 上传脚本（Windows）
REM 作用：同步 html/ 到本地 git 仓库并推送到 GitHub（Cloudflare Pages 自动部署）
REM
REM 使用前请先设置仓库路径，例如：
REM   set ARTCOATINGS_REPO=C:\Users\cjx\artcoatings
REM
REM 鉴权：PAT 写入 tools\.github_token（已 gitignore，不入库）；GitHub 不支持密码推送。
REM ============================================================================
setlocal
if "%ARTCOATINGS_REPO%"=="" (
  echo [错误] 请先设置 ARTCOATINGS_REPO 指向本地 git 仓库目录。
  echo 例如：set ARTCOATINGS_REPO=C:\Users\cjx\artcoatings
  exit /b 1
)
bash "%~dp0deploy.sh"
endlocal
