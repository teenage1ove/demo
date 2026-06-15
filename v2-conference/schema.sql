-- Конференции.РФ — база данных conference_rf
SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS `conference_rf` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `conference_rf`;

DROP TABLE IF EXISTS `reviews`;
DROP TABLE IF EXISTS `applications`;
DROP TABLE IF EXISTS `items`;
DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `login` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `fio` VARCHAR(200) NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `birth_date` DATE DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `category` VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `applications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `item_id` INT NOT NULL,
  `start_date` DATE NOT NULL,
  `payment_method` VARCHAR(50) NOT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'Новая',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `reviews` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `application_id` INT NOT NULL UNIQUE,
  `user_id` INT NOT NULL,
  `review_text` TEXT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `items` (`name`, `category`) VALUES
('Лекционная аудитория', 'Аудитория'),
('Малая аудитория', 'Аудитория'),
('Открытый коворкинг', 'Коворкинг'),
('Переговорный коворкинг', 'Коворкинг'),
('Кинозал', 'Кинозал');

-- тестовый пользователь: логин test12 / пароль test1234
INSERT INTO `users` (`login`, `password`, `fio`, `phone`, `email`) VALUES
('test12', '$2y$12$di3Sp90YCZywBqYGbqnMbOgPN1yCj243u.vSZdvdfraA2NRFzbsyC', 'Наумова Софья Михайловна', '79998567744', 'test1@mail.ru');
