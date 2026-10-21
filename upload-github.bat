@echo off
REM ---------------------------------------------------------------
REM artcoatings.org - push html/ to GitHub
REM Cloudflare Pages publishes from the "html" directory automatically.
REM Usage: upload-github.bat
REM ---------------------------------------------------------------
setlocal
cd /d "%~dp0"

set REPO=merlincao/artcoatings
set BRANCH=main
set TOKENFILE=tools\.github_token

if not exist "%TOKENFILE%" goto notoken

set /p TOKEN=<"%TOKENFILE%"

echo [1/2] staging html/ ...
git add html
git add site\wp-content\themes\artcoatings
git add site\wp-content\plugins\artcoatings-core

echo [2/2] committing and pushing to %REPO% ...
git commit -m "chore: regenerate static site" 2>nul
if errorlevel 1 echo   no changes to commit, pushing anyway

git push https://merlincao:%TOKEN%@github.com/%REPO%.git %BRANCH%
if errorlevel 1 goto fail

echo done. Cloudflare Pages will publish shortly.
goto :eof

:notoken
echo [ERR] %TOKENFILE% not found.
echo Create it with a single line containing your GitHub PAT.
exit /b 1

:fail
echo [ERR] push failed. Check network/proxy and the PAT.
exit /b 1
