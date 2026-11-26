USE `db_comfysci`;

ALTER TABLE `tblstudent`
  ADD COLUMN IF NOT EXISTS `totalXp` int(11) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `quizAvatar` varchar(50) NOT NULL DEFAULT 'mochi.png';

CREATE OR REPLACE VIEW `students` AS
SELECT
  `studentID` AS `studId`,
  `studentID` AS `classCode`,
  'Gordon College' AS `schlName`,
  '' AS `blkName`,
  `studentLastName` AS `studLastName`,
  `studentFirstName` AS `studFirstName`,
  '' AS `studMiddleName`,
  '' AS `studExtName`,
  `studentEmail` AS `studEmail`,
  `studentPassword` AS `studPassword`,
  1 AS `statusActive`,
  `totalXp` AS `total_xp`,
  `quizAvatar` AS `quiz_avatar`
FROM `tblstudent`
WHERE `archivedAt` IS NULL;

CREATE TABLE IF NOT EXISTS `quiz` (
  `quizId` bigint(20) NOT NULL AUTO_INCREMENT,
  `title` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `createdBy` bigint(20) NOT NULL DEFAULT 0,
  `isPublic` tinyint(1) NOT NULL DEFAULT 1,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `quizType` varchar(50) NOT NULL DEFAULT '',
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `timer` varchar(50) DEFAULT NULL,
  `points` int(11) DEFAULT NULL,
  `due_date` varchar(50) DEFAULT NULL,
  `question` text DEFAULT NULL,
  `quiz_type` varchar(50) DEFAULT NULL,
  `allow_retake` tinyint(1) NOT NULL DEFAULT 0,
  `xp_per_correct` int(11) NOT NULL DEFAULT 5,
  `show_answer_feedback` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`quizId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `question` (
  `questionId` bigint(20) NOT NULL AUTO_INCREMENT,
  `quizId` bigint(20) NOT NULL,
  `questionText` text NOT NULL,
  `type` varchar(30) NOT NULL,
  `choices` text DEFAULT NULL,
  `correct_answer` text DEFAULT NULL,
  `points` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`questionId`),
  KEY `idxQuestionQuiz` (`quizId`),
  CONSTRAINT `fkGamifiedQuestionQuiz` FOREIGN KEY (`quizId`) REFERENCES `quiz` (`quizId`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `answer` (
  `answerId` bigint(20) NOT NULL AUTO_INCREMENT,
  `questionId` bigint(20) NOT NULL,
  `answerText` text NOT NULL,
  `isCorrect` tinyint(1) NOT NULL DEFAULT 0,
  `score` int(11) NOT NULL DEFAULT 0,
  `xp_earned` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`answerId`),
  KEY `idxAnswerQuestion` (`questionId`),
  CONSTRAINT `fkGamifiedAnswerQuestion` FOREIGN KEY (`questionId`) REFERENCES `question` (`questionId`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `gamequiz` (
  `gameId` bigint(20) NOT NULL AUTO_INCREMENT,
  `userId` int(25) NOT NULL,
  `quizId` bigint(20) NOT NULL,
  `score` int(11) NOT NULL DEFAULT 0,
  `totalPoints` int(11) NOT NULL DEFAULT 0,
  `startedAt` datetime DEFAULT NULL,
  `endedAt` datetime DEFAULT NULL,
  PRIMARY KEY (`gameId`),
  KEY `idxGameQuizQuiz` (`quizId`),
  KEY `idxGameQuizUser` (`userId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `quiz_memory_notes` (
  `noteId` int(11) NOT NULL AUTO_INCREMENT,
  `userId` int(25) NOT NULL,
  `quizId` bigint(20) NOT NULL,
  `attempt_token` varchar(64) NOT NULL,
  `memory_note` text DEFAULT NULL,
  `locked_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`noteId`),
  UNIQUE KEY `uqGamifiedAttemptToken` (`attempt_token`),
  KEY `idxMemoryQuizUser` (`quizId`,`userId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `leader_board` (
  `leaderboardId` bigint(20) NOT NULL AUTO_INCREMENT,
  `userId` int(25) NOT NULL,
  `quizId` bigint(20) NOT NULL,
  `score` int(11) NOT NULL,
  `rank` int(11) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`leaderboardId`),
  KEY `idxLeaderQuiz` (`quizId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `student_cosmetic_rewards` (
  `rewardId` bigint(20) NOT NULL AUTO_INCREMENT,
  `userId` int(25) NOT NULL,
  `reward_type` varchar(50) NOT NULL,
  `reward_name` varchar(80) NOT NULL,
  `earned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`rewardId`),
  KEY `idxCosmeticRewardUser` (`userId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
