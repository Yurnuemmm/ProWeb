-- Esquema de base de datos para WebOs (MySQL)
CREATE DATABASE IF NOT EXISTS `web_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `web_db`;

-- Tabla de usuarios (almacenada en MySQL)
CREATE TABLE IF NOT EXISTS `usuarios` (
    `id` VARCHAR(64) NOT NULL PRIMARY KEY,
    `nombre` VARCHAR(255) NOT NULL,
    `nacimiento` VARCHAR(50) NOT NULL,
    `educacion` VARCHAR(255) NOT NULL,
    `telefono` VARCHAR(50) NOT NULL,
    `horas` VARCHAR(50) NOT NULL,
    `email` VARCHAR(191) NOT NULL UNIQUE,
    `nickname` VARCHAR(100) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `createdAt` DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- NOTA: Las tarjetas se gestionan y guardan en formato JSON
-- en el archivo: api/data/tarjetas.json
