<?php
// עדכן פרטי גישה משלך
$DB_HOST = 'localhost';
$DB_NAME = 'forklift_fixed';
$DB_USER = 'root';
$DB_PASS = '';

$pdo = new PDO(
  "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
  $DB_USER,
  $DB_PASS,
  [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
