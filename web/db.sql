SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

CREATE TABLE IF NOT EXISTS `pastes` (
  `data` mediumtext NOT NULL,
  `secret` varchar(40) NOT NULL,
  PRIMARY KEY (`secret`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `shorts` (
  `key` varchar(255) NOT NULL,
  `type` tinyint(4) NOT NULL,
  `value` varchar(25000) NOT NULL,
  `expires` int(10) unsigned NOT NULL,
  `secret` varchar(40) NOT NULL,
  PRIMARY KEY (`key`),
  UNIQUE KEY `secret` (`secret`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
