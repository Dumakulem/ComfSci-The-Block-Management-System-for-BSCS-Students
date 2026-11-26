CREATE TABLE IF NOT EXISTS ai_consultation_daily_usage (
    studentID INT NOT NULL,
    usageDate DATE NOT NULL,
    requestCount TINYINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (studentID, usageDate),
    CONSTRAINT chk_ai_consultation_request_count CHECK (requestCount <= 10),
    CONSTRAINT fk_ai_consultation_usage_student
        FOREIGN KEY (studentID) REFERENCES tblstudent (studentID)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_consultation_access (
    studentID INT NOT NULL,
    isRevoked TINYINT(1) NOT NULL DEFAULT 0,
    changedBy INT NULL,
    changedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (studentID),
    KEY idx_ai_consultation_access_changed_by (changedBy),
    CONSTRAINT fk_ai_consultation_access_student
        FOREIGN KEY (studentID) REFERENCES tblstudent (studentID)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_ai_consultation_access_admin
        FOREIGN KEY (changedBy) REFERENCES tbladmin (adminID)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
