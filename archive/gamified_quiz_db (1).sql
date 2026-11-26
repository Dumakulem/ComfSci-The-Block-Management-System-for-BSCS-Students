-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 25, 2026 at 08:09 AM
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
-- Database: `gamified_quiz_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `adminId` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`adminId`, `username`, `password`) VALUES
(1, 'admin', 'admin123');

-- --------------------------------------------------------

--
-- Table structure for table `answer`
--

CREATE TABLE `answer` (
  `answerId` bigint(20) NOT NULL,
  `questionId` bigint(20) NOT NULL,
  `answerText` text NOT NULL,
  `isCorrect` tinyint(1) NOT NULL DEFAULT 0,
  `score` int(11) DEFAULT 0,
  `xp_earned` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `gamequiz`
--

CREATE TABLE `gamequiz` (
  `gameId` bigint(20) NOT NULL,
  `userId` bigint(20) NOT NULL,
  `quizId` bigint(20) NOT NULL,
  `score` int(11) NOT NULL DEFAULT 0,
  `totalPoints` int(11) NOT NULL DEFAULT 0,
  `startedAt` datetime DEFAULT NULL,
  `endedAt` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `gamequiz`
--

INSERT INTO `gamequiz` (`gameId`, `userId`, `quizId`, `score`, `totalPoints`, `startedAt`, `endedAt`) VALUES
(2, 1, 6, 10, 10, '2026-11-23 22:58:31', '2026-11-23 22:58:31'),
(3, 1, 6, 0, 10, '2026-11-23 23:05:21', '2026-11-23 23:05:21'),
(4, 1, 6, 10, 10, '2026-11-23 23:08:36', '2026-11-23 23:08:36'),
(5, 1, 11, 10, 10, '2026-11-23 23:38:40', '2026-11-23 23:38:40'),
(6, 1, 12, 10, 10, '2026-11-23 23:43:34', '2026-11-23 23:43:34'),
(7, 1, 13, 10, 10, '2026-11-24 01:27:08', '2026-11-24 01:27:08'),
(8, 1, 13, 10, 10, '2026-11-24 14:34:54', '2026-11-24 14:34:54'),
(9, 1, 14, 10, 20, '2026-11-24 15:03:31', '2026-11-24 15:03:31'),
(10, 1, 13, 0, 10, '2026-11-24 15:16:23', '2026-11-24 15:16:23'),
(11, 1, 13, 10, 10, '2026-11-24 15:24:38', '2026-11-24 15:24:38'),
(12, 1, 12, 0, 10, '2026-11-24 15:24:53', '2026-11-24 15:24:53'),
(13, 1, 13, 0, 10, '2026-11-24 15:32:22', '2026-11-24 15:32:22'),
(14, 1, 13, 10, 10, '2026-11-24 16:01:28', '2026-11-24 16:01:28'),
(15, 1, 13, 10, 10, '2026-11-24 16:01:50', '2026-11-24 16:01:50'),
(16, 1, 13, 10, 10, '2026-11-24 16:02:27', '2026-11-24 16:02:27'),
(17, 1, 13, 0, 10, '2026-11-24 16:21:59', '2026-11-24 16:21:59'),
(18, 1, 12, 0, 10, '2026-11-24 16:22:43', '2026-11-24 16:22:43'),
(19, 1, 13, 0, 10, '2026-11-25 04:26:37', '2026-11-25 04:26:49'),
(20, 1, 9, 0, 20, '2026-11-25 04:27:17', '2026-11-25 04:27:36'),
(21, 1, 4, 30, 30, '2026-11-25 04:27:49', '2026-11-25 04:28:06'),
(22, 1, 13, 0, 10, '2026-11-25 04:51:43', '2026-11-25 04:51:58'),
(23, 1, 13, 0, 10, '2026-11-25 04:52:13', '2026-11-25 04:52:28'),
(24, 1, 13, 10, 10, '2026-11-25 04:52:56', '2026-11-25 04:53:02'),
(25, 1, 15, 10, 10, '2026-11-25 04:53:28', '2026-11-25 04:53:41'),
(26, 1, 15, 5, 10, '2026-11-25 05:20:07', '2026-11-25 05:20:18'),
(27, 1, 15, 0, 10, '2026-11-25 05:20:30', '2026-11-25 05:21:31'),
(28, 1, 15, 5, 10, '2026-11-25 04:40:17', '2026-11-25 04:40:25'),
(29, 1, 16, 30, 30, '2026-11-25 04:41:19', '2026-11-25 04:41:32'),
(30, 1, 12, 0, 10, '2026-11-25 04:46:33', '2026-11-25 04:46:44'),
(31, 1, 16, 10, 30, '2026-11-25 04:47:27', '2026-11-25 05:02:41'),
(32, 1, 17, 30, 30, '2026-11-25 05:29:07', '2026-11-25 05:29:28'),
(33, 1, 17, 30, 30, '2026-11-25 05:30:10', '2026-11-25 05:30:23'),
(34, 1, 17, 30, 30, '2026-11-25 05:32:25', '2026-11-25 05:32:39'),
(35, 1, 13, 10, 10, '2026-11-25 05:42:46', '2026-11-25 05:42:52'),
(36, 1, 17, 30, 30, '2026-11-25 05:44:33', '2026-11-25 05:44:44'),
(37, 1, 17, 0, 30, '2026-11-25 14:23:11', '2026-11-25 14:39:57'),
(38, 1, 17, 0, 30, '2026-11-25 07:41:56', '2026-11-25 07:42:36'),
(39, 1, 16, 30, 30, '2026-11-25 07:44:33', '2026-11-25 07:45:12'),
(40, 1, 17, 30, 30, '2026-11-25 07:46:36', '2026-11-25 07:46:48'),
(41, 1, 17, 30, 30, '2026-11-25 06:49:32', '2026-11-25 06:49:42'),
(42, 1, 17, 10, 30, '2026-11-25 06:50:13', '2026-11-25 06:50:51'),
(43, 1, 17, 0, 30, '2026-11-25 06:51:32', '2026-11-25 06:52:41'),
(44, 1, 17, 20, 30, '2026-11-25 06:55:26', '2026-11-25 06:55:38'),
(45, 1, 17, 20, 30, '2026-11-25 06:55:54', '2026-11-25 06:56:10'),
(46, 1, 17, 10, 30, '2026-11-25 07:02:54', '2026-11-25 07:03:19'),
(47, 1, 17, 10, 30, '2026-11-25 07:03:31', '2026-11-25 07:03:52'),
(48, 1, 17, 20, 30, '2026-11-25 07:07:36', '2026-11-25 07:07:53');

-- --------------------------------------------------------

--
-- Table structure for table `leader_board`
--

CREATE TABLE `leader_board` (
  `leaderboardId` bigint(20) NOT NULL,
  `userId` bigint(20) NOT NULL,
  `quizId` bigint(20) NOT NULL,
  `score` int(11) NOT NULL,
  `rank` int(11) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `question`
--

CREATE TABLE `question` (
  `questionId` bigint(20) NOT NULL,
  `quizId` bigint(20) NOT NULL,
  `questionText` text NOT NULL,
  `type` varchar(30) NOT NULL,
  `choices` text DEFAULT NULL,
  `correct_answer` text DEFAULT NULL,
  `points` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `question`
--

INSERT INTO `question` (`questionId`, `quizId`, `questionText`, `type`, `choices`, `correct_answer`, `points`) VALUES
(2, 6, 'huss huss', 'identification', NULL, NULL, 10),
(5, 8, 'ano ', 'identification', NULL, NULL, 10),
(6, 8, 'edi', 'multiple choice', NULL, NULL, 10),
(7, 9, 'huh', 'identification', NULL, NULL, 10),
(8, 9, 'oki', 'multiple choice', NULL, NULL, 10),
(9, 10, 'huh', 'multiple choice', NULL, NULL, 10),
(10, 10, 'ano', 'identification', NULL, NULL, 10),
(11, 11, 'eto', 'identification', NULL, NULL, 10),
(12, 12, 'okay', 'identification', NULL, NULL, 10),
(13, 13, 'a', 'multiple choice', '[\"b\",\"c\",\"d\",\"e\"]', 'b', 10),
(14, 14, 'hh', 'identification', NULL, 'hh', 10),
(15, 14, 'huss', 'multiple choice', '[\"huss\",\"ess\",\"iss\",\"oss\"]', 'huss', 10),
(16, 15, 'eyyy', 'identification', NULL, 'eyyy', 5),
(17, 15, 'sino ang god?', 'identification', NULL, 'hev abi', 5),
(18, 4, 'wait', 'identification', NULL, 'lang', 10),
(19, 4, 'wait', 'identification', NULL, 'lang', 10),
(20, 4, 'wait', 'identification', NULL, 'lang', 10),
(21, 7, 'Pwede Bang by?', 'identification', NULL, 'Hev Abi', 10),
(22, 7, 'Summer Crush by?', 'identification', NULL, 'Hellmerry', 10),
(23, 7, 'Shuloks', 'identification', NULL, 'ewan ko', 10),
(24, 16, 'hell', 'identification', NULL, 'yeah', 10),
(25, 16, 'okay', 'identification', NULL, 'okay', 10),
(26, 16, 'okay', 'identification', NULL, 'okay', 10),
(27, 17, 'sbdyw', 'identification', NULL, 'oki', 10),
(28, 17, 'mf nejfnwe', 'identification', NULL, 'oki', 10),
(29, 17, 'efneufe', 'identification', NULL, 'oki', 10);

-- --------------------------------------------------------

--
-- Table structure for table `quiz`
--

CREATE TABLE `quiz` (
  `quizId` bigint(20) NOT NULL,
  `title` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `createdBy` bigint(20) NOT NULL,
  `isPublic` tinyint(1) NOT NULL DEFAULT 1,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `quizType` varchar(50) NOT NULL,
  `status` varchar(20) DEFAULT 'draft',
  `timer` varchar(50) DEFAULT NULL,
  `points` int(11) DEFAULT NULL,
  `due_date` varchar(50) DEFAULT NULL,
  `question` text DEFAULT NULL,
  `quiz_type` varchar(50) DEFAULT NULL,
  `allow_retake` tinyint(1) DEFAULT 0,
  `xp_per_correct` int(11) NOT NULL DEFAULT 5,
  `show_answer_feedback` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quiz`
--

INSERT INTO `quiz` (`quizId`, `title`, `description`, `createdBy`, `isPublic`, `createdAt`, `quizType`, `status`, `timer`, `points`, `due_date`, `question`, `quiz_type`, `allow_retake`, `xp_per_correct`, `show_answer_feedback`) VALUES
(2, 'DSA - Midterms', NULL, 0, 1, '2026-11-23 10:34:58', '', 'published', '15', 10, 'Nov. 27, 2026', NULL, NULL, 0, 5, 0),
(4, 'Sample Lang', NULL, 0, 1, '2026-11-23 12:17:09', '', 'published', '30', 10, '2026-11-24', NULL, NULL, 1, 5, 0),
(6, 'idk', NULL, 0, 1, '2026-11-23 14:58:19', '', 'draft', NULL, NULL, NULL, NULL, NULL, 0, 5, 0),
(7, 'wala lang', NULL, 0, 1, '2026-11-23 15:26:56', '', 'published', '30', 10, '2026-11-24', NULL, NULL, 1, 5, 0),
(8, 'ulit', NULL, 0, 1, '2026-11-23 15:30:35', '', 'draft', NULL, NULL, NULL, NULL, NULL, 0, 5, 0),
(9, 'erm', NULL, 0, 1, '2026-11-23 15:32:22', '', 'published', NULL, NULL, NULL, NULL, NULL, 0, 5, 0),
(10, 'heh', NULL, 0, 1, '2026-11-23 15:34:15', '', 'published', NULL, NULL, NULL, NULL, NULL, 0, 5, 0),
(11, 'last', NULL, 0, 1, '2026-11-23 15:37:51', '', 'published', NULL, NULL, NULL, NULL, NULL, 0, 5, 0),
(12, 'yann', NULL, 0, 1, '2026-11-23 15:43:17', '', 'published', NULL, NULL, NULL, NULL, NULL, 1, 5, 0),
(13, 'he', NULL, 0, 1, '2026-11-23 17:06:04', '', 'published', '15', 10, '2026-11-23', NULL, NULL, 1, 5, 0),
(14, 'Sample Lang', NULL, 0, 1, '2026-11-24 07:03:03', '', 'published', '30', 10, '2026-11-24', NULL, NULL, 0, 5, 0),
(15, 'kunnwari', NULL, 0, 1, '2026-11-24 08:41:58', '', 'published', '30', 5, '2026-11-24', NULL, NULL, 1, 5, 0),
(16, 'okay', NULL, 0, 1, '2026-11-25 04:41:07', '', 'published', '30', 10, '2026-11-25', NULL, NULL, 1, 1, 0),
(17, 'review', NULL, 0, 1, '2026-11-25 05:28:57', '', 'published', '30', 10, '2026-11-25', NULL, NULL, 1, 5, 1);

-- --------------------------------------------------------

--
-- Table structure for table `quiz_memory_notes`
--

CREATE TABLE `quiz_memory_notes` (
  `noteId` int(11) NOT NULL,
  `userId` int(11) NOT NULL,
  `quizId` int(11) NOT NULL,
  `attempt_token` varchar(64) NOT NULL,
  `memory_note` text DEFAULT NULL,
  `locked_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quiz_memory_notes`
--

INSERT INTO `quiz_memory_notes` (`noteId`, `userId`, `quizId`, `attempt_token`, `memory_note`, `locked_at`, `created_at`) VALUES
(1, 1, 13, '2e05a7cfd68db871ef87a042be4bf79dac264cd66644c1137b8f3e526986cec3', 'uyeriufrkvhtlblykgyt,fmtrgtmrhbtghvrbf crebmrjgtkfhmebcermgvtkvhtebcktvhtk', '2026-11-24 15:13:02', '2026-11-24 15:13:02'),
(2, 1, 13, 'dd1b1c6270cfa693130e998c7c02e7eaedf28e69841db148b7ef78823bf8688d', '', '2026-11-24 15:24:12', '2026-11-24 15:24:12'),
(3, 1, 12, '3aedcb8626363b99cd87966a1f2a5aaa28887f0d3d66140aa50c3172cb25394c', '', '2026-11-24 15:24:48', '2026-11-24 15:24:48'),
(4, 1, 13, '73c6c4f885942818e8e52256ad2bf8483a7ccefc4779be7ac4050dd2ecf4004e', '', '2026-11-24 15:30:43', '2026-11-24 15:30:43'),
(5, 1, 13, 'e48eebaf148d33ee61a3692ccff876d168b308bc1af0e7d2c98b18ae8922c717', '', '2026-11-24 15:45:20', '2026-11-24 15:45:20'),
(6, 1, 13, 'a58a82ac2b758871d6ba3c90b3a942a69a9e8852dc2b31a02fdd567c155fe075', '', '2026-11-24 16:01:45', '2026-11-24 16:01:45'),
(7, 1, 13, '65c95a2d05178de3eebda1d1b35f519a0dcad22bf3d34b3ab23d190a16453ef8', '', '2026-11-24 16:02:18', '2026-11-24 16:02:18'),
(8, 1, 13, '7d690bdc881de7cfc715573a83010bca7c1932c360940ada976fd93d665160f0', '', '2026-11-24 16:21:18', '2026-11-24 16:21:18'),
(9, 1, 12, '828cb5bc77bec035bf0ea7172eded72854499f336dcb33eba77a76419e71e38c', '', '2026-11-24 16:22:09', '2026-11-24 16:22:09'),
(10, 1, 13, '018693ee67e5b3d4d237c7b8a86de8899425dd14d4a49695aa5790b66bbb0441', '', '2026-11-25 11:26:37', '2026-11-25 11:26:37'),
(11, 1, 9, '1e9673cb3bfe66956733c2bbbf38a9b20420e44875782b7c2cc08736d848ec90', '', '2026-11-25 11:27:17', '2026-11-25 11:27:17'),
(12, 1, 4, '2ef7918500893c84c4e60636947046fd8671bdb8bd832fb2ce0bedede358bc00', '', '2026-11-25 11:27:49', '2026-11-25 11:27:49'),
(13, 1, 13, 'b4bb41caa67866dff674ddd72bdede00fcd0fdf63ac46819c4df37d793fa4e43', '', '2026-11-25 11:51:43', '2026-11-25 11:51:43'),
(14, 1, 13, 'b5238fc58a350ce71e95b3821f91e27eaa386fb95ede7332838c3854c86d9af7', '', '2026-11-25 11:52:13', '2026-11-25 11:52:13'),
(15, 1, 13, '311d133cddca58831aa7436cb69cb533226d457bcb26ec5aada9be24af5f24bf', '', '2026-11-25 11:52:56', '2026-11-25 11:52:56'),
(16, 1, 15, '5ad9727821d867ca3d67eade4d16de8e5953b8a53ee800d3f0a7f7662683d570', '', '2026-11-25 11:53:28', '2026-11-25 11:53:28'),
(17, 1, 15, '87ed3a48ce98720043aa8a65bc47da36a49f57c1e27ee51edf9c754969184e2b', '', '2026-11-25 12:20:07', '2026-11-25 12:20:07'),
(18, 1, 15, '068964ee698b8b352f551698aa2016023dbcb9bd6855a09b9faf3ee33466e924', '', '2026-11-25 12:20:30', '2026-11-25 12:20:30'),
(19, 1, 15, '537cadeebde6196578f22dd1a25cf125f6910188409dc096d05916f4f487d7aa', '', '2026-11-25 12:40:17', '2026-11-25 12:40:17'),
(20, 1, 16, 'f419ffe57c8c8835fc169b2b506a929d5a3ea37770481072b0d01bc31551beb5', '', '2026-11-25 12:41:19', '2026-11-25 12:41:19'),
(21, 1, 12, '6a0bd445c61824f1f95add363334ec64dec5c9132ee696dd27d8f76db6b86e08', '', '2026-11-25 12:46:33', '2026-11-25 12:46:33'),
(22, 1, 12, '38dbee1c3685785c41bf83bdbccc290121ad2ea9d5e0c94e55250d35b3ed4a05', '', '2026-11-25 12:47:19', '2026-11-25 12:47:19'),
(23, 1, 16, 'ef7aa97c2c13d244562738caf87ca22c9f1f1cd7ae69b04f16b40a7befa0d462', '', '2026-11-25 12:47:27', '2026-11-25 12:47:27'),
(24, 1, 17, 'd6d20307ea8ef435c49c9004e5839a9b7261f7453329a62c621cb28620627cad', '', '2026-11-25 13:29:07', '2026-11-25 13:29:07'),
(25, 1, 17, 'ae926cb999c12e56c8adf6d484a3e4e1c0bdc527e2bd47411e4dbfaa66260d97', '', '2026-11-25 13:30:10', '2026-11-25 13:30:10'),
(26, 1, 17, '2e8bb3c155b391598d3a70407c90c247dbd9b3b3f6fe2571c61af91b1f262008', '', '2026-11-25 13:32:25', '2026-11-25 13:32:25'),
(27, 1, 13, '079fd74aef345fb104e168f95a60ba807bc9360f5d736df5a4cc0120d1cc8529', '', '2026-11-25 13:42:46', '2026-11-25 13:42:46'),
(28, 1, 17, '8b4db451905e9631ae72b6ba93704b50aa51c48d016b1e0a5c94b82511a77402', '', '2026-11-25 13:44:33', '2026-11-25 13:44:33'),
(29, 1, 17, 'ff373105a910f27311bc45f2cff6eb3aede937644c90ead5827255bddbaafcd9', '', '2026-11-25 14:23:11', '2026-11-25 14:23:11'),
(30, 1, 17, 'f493dfdb7aa82f38c3cb80d0918ed08f4c701602d344c3a58139026581ba21a7', '', '2026-11-25 14:41:56', '2026-11-25 14:41:56'),
(31, 1, 16, '8c4453e65b0d1815024a89d77a15437c6d55321d340aaebc2a61c08abfdecd5e', '', '2026-11-25 14:44:33', '2026-11-25 14:44:33'),
(32, 1, 17, '61953f3220f3e4c87328403a11f5fdfada9ba662a20d45c288abf2c3e211fecc', '', '2026-11-25 14:46:36', '2026-11-25 14:46:36'),
(33, 1, 17, 'b124078e8ee99166fd79b6aabdc0d548c14a8852a31d1b786e9bb5c9551b5568', '', '2026-11-25 14:49:32', '2026-11-25 14:49:32'),
(34, 1, 17, 'a437880334c980a54f15fbc7a8c9070ef403c3a9e69c70adadd7f653bff74743', '', '2026-11-25 14:50:13', '2026-11-25 14:50:13'),
(35, 1, 17, 'be696d6027400e4eb19fc0b33dd93ca67e9a81837263d4eb389925755be94147', '', '2026-11-25 14:51:32', '2026-11-25 14:51:32'),
(36, 1, 17, 'e5125895422b2406f2186f1aef1cd44c8e21353dc3a95ce5661707bc692451b4', '', '2026-11-25 14:55:26', '2026-11-25 14:55:26'),
(37, 1, 17, '19ff2acba9780cde51d881d1aadc5d70f710f8fd83c549540171906e2a6aa1d2', '', '2026-11-25 14:55:54', '2026-11-25 14:55:54'),
(38, 1, 17, 'f40beb7d8eac35af930411c63a05c1367749fabbfbea5b6922a67fb3518d2a95', '', '2026-11-25 15:02:54', '2026-11-25 15:02:54'),
(39, 1, 17, 'ede46de86a4b092058ae6e31e17a91ae8f19de1428819b4cf91786f2c9d5bb49', '', '2026-11-25 15:03:31', '2026-11-25 15:03:31'),
(40, 1, 17, '4c70426300d348329d666734e3eb2e278cff9c487c9f5b4027a52e0068d12951', '', '2026-11-25 15:07:36', '2026-11-25 15:07:36');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `studId` bigint(20) NOT NULL,
  `classCode` bigint(20) NOT NULL,
  `schlName` varchar(100) NOT NULL,
  `blkName` varchar(50) NOT NULL,
  `studLastName` varchar(50) NOT NULL,
  `studFirstName` varchar(50) NOT NULL,
  `studMiddleName` varchar(50) NOT NULL,
  `studExtName` varchar(10) DEFAULT NULL,
  `studEmail` varchar(100) NOT NULL,
  `studPassword` varchar(255) NOT NULL,
  `statusActive` tinyint(1) NOT NULL,
  `total_xp` int(11) DEFAULT 0,
  `quiz_avatar` varchar(50) DEFAULT 'mochi.png'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`studId`, `classCode`, `schlName`, `blkName`, `studLastName`, `studFirstName`, `studMiddleName`, `studExtName`, `studEmail`, `studPassword`, `statusActive`, `total_xp`, `quiz_avatar`) VALUES
(1, 1, 'Gordon College', 'BSCS-2B', 'Muit', 'Zhy', '', NULL, 'zhy@gmail.com', 'password', 1, 297, 'fluff.png');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `userId` bigint(20) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`adminId`);

--
-- Indexes for table `answer`
--
ALTER TABLE `answer`
  ADD PRIMARY KEY (`answerId`),
  ADD KEY `questionId` (`questionId`);

--
-- Indexes for table `gamequiz`
--
ALTER TABLE `gamequiz`
  ADD PRIMARY KEY (`gameId`),
  ADD KEY `quizId` (`quizId`);

--
-- Indexes for table `leader_board`
--
ALTER TABLE `leader_board`
  ADD PRIMARY KEY (`leaderboardId`),
  ADD KEY `quizId` (`quizId`);

--
-- Indexes for table `question`
--
ALTER TABLE `question`
  ADD PRIMARY KEY (`questionId`),
  ADD KEY `quizId` (`quizId`);

--
-- Indexes for table `quiz`
--
ALTER TABLE `quiz`
  ADD PRIMARY KEY (`quizId`);

--
-- Indexes for table `quiz_memory_notes`
--
ALTER TABLE `quiz_memory_notes`
  ADD PRIMARY KEY (`noteId`),
  ADD UNIQUE KEY `attempt_token` (`attempt_token`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`studId`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`userId`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `adminId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `answer`
--
ALTER TABLE `answer`
  MODIFY `answerId` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `gamequiz`
--
ALTER TABLE `gamequiz`
  MODIFY `gameId` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `leader_board`
--
ALTER TABLE `leader_board`
  MODIFY `leaderboardId` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `question`
--
ALTER TABLE `question`
  MODIFY `questionId` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `quiz`
--
ALTER TABLE `quiz`
  MODIFY `quizId` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `quiz_memory_notes`
--
ALTER TABLE `quiz_memory_notes`
  MODIFY `noteId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `studId` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `userId` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `answer`
--
ALTER TABLE `answer`
  ADD CONSTRAINT `answer_ibfk_1` FOREIGN KEY (`questionId`) REFERENCES `question` (`questionId`) ON DELETE CASCADE;

--
-- Constraints for table `gamequiz`
--
ALTER TABLE `gamequiz`
  ADD CONSTRAINT `gamequiz_ibfk_1` FOREIGN KEY (`quizId`) REFERENCES `quiz` (`quizId`) ON DELETE CASCADE;

--
-- Constraints for table `leader_board`
--
ALTER TABLE `leader_board`
  ADD CONSTRAINT `leader_board_ibfk_1` FOREIGN KEY (`quizId`) REFERENCES `quiz` (`quizId`) ON DELETE CASCADE;

--
-- Constraints for table `question`
--
ALTER TABLE `question`
  ADD CONSTRAINT `question_ibfk_1` FOREIGN KEY (`quizId`) REFERENCES `quiz` (`quizId`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
