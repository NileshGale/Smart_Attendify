-- ============================================================================
-- ATTENDIFY - Complete Attendance Management System Database Schema
-- ============================================================================
-- Version: 2.1 (Merged)
-- Features: Unique codes, QR codes, subject allocation, teacher-student mapping,
--           schedule management, photo/dob support, sample attendance data
-- ============================================================================

-- ============================================================================
-- TABLE: users
-- ============================================================================
CREATE TABLE IF NOT EXISTS users (
    id           INT          AUTO_INCREMENT PRIMARY KEY,
    username     VARCHAR(50)  NOT NULL UNIQUE,
    email        VARCHAR(100) NOT NULL UNIQUE,
    password     VARCHAR(255) NOT NULL,
    full_name    VARCHAR(100) NOT NULL,
    reg_id       VARCHAR(50)  NOT NULL UNIQUE,
    qr_code_path VARCHAR(255) DEFAULT NULL,
    qr_code_data VARCHAR(255) DEFAULT NULL UNIQUE,
    role         ENUM('student','teacher','admin') NOT NULL DEFAULT 'student',
    department   VARCHAR(100) DEFAULT NULL,
    branch       VARCHAR(100) DEFAULT NULL,
    phone        VARCHAR(20)  DEFAULT NULL,
    photo_path   VARCHAR(255) DEFAULT NULL,
    dob          DATE         DEFAULT NULL,
    session_token VARCHAR(255) DEFAULT NULL,
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active    BOOLEAN      DEFAULT TRUE,

    INDEX idx_role     (role),
    INDEX idx_reg_id   (reg_id),
    INDEX idx_email    (email),
    INDEX idx_qr_code  (qr_code_data),
    INDEX idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE: subjects
-- ============================================================================
CREATE TABLE IF NOT EXISTS subjects (
    id           INT          AUTO_INCREMENT PRIMARY KEY,
    subject_name VARCHAR(100) NOT NULL,
    subject_code VARCHAR(20)  NOT NULL UNIQUE,
    description  TEXT         DEFAULT NULL,
    credits      INT          DEFAULT 3,
    department   VARCHAR(100) DEFAULT NULL,
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_subject_code (subject_code),
    INDEX idx_subject_name (subject_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE: teacher_subjects
-- ============================================================================
CREATE TABLE IF NOT EXISTS teacher_subjects (
    id            INT         AUTO_INCREMENT PRIMARY KEY,
    teacher_id    INT         NOT NULL,
    subject_id    INT         NOT NULL,
    academic_year VARCHAR(20) DEFAULT '2024-2025',
    created_at    TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (teacher_id) REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    UNIQUE KEY unique_teacher_subject (teacher_id, subject_id),

    INDEX idx_teacher (teacher_id),
    INDEX idx_subject (subject_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE: student_subjects
-- ============================================================================
CREATE TABLE IF NOT EXISTS student_subjects (
    id            INT         AUTO_INCREMENT PRIMARY KEY,
    student_id    INT         NOT NULL,
    subject_id    INT         NOT NULL,
    academic_year VARCHAR(20) DEFAULT '2024-2025',
    enrolled_at   TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (student_id) REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_subject (student_id, subject_id),

    INDEX idx_student (student_id),
    INDEX idx_subject (subject_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE: teacher_schedules  (admin-managed timetable per teacher)
-- ============================================================================
CREATE TABLE IF NOT EXISTS teacher_schedules (
    id            INT          AUTO_INCREMENT PRIMARY KEY,
    teacher_id    INT          NOT NULL,
    day_of_week   ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') NOT NULL,
    start_time    TIME         NOT NULL,
    end_time      TIME         NOT NULL,
    subject_name  VARCHAR(120) NOT NULL,
    subject_code  VARCHAR(30)  DEFAULT NULL,
    class_section VARCHAR(40)  DEFAULT NULL,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    KEY idx_ts_teacher (teacher_id),
    KEY idx_ts_day     (day_of_week),
    CONSTRAINT fk_ts_teacher FOREIGN KEY (teacher_id)
        REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE: schedules  (legacy — subject-linked timetable)
-- ============================================================================
CREATE TABLE IF NOT EXISTS schedules (
    id            INT  AUTO_INCREMENT PRIMARY KEY,
    teacher_id    INT  NOT NULL,
    subject_id    INT  NOT NULL,
    day_of_week   ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
    start_time    TIME NOT NULL,
    end_time      TIME NOT NULL,
    class_name    VARCHAR(50)  DEFAULT NULL,
    academic_year VARCHAR(20)  DEFAULT '2024-2025',
    room_number   VARCHAR(20)  DEFAULT NULL,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (teacher_id) REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,

    INDEX idx_teacher (teacher_id),
    INDEX idx_subject (subject_id),
    INDEX idx_day     (day_of_week)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE: attendance_sessions
-- ============================================================================
CREATE TABLE IF NOT EXISTS attendance_sessions (
    id              INT         AUTO_INCREMENT PRIMARY KEY,
    teacher_id      INT         NOT NULL,
    subject_id      INT         NOT NULL,
    session_date    DATE        NOT NULL,
    unique_code     VARCHAR(10) NOT NULL UNIQUE,
    code_expires_at DATETIME    NOT NULL,
    created_at      TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
    is_active       BOOLEAN     DEFAULT TRUE,

    FOREIGN KEY (teacher_id) REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,

    INDEX idx_unique_code     (unique_code),
    INDEX idx_teacher_subject (teacher_id, subject_id),
    INDEX idx_date            (session_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE: attendance
-- ============================================================================
CREATE TABLE IF NOT EXISTS attendance (
    id                    INT  AUTO_INCREMENT PRIMARY KEY,
    student_id            INT  NOT NULL,
    subject_id            INT  NOT NULL,
    teacher_id            INT  DEFAULT NULL,
    attendance_date       DATE NOT NULL,
    attendance_session_id INT  DEFAULT NULL,
    marking_method        ENUM('manual','qr','unique_code') DEFAULT 'manual',
    status                ENUM('present','absent')          NOT NULL DEFAULT 'present',
    marked_at             TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (student_id)            REFERENCES users(id)               ON DELETE CASCADE,
    FOREIGN KEY (subject_id)            REFERENCES subjects(id)            ON DELETE CASCADE,
    FOREIGN KEY (teacher_id)            REFERENCES users(id)               ON DELETE SET NULL,
    FOREIGN KEY (attendance_session_id) REFERENCES attendance_sessions(id) ON DELETE SET NULL,

    UNIQUE KEY unique_attendance (student_id, subject_id, attendance_date),
    INDEX idx_student (student_id),
    INDEX idx_subject (subject_id),
    INDEX idx_teacher (teacher_id),
    INDEX idx_date    (attendance_date),
    INDEX idx_status  (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE: attendance_proofs
-- ============================================================================
CREATE TABLE IF NOT EXISTS attendance_proofs (
    id              INT          AUTO_INCREMENT PRIMARY KEY,
    attendance_id   INT          NOT NULL,
    proof_file_path VARCHAR(255) NOT NULL,
    uploaded_by     INT          DEFAULT NULL,
    reason          TEXT         DEFAULT NULL,
    uploaded_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (attendance_id) REFERENCES attendance(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by)   REFERENCES users(id)      ON DELETE SET NULL,

    INDEX idx_attendance (attendance_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE: events  (College Events for attendance via unique code / QR)
-- ============================================================================
CREATE TABLE IF NOT EXISTS events (
    id              INT          AUTO_INCREMENT PRIMARY KEY,
    event_name      VARCHAR(255) NOT NULL,
    event_date      DATE         NOT NULL,
    event_time      TIME         NOT NULL,
    teacher_id      INT          NOT NULL,
    unique_code     VARCHAR(10)  DEFAULT NULL,
    code_expires_at DATETIME     DEFAULT NULL,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_unique_code (unique_code),
    INDEX idx_teacher     (teacher_id),
    INDEX idx_event_date  (event_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE: event_attendance  (Tracks which students attended which event)
-- ============================================================================
CREATE TABLE IF NOT EXISTS event_attendance (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    event_id        INT NOT NULL,
    student_id      INT NOT NULL,
    marking_method  ENUM('qr','unique_code') DEFAULT 'unique_code',
    scanned_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (event_id)   REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id)  ON DELETE CASCADE,
    UNIQUE KEY unique_event_student (event_id, student_id),

    INDEX idx_event   (event_id),
    INDEX idx_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE: password_reset_tokens
-- ============================================================================
CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id         INT          AUTO_INCREMENT PRIMARY KEY,
    email      VARCHAR(100) NOT NULL,
    token      VARCHAR(255) NOT NULL,
    expires_at DATETIME     NOT NULL,
    used       TINYINT(1)   DEFAULT 0,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_email   (email),
    INDEX idx_expires (expires_at),
    INDEX idx_used    (used)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- DEFAULT DATA





-- ── Subjects ───────────────────────────────────────────────────────────────
INSERT IGNORE INTO subjects (subject_name, subject_code, department, credits)
VALUES
('C Programming',       'CP101',  'Computer Science', 4),
('Core Java',           'CJ201',  'Computer Science', 4),
('Python Programming',  'PP301',  'Computer Science', 4),
('PHP',                 'PHP101', 'Computer Science', 4),
('SQL with Oracle',     'SQL201', 'Computer Science', 4),
('E Commerce',          'EC301',  'Commerce',         3),
('Cloud Computing',     'CC401',  'Computer Science', 3),
('Digital Marketing',   'DM201',  'Commerce',         3);



ALTER TABLE users ADD COLUMN failed_attempts INT DEFAULT 0;
ALTER TABLE users ADD COLUMN lockout_until DATETIME DEFAULT NULL;

-- ============================================================================
-- COMPLETION MESSAGE
-- ============================================================================
SELECT '✓ Attendify Complete Database (v2.1) Created Successfully!' AS status;
SELECT 'Default password for all users: admin123'                    AS note;
SELECT '⚠  Change all passwords after first login!'                  AS warning;
SELECT 'Table teacher_schedules is ready for admin schedule mgmt'    AS info;



