-- phpMyAdmin SQL Dump
-- version 4.5.2
-- http://www.phpmyadmin.net
--
-- Хост: 127.0.0.1
-- Время создания: Апр 11 2016 г., 12:43
-- Версия сервера: 5.7.9
-- Версия PHP: 5.6.16

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `x`
--

-- --------------------------------------------------------

--
-- Структура таблицы `mp`
--

DROP TABLE IF EXISTS `mp`;
CREATE TABLE IF NOT EXISTS `mp` (
  `payid` int(11) NOT NULL,
  `pctransid` int(11) NOT NULL,
  `date` datetime NOT NULL,
  `status` int(11) NOT NULL,
  `amount` int(11) NOT NULL,
  `fee` int(11) NOT NULL,
  `annul_amount` int(11) NOT NULL,
  `phone` varchar(13) NOT NULL,
  `goods_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `compensation_operator` int(11) NOT NULL,
  `trading_concession` int(11) NOT NULL,
  `branch` int(11) NOT NULL,
  `source_payment` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
