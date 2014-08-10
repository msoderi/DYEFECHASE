-- phpMyAdmin SQL Dump
-- version 2.8.2.4
-- http://www.phpmyadmin.net
-- 
-- Host: localhost:3306
-- Generato il: 10 Ago, 2014 at 12:45 AM
-- Versione MySQL: 5.5.19
-- Versione PHP: 5.2.6
-- 
-- Database: `synd`
-- 

-- --------------------------------------------------------

-- 
-- Struttura della tabella `admin__channels`
-- 

CREATE TABLE `admin__channels` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `channel` varchar(255) NOT NULL,
  `referer` varchar(255) NOT NULL,
  `LastModified` varchar(255) NOT NULL,
  `ContentLength` varchar(255) NOT NULL,
  `skip` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `channel` (`channel`)
) ENGINE=InnoDB AUTO_INCREMENT=335448 DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

-- 
-- Struttura della tabella `admin__channelsd`
-- 

CREATE TABLE `admin__channelsd` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `website` varchar(255) NOT NULL,
  `downloads` int(10) unsigned NOT NULL,
  `skipped` int(10) unsigned NOT NULL,
  `channels` int(10) unsigned NOT NULL,
  `exetime` int(10) unsigned NOT NULL,
  `datetime` datetime NOT NULL,
  `v` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13219 DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

-- 
-- Struttura della tabella `engine__interesting_links`
-- 

CREATE TABLE `engine__interesting_links` (
  `item` varchar(255) NOT NULL,
  PRIMARY KEY (`item`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

-- 
-- Struttura della tabella `engine__new_websites`
-- 

CREATE TABLE `engine__new_websites` (
  `item` varchar(255) NOT NULL,
  PRIMARY KEY (`item`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

-- 
-- Struttura della tabella `engine__pages`
-- 

CREATE TABLE `engine__pages` (
  `item` varchar(255) NOT NULL,
  PRIMARY KEY (`item`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

-- 
-- Struttura della tabella `engine__previous_level`
-- 

CREATE TABLE `engine__previous_level` (
  `item` varchar(255) NOT NULL,
  PRIMARY KEY (`item`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
