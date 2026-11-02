<?php
$DB_HOST='localhost'; $DB_NAME='forklift'; $DB_USER='root'; $DB_PASS='';

try {
  $pdo = new PDO("mysql:host=$DB_HOST;charset=utf8mb4", $DB_USER, $DB_PASS,
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);

  $pdo->exec("CREATE DATABASE IF NOT EXISTS forklift
              CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
  $pdo->exec("USE forklift");

  // טבלת משתמשים
  $pdo->exec("CREATE TABLE IF NOT EXISTS users (
      id INT AUTO_INCREMENT PRIMARY KEY,
      username VARCHAR(50) NOT NULL UNIQUE,
      password_hash VARCHAR(255) NOT NULL,
      role ENUM('client','employee','admin') NOT NULL DEFAULT 'client',
      tz VARCHAR(15) DEFAULT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

  // טבלת מוצרים
  $pdo->exec("CREATE TABLE IF NOT EXISTS products (
      id INT AUTO_INCREMENT PRIMARY KEY,
      type ENUM('forklifts','pallets','parts','accessories') NOT NULL,
      name VARCHAR(255) NOT NULL,
      model VARCHAR(255) DEFAULT NULL,
      price DECIMAL(12,2) DEFAULT NULL,
      description TEXT DEFAULT NULL,
      image_path VARCHAR(255) DEFAULT NULL,
      active TINYINT(1) NOT NULL DEFAULT 1,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
    )");

  // --- בדיקה והוספת עמודות חדשות אם חסרות ---
  $columns = $pdo->query("SHOW COLUMNS FROM products")->fetchAll(PDO::FETCH_COLUMN);

  $toAdd = [
    "lift_capacity VARCHAR(50) DEFAULT NULL",
    "mast_length VARCHAR(50) DEFAULT NULL",
    "engine_type ENUM('electric','diesel','turbo_diesel') DEFAULT NULL",
    "`condition` ENUM('new','used') DEFAULT NULL",
    "compatible_models VARCHAR(255) DEFAULT NULL",
    "subtype ENUM('electric','manual','semi') DEFAULT NULL",
    "purpose VARCHAR(255) DEFAULT NULL"
  ];

  foreach ($toAdd as $colDef) {
    $colName = explode(" ", $colDef)[0];
    if (!in_array(str_replace("`", "", $colName), $columns)) {
      $pdo->exec("ALTER TABLE products ADD COLUMN $colDef");
    }
  }

  // טבלת קריאות שירות
  $pdo->exec("CREATE TABLE IF NOT EXISTS service_tickets (
      id INT AUTO_INCREMENT PRIMARY KEY,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      name VARCHAR(120) NULL,
      phone VARCHAR(40) NULL,
      email VARCHAR(180) NULL,
      company VARCHAR(180) NULL,
      issue_text TEXT NULL,
      category VARCHAR(60) NULL,
      priority VARCHAR(20) NULL DEFAULT 'normal',
      location VARCHAR(255) NULL,
      status VARCHAR(30) NOT NULL DEFAULT 'new',
      source VARCHAR(30) NOT NULL DEFAULT 'manual'
    )");

  echo '✅ OK: database & tables updated';
} catch (Throwable $e) {
  http_response_code(500);
  echo '❌ ERROR: '.$e->getMessage();
}
