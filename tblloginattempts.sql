-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 28, 2026 at 03:37 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_comfysci`
--

-- --------------------------------------------------------

--
-- Table structure for table `tblloginattempts`
--

CREATE TABLE `tblloginattempts` (
  `attemptID` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `ipAddress` varchar(45) NOT NULL,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `lockedUntil` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblloginattempts`
--

INSERT INTO `tblloginattempts` (`attemptID`, `email`, `ipAddress`, `attempts`, `lockedUntil`) VALUES
(4, 'test@gmail.com', '::1', 2, 0),
(5, 'test@gmail.com2', '::1', 1, 0),
(6, 'KielLuke@gmail.com', '::1', 5, 1795832811);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `tblloginattempts`
--
ALTER TABLE `tblloginattempts`
  ADD PRIMARY KEY (`attemptID`),
  ADD KEY `idx_email_ip` (`email`,`ipAddress`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `tblloginattempts`
--
ALTER TABLE `tblloginattempts`
  MODIFY `attemptID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
