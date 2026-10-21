-- ============================================================================
-- artcoatings.org 本地数据库初始化
-- 用 root（或具备 GRANT 权限的账号）执行：
--   mysql -h 127.0.0.1 -P 3398 -u root -p < tools/init-db.sql
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `artcoatings_wp`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'artcoatings'@'localhost'
  IDENTIFIED WITH mysql_native_password BY 'c310012';
CREATE USER IF NOT EXISTS 'artcoatings'@'127.0.0.1'
  IDENTIFIED WITH mysql_native_password BY 'c310012';

GRANT ALL PRIVILEGES ON `artcoatings_wp`.* TO 'artcoatings'@'localhost';
GRANT ALL PRIVILEGES ON `artcoatings_wp`.* TO 'artcoatings'@'127.0.0.1';

FLUSH PRIVILEGES;
