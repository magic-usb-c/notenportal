-- ============================================================
-- Einmalig von einem MySQL-Admin (root oder vergleichbar) ausführen:
--   sudo mysql notenportal < database/setup_migrations_table.sql
--
-- Erstellt die Laravel-Migrations-Tabelle und gibt np_web die nötigen Rechte.
-- ============================================================

CREATE TABLE IF NOT EXISTS `migrations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `migration` VARCHAR(255) NOT NULL,
  `batch` INT NOT NULL
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- np_web braucht CREATE/DROP/ALTER/INDEX für Migrations-Verwaltung
GRANT CREATE, DROP, ALTER, INDEX ON `notenportal`.`migrations` TO 'np_web'@'localhost';
FLUSH PRIVILEGES;
