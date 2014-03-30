-- phpMyAdmin SQL Dump
-- version 3.4.11.1deb2
-- http://www.phpmyadmin.net
--
-- Хост: localhost
-- Время создания: Мар 31 2014 г., 01:13
-- Версия сервера: 5.5.31
-- Версия PHP: 5.4.4-14+deb7u5

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- База данных: `mosaic`
--

-- --------------------------------------------------------

--
-- Структура таблицы `pictures`
--

CREATE TABLE IF NOT EXISTS `pictures` (
  `pid` int(9) unsigned NOT NULL AUTO_INCREMENT,
  `pic_name` varchar(20) DEFAULT NULL,
  `pic_path` varchar(120) DEFAULT NULL,
  `pic_turn` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `pic_disc` text,
  PRIMARY KEY (`pid`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='Таблица изображений' AUTO_INCREMENT=9 ;

--
-- Дамп данных таблицы `pictures`
--

INSERT INTO `pictures` (`pid`, `pic_name`, `pic_path`, `pic_turn`, `pic_disc`) VALUES
(1, '300', 'img/set/pic1.png', 1, NULL),
(2, 'Need for Speed', 'img/set/pic2.png', 1, NULL),
(3, 'Noah', 'img/set/pic3.png', 1, NULL),
(4, 'Spider-Man', 'img/set/pic4.png', 1, NULL),
(5, 'Transcendence', 'img/set/pic5.png', 1, NULL),
(6, 'X-Men', 'img/set/pic6.png', 1, NULL),
(7, 'Dragon', 'img/set/pic7.png', 1, NULL),
(8, 'Raid', 'img/set/pic8.png', 1, NULL);

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
