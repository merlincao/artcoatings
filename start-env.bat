@echo off
chcp 65001 >nul
REM ============================================================================
REM  artcoatings.org 本地创作环境 · 启动
REM
REM  依赖：复用 xzmcgeo/meizhous 的 PHP + MySQL 运行时（无需重复下载）
REM  启动：MySQL(3398) → PHP 内置服务器(8080)
REM  前台：http://localhost:8080/
REM  后台：http://localhost:8080/wp-admin/
REM  管理员：admin / Admin@2026!ac
REM ============================================================================

set "ROOT=%~dp0"
set "RUNTIME=C:\Users\cjx\WorkBuddy\2026-10-06-00-30-33\meizhous\env"
set "MYSQLD=%RUNTIME%\mysql\bin\mysqld.exe"
set "PHP=%RUNTIME%\php\php.exe"
set "MYINI=C:\Users\cjx\WorkBuddy\xzmcgeo\local\mysql\my.ini"

echo.
echo   artcoatings.org 本地创作环境
echo   ----------------------------------------

if not exist "%MYSQLD%" (
    echo   [错误] 找不到 MySQL：%MYSQLD%
    echo   请修改本脚本中的 RUNTIME 变量指向实际运行时目录。
    pause & exit /b 1
)
if not exist "%PHP%" (
    echo   [错误] 找不到 PHP：%PHP%
    pause & exit /b 1
)

REM ---- MySQL ----
netstat -ano | findstr "LISTENING" | findstr ":3398" >nul
if %errorlevel%==0 (
    echo   [跳过] MySQL 已在 3398 运行
) else (
    echo   [1/2] 启动 MySQL 8.0 ^(端口 3398^)...
    start "MySQL-3398" /MIN "%MYSQLD%" --defaults-file="%MYINI%" --console
    timeout /t 8 /nobreak >nul
)

REM ---- PHP 内置服务器 ----
netstat -ano | findstr "LISTENING" | findstr ":8080" >nul
if %errorlevel%==0 (
    echo   [跳过] PHP 服务器已在 8080 运行
) else (
    echo   [2/2] 启动 PHP 服务器 ^(端口 8080^)...
    start "PHP-8080" /MIN "%PHP%" -d memory_limit=512M -S localhost:8080 -t "%ROOT%site" "%ROOT%router.php"
    timeout /t 3 /nobreak >nul
)

echo.
echo   ----------------------------------------
echo     前台   http://localhost:8080/
echo     后台   http://localhost:8080/wp-admin/
echo     账号   admin
echo     密码   Admin@2026!ac
echo   ----------------------------------------
echo.
pause
