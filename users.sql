-- phpMyAdmin SQL Dump
-- version 4.0.5
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Aug 07, 2013 at 01:01 AM
-- Server version: 5.5.29
-- PHP Version: 5.4.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

--
-- Database: `tech`
--

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` text NOT NULL,
  `password` text NOT NULL,
  `email` text NOT NULL,
  `fullname` text NOT NULL,
  `group` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=63 ;
