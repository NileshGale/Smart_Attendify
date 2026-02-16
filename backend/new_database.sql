-- ============================================================================
-- ATTENDIFY - Enhanced Attendance Management System Database Schema
-- ============================================================================
-- Version: 2.1 Enhanced + teacher_schedules
-- Features: Unique codes, QR codes, subject allocation, teacher-student mapping,
--           schedule management, photo/dob support
-- ============================================================================

CREATE DATABASE IF NOT EXISTS attendify_db
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE attendify_db;

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

    INDEX idx_unique_code      (unique_code),
    INDEX idx_teacher_subject  (teacher_id, subject_id),
    INDEX idx_date             (session_date)
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
-- ============================================================================
-- Default password: admin123  (bcrypt hash — change in production!)
SET @default_password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

-- ── Admin ──────────────────────────────────────────────────────────────────
INSERT INTO users (username, email, password, full_name, reg_id, role, is_active)
VALUES ('admin', 'admin@attendify.com', @default_password, 'System Administrator', 'ADMIN001', 'admin', TRUE);

-- ── Teachers ───────────────────────────────────────────────────────────────
INSERT INTO users (username, email, password, full_name, reg_id, qr_code_data, role, department, is_active)
VALUES
('bhagyashree.ambulkar', 'bhagyashree@attendify.com', @default_password,
 'Bhagyashree Ambulkar', 'TEA2024001', 'QRT001', 'teacher', 'Computer Science', TRUE),
('rekha.israni',         'rekha@attendify.com',        @default_password,
 'Rekha Israni',         'TEA2024002', 'QRT002', 'teacher', 'Computer Science', TRUE),
('alina.sani',           'alina@attendify.com',         @default_password,
 'Alina Sani',           'TEA2024003', 'QRT003', 'teacher', 'Management',       TRUE);

-- ── Students ───────────────────────────────────────────────────────────────
INSERT INTO users (username, email, password, full_name, reg_id, qr_code_data, role, branch, is_active)
VALUES
('nilesh.gale',     'nilesh@student.attendify.com',    @default_password, 'Nilesh Gale',     'SEE2004001', 'QRS2004001', 'student', 'Computer Science', TRUE),
('parinita.paigwar','parinita@student.attendify.com',  @default_password, 'Parinita Paigwar','SEE2004002', 'QRS2004002', 'student', 'Computer Science', TRUE),
('mohit.mawre',     'mohit@student.attendify.com',     @default_password, 'Mohit Mawre',     'SEE2004003', 'QRS2004003', 'student', 'Computer Science', TRUE),
('prajwal.pimple',  'prajwal@student.attendify.com',   @default_password, 'Prajwal Pimple',  'SEE2004004', 'QRS2004004', 'student', 'Computer Science', TRUE),
('vivek.mandve',    'vivek@student.attendify.com',      @default_password, 'Vivek Mandve',    'SEE2004005', 'QRS2004005', 'student', 'Management',       TRUE),
('karan.konge',     'karan@student.attendify.com',      @default_password, 'Karan Konge',     'SEE2004006', 'QRS2004006', 'student', 'Management',       TRUE),
('tanvi.rane',      'tanvi@student.attendify.com',      @default_password, 'Tanvi Rane',      'SEE2004007', 'QRS2004007', 'student', 'Computer Science', TRUE);

-- ── Subjects ───────────────────────────────────────────────────────────────
INSERT INTO subjects (subject_name, subject_code, department, credits)
VALUES
('Java Programming',             'CS101', 'Computer Science', 4),
('SQL & Database',               'CS102', 'Computer Science', 4),
('Python Programming',           'CS103', 'Computer Science', 4),
('ICI (Internet & Computing)',   'CS104', 'Computer Science', 3),
('C Programming',                'CS105', 'Computer Science', 4),
('Cloud Computing',              'CS201', 'Computer Science', 3),
('E-Commerce',                   'CS202', 'Computer Science', 3),
('PHP Web Development',          'CS203', 'Computer Science', 4),
('Operating System',             'CS204', 'Computer Science', 4),
('Operation Research',           'CS205', 'Computer Science', 3),
('Digital Marketing',            'MG101', 'Management',       3),
('Organisational Behaviour',     'MG102', 'Management',       3),
('Microsoft Excel',              'MG103', 'Management',       2),
('Sales Management',             'MG104', 'Management',       3);

-- ── Teacher ↔ Subject mapping ──────────────────────────────────────────────
-- Bhagyashree Ambulkar (id=2): Java, SQL, Python, ICI, C Programming
INSERT INTO teacher_subjects (teacher_id, subject_id) VALUES (2,1),(2,2),(2,3),(2,4),(2,5);

-- Rekha Israni (id=3): Cloud, E-Commerce, PHP, OS, Operation Research
INSERT INTO teacher_subjects (teacher_id, subject_id) VALUES (3,6),(3,7),(3,8),(3,9),(3,10);

-- Alina Sani (id=4): Digital Marketing, OB, Excel, Sales Management
INSERT INTO teacher_subjects (teacher_id, subject_id) VALUES (4,11),(4,12),(4,13),(4,14);

-- ── Student ↔ Subject mapping ──────────────────────────────────────────────
-- Nilesh Gale (id=5)
INSERT INTO student_subjects (student_id, subject_id) VALUES (5,2),(5,3),(5,9),(5,8);
-- Parinita Paigwar (id=6)
INSERT INTO student_subjects (student_id, subject_id) VALUES (6,2),(6,3),(6,9),(6,8);
-- Mohit Mawre (id=7)
INSERT INTO student_subjects (student_id, subject_id) VALUES (7,7),(7,6),(7,10),(7,11);
-- Prajwal Pimple (id=8)
INSERT INTO student_subjects (student_id, subject_id) VALUES (8,11),(8,13),(8,14),(8,1);
-- Vivek Mandve (id=9)
INSERT INTO student_subjects (student_id, subject_id) VALUES (9,12),(9,13),(9,10),(9,11);
-- Karan Konge (id=10)
INSERT INTO student_subjects (student_id, subject_id) VALUES (10,13),(10,14),(10,7),(10,6);
-- Tanvi Rane (id=11)
INSERT INTO student_subjects (student_id, subject_id) VALUES (11,2),(11,3),(11,12),(11,1);

-- ── Teacher Schedule seed data ─────────────────────────────────────────────
-- Bhagyashree Ambulkar (teacher_id = 2) — full week timetable
INSERT INTO teacher_schedules
    (teacher_id, day_of_week, start_time, end_time, subject_name,              subject_code, class_section)
VALUES
-- Monday
(2, 'Monday',    '10:00:00', '11:00:00', 'C Programming',              'CS105', 'CSE-2A'),
(2, 'Monday',    '11:00:00', '12:00:00', 'Java Programming',           'CS101', 'CSE-2B'),
(2, 'Monday',    '12:30:00', '13:30:00', 'SQL & Database',             'CS102', 'CSE-2C'),
(2, 'Monday',    '13:30:00', '14:30:00', 'ICI (Internet & Computing)', 'CS104', 'CSE-3B'),
-- Tuesday
(2, 'Tuesday',   '10:00:00', '11:00:00', 'Python Programming',        'CS103', 'CSE-3A'),
(2, 'Tuesday',   '11:00:00', '12:00:00', 'SQL & Database',            'CS102', 'CSE-2C'),
(2, 'Tuesday',   '12:30:00', '13:30:00', 'C Programming',             'CS105', 'CSE-2A'),
(2, 'Tuesday',   '13:30:00', '14:30:00', 'Java Programming',          'CS101', 'CSE-2B'),
-- Wednesday
(2, 'Wednesday', '10:00:00', '11:00:00', 'ICI (Internet & Computing)', 'CS104', 'CSE-3B'),
(2, 'Wednesday', '11:00:00', '12:00:00', 'C Programming',              'CS105', 'CSE-2A'),
(2, 'Wednesday', '12:30:00', '13:30:00', 'Python Programming',         'CS103', 'CSE-3A'),
-- Thursday
(2, 'Thursday',  '10:00:00', '11:00:00', 'Java Programming',           'CS101', 'CSE-2B'),
(2, 'Thursday',  '11:00:00', '12:00:00', 'SQL & Database',             'CS102', 'CSE-2C'),
(2, 'Thursday',  '12:30:00', '13:30:00', 'ICI (Internet & Computing)', 'CS104', 'CSE-3B'),
-- Friday
(2, 'Friday',    '10:00:00', '11:00:00', 'Python Programming',        'CS103', 'CSE-3A'),
(2, 'Friday',    '11:00:00', '12:00:00', 'C Programming',             'CS105', 'CSE-2A'),
(2, 'Friday',    '12:30:00', '13:30:00', 'Java Programming',          'CS101', 'CSE-2B');

-- Rekha Israni (teacher_id = 3) — sample timetable
INSERT INTO teacher_schedules
    (teacher_id, day_of_week, start_time, end_time, subject_name,     subject_code, class_section)
VALUES
(3, 'Monday',    '10:00:00', '11:00:00', 'Cloud Computing',   'CS201', 'CSE-3A'),
(3, 'Monday',    '11:00:00', '12:00:00', 'PHP Web Development','CS203','CSE-3B'),
(3, 'Tuesday',   '10:00:00', '11:00:00', 'E-Commerce',        'CS202', 'CSE-2A'),
(3, 'Tuesday',   '11:00:00', '12:00:00', 'Operating System',  'CS204', 'CSE-2B'),
(3, 'Wednesday', '10:00:00', '11:00:00', 'Operation Research','CS205', 'CSE-2C'),
(3, 'Thursday',  '10:00:00', '11:00:00', 'Cloud Computing',   'CS201', 'CSE-3A'),
(3, 'Friday',    '10:00:00', '11:00:00', 'PHP Web Development','CS203','CSE-3B');

-- Alina Sani (teacher_id = 4) — sample timetable
INSERT INTO teacher_schedules
    (teacher_id, day_of_week, start_time, end_time, subject_name,              subject_code, class_section)
VALUES
(4, 'Monday',    '10:00:00', '11:00:00', 'Digital Marketing',         'MG101', 'MGMT-1A'),
(4, 'Monday',    '11:00:00', '12:00:00', 'Sales Management',          'MG104', 'MGMT-2A'),
(4, 'Tuesday',   '10:00:00', '11:00:00', 'Organisational Behaviour',  'MG102', 'MGMT-2B'),
(4, 'Tuesday',   '11:00:00', '12:00:00', 'Microsoft Excel',           'MG103', 'MGMT-1B'),
(4, 'Wednesday', '10:00:00', '11:00:00', 'Sales Management',          'MG104', 'MGMT-2A'),
(4, 'Thursday',  '10:00:00', '11:00:00', 'Digital Marketing',         'MG101', 'MGMT-1A'),
(4, 'Friday',    '10:00:00', '11:00:00', 'Organisational Behaviour',  'MG102', 'MGMT-2B');

-- ============================================================================
-- SAMPLE ATTENDANCE DATA (last 30 days, realistic percentages)
-- ============================================================================
-- Helper: insert 30 days of attendance for student/subject pairs
-- Nilesh Gale (id=5) — SQL (subject 2)
INSERT INTO attendance (student_id, subject_id, teacher_id, attendance_date, marking_method, status)
SELECT 5, 2, 2, DATE_SUB(CURDATE(), INTERVAL n DAY),
       'manual',
       CASE WHEN n % 7 IN (1,2) THEN 'absent' ELSE 'present' END
FROM (
    SELECT 1 n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5
    UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10
    UNION SELECT 11 UNION SELECT 12 UNION SELECT 13 UNION SELECT 14 UNION SELECT 15
    UNION SELECT 16 UNION SELECT 17 UNION SELECT 18 UNION SELECT 19 UNION SELECT 20
    UNION SELECT 21 UNION SELECT 22 UNION SELECT 23 UNION SELECT 24 UNION SELECT 25
    UNION SELECT 26 UNION SELECT 27 UNION SELECT 28 UNION SELECT 29 UNION SELECT 30
) nums
ON DUPLICATE KEY UPDATE status = VALUES(status);

-- Nilesh Gale (id=5) — Python (subject 3)
INSERT INTO attendance (student_id, subject_id, teacher_id, attendance_date, marking_method, status)
SELECT 5, 3, 2, DATE_SUB(CURDATE(), INTERVAL n DAY),
       'manual',
       CASE WHEN n % 10 IN (3) THEN 'absent' ELSE 'present' END
FROM (
    SELECT 1 n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5
    UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10
    UNION SELECT 11 UNION SELECT 12 UNION SELECT 13 UNION SELECT 14 UNION SELECT 15
    UNION SELECT 16 UNION SELECT 17 UNION SELECT 18 UNION SELECT 19 UNION SELECT 20
    UNION SELECT 21 UNION SELECT 22 UNION SELECT 23 UNION SELECT 24 UNION SELECT 25
) nums
ON DUPLICATE KEY UPDATE status = VALUES(status);

-- Mohit Mawre (id=7) — E-Commerce (subject 7)
INSERT INTO attendance (student_id, subject_id, teacher_id, attendance_date, marking_method, status)
SELECT 7, 7, 3, DATE_SUB(CURDATE(), INTERVAL n DAY),
       'manual',
       CASE WHEN n IN (2,5,9,14,19,22) THEN 'absent' ELSE 'present' END
FROM (
    SELECT 1 n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5
    UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10
    UNION SELECT 11 UNION SELECT 12 UNION SELECT 13 UNION SELECT 14 UNION SELECT 15
    UNION SELECT 16 UNION SELECT 17 UNION SELECT 18 UNION SELECT 19 UNION SELECT 20
    UNION SELECT 21 UNION SELECT 22 UNION SELECT 23 UNION SELECT 24 UNION SELECT 25
) nums
ON DUPLICATE KEY UPDATE status = VALUES(status);

-- ============================================================================
-- VIEWS
-- ============================================================================

CREATE OR REPLACE VIEW view_student_subjects AS
SELECT
    u.id          AS student_id,
    u.reg_id,
    u.full_name   AS student_name,
    u.email,
    s.id          AS subject_id,
    s.subject_name,
    s.subject_code,
    s.credits,
    ss.academic_year
FROM users u
JOIN student_subjects ss ON u.id = ss.student_id
JOIN subjects s          ON ss.subject_id = s.id
WHERE u.role = 'student' AND u.is_active = TRUE
ORDER BY u.full_name, s.subject_name;

-- ---------------------------------------------------------------------------

CREATE OR REPLACE VIEW view_teacher_subjects AS
SELECT
    u.id          AS teacher_id,
    u.reg_id,
    u.full_name   AS teacher_name,
    u.email,
    s.id          AS subject_id,
    s.subject_name,
    s.subject_code,
    ts.academic_year
FROM users u
JOIN teacher_subjects ts ON u.id = ts.teacher_id
JOIN subjects s          ON ts.subject_id = s.id
WHERE u.role = 'teacher' AND u.is_active = TRUE
ORDER BY u.full_name, s.subject_name;

-- ---------------------------------------------------------------------------

CREATE OR REPLACE VIEW view_student_attendance_summary AS
SELECT
    u.id          AS student_id,
    u.reg_id,
    u.full_name   AS student_name,
    s.subject_name,
    s.subject_code,
    COUNT(a.id)                                                                              AS total_classes,
    SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END)                                   AS present_count,
    SUM(CASE WHEN a.status = 'absent'  THEN 1 ELSE 0 END)                                   AS absent_count,
    ROUND(SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) / COUNT(a.id) * 100, 2)    AS attendance_percentage
FROM users u
JOIN attendance a ON u.id = a.student_id
JOIN subjects s   ON a.subject_id = s.id
WHERE u.role = 'student'
GROUP BY u.id, u.reg_id, u.full_name, s.subject_name, s.subject_code;

-- ---------------------------------------------------------------------------
-- View: Teacher timetable (joins teacher_schedules with user info)

CREATE OR REPLACE VIEW view_teacher_timetable AS
SELECT
    ts.id,
    u.id          AS teacher_id,
    u.reg_id      AS teacher_reg_id,
    u.full_name   AS teacher_name,
    u.department,
    ts.day_of_week,
    ts.start_time,
    ts.end_time,
    CONCAT(DATE_FORMAT(ts.start_time,'%h:%i %p'),' - ',DATE_FORMAT(ts.end_time,'%h:%i %p')) AS time_slot,
    ts.subject_name,
    ts.subject_code,
    ts.class_section
FROM teacher_schedules ts
JOIN users u ON ts.teacher_id = u.id
ORDER BY u.full_name,
         FIELD(ts.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'),
         ts.start_time;

-- ============================================================================
-- STORED PROCEDURES
-- ============================================================================

DELIMITER //

-- ── Generate Unique Attendance Code ────────────────────────────────────────
CREATE PROCEDURE sp_generate_attendance_code(
    IN  p_teacher_id       INT,
    IN  p_subject_id       INT,
    IN  p_session_date     DATE,
    IN  p_validity_minutes INT
)
BEGIN
    DECLARE v_unique_code VARCHAR(10);
    DECLARE v_expires_at  DATETIME;

    SET v_unique_code = UPPER(SUBSTRING(MD5(RAND()), 1, 6));
    SET v_expires_at  = DATE_ADD(NOW(), INTERVAL p_validity_minutes MINUTE);

    UPDATE attendance_sessions
    SET    is_active = FALSE
    WHERE  teacher_id   = p_teacher_id
      AND  subject_id   = p_subject_id
      AND  session_date = p_session_date;

    INSERT INTO attendance_sessions
        (teacher_id, subject_id, session_date, unique_code, code_expires_at)
    VALUES
        (p_teacher_id, p_subject_id, p_session_date, v_unique_code, v_expires_at);

    SELECT v_unique_code AS unique_code, v_expires_at AS expires_at;
END //

-- ── Mark Attendance via Unique Code ────────────────────────────────────────
CREATE PROCEDURE sp_mark_attendance_by_code(
    IN p_student_id  INT,
    IN p_unique_code VARCHAR(10)
)
BEGIN
    DECLARE v_session_id   INT;
    DECLARE v_teacher_id   INT;
    DECLARE v_subject_id   INT;
    DECLARE v_session_date DATE;

    SELECT id, teacher_id, subject_id, session_date
    INTO   v_session_id, v_teacher_id, v_subject_id, v_session_date
    FROM   attendance_sessions
    WHERE  unique_code    = p_unique_code
      AND  is_active      = TRUE
      AND  code_expires_at > NOW()
    LIMIT  1;

    IF v_session_id IS NOT NULL THEN
        IF EXISTS (
            SELECT 1 FROM student_subjects
            WHERE  student_id = p_student_id
              AND  subject_id = v_subject_id
        ) THEN
            INSERT INTO attendance
                (student_id, subject_id, teacher_id, attendance_date,
                 attendance_session_id, marking_method, status)
            VALUES
                (p_student_id, v_subject_id, v_teacher_id, v_session_date,
                 v_session_id, 'unique_code', 'present')
            ON DUPLICATE KEY UPDATE
                status                = 'present',
                marking_method        = 'unique_code',
                attendance_session_id = v_session_id,
                marked_at             = NOW();

            SELECT 'success' AS status, 'Attendance marked successfully' AS message;
        ELSE
            SELECT 'error' AS status, 'You are not enrolled in this subject' AS message;
        END IF;
    ELSE
        SELECT 'error' AS status, 'Invalid or expired code' AS message;
    END IF;
END //

-- ── Increase Attendance Percentage ─────────────────────────────────────────
CREATE PROCEDURE sp_increase_attendance(
    IN p_student_id         INT,
    IN p_subject_id         INT,
    IN p_percentage_increase DECIMAL(5,2)
)
BEGIN
    DECLARE v_total_classes     INT;
    DECLARE v_present_count     INT;
    DECLARE v_current_pct       DECIMAL(5,2);
    DECLARE v_target_pct        DECIMAL(5,2);
    DECLARE v_classes_to_add    INT;
    DECLARE v_counter           INT DEFAULT 0;
    DECLARE v_attendance_date   DATE;

    SELECT COUNT(*),
           SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END),
           ROUND(SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) / COUNT(*) * 100, 2)
    INTO   v_total_classes, v_present_count, v_current_pct
    FROM   attendance
    WHERE  student_id = p_student_id AND subject_id = p_subject_id;

    SET v_target_pct = LEAST(v_current_pct + p_percentage_increase, 100);

    SET v_classes_to_add = CEIL(
        (v_target_pct * (v_total_classes + v_present_count) - 100 * v_present_count)
        / (100 - v_target_pct)
    );

    SET v_attendance_date = CURDATE();
    WHILE v_counter < v_classes_to_add DO
        SET v_attendance_date = DATE_ADD(v_attendance_date, INTERVAL 1 DAY);

        INSERT IGNORE INTO attendance
            (student_id, subject_id, attendance_date, status, marking_method)
        VALUES
            (p_student_id, p_subject_id, v_attendance_date, 'present', 'manual');

        SET v_counter = v_counter + 1;
    END WHILE;

    SELECT 'success' AS status,
           CONCAT('Added ', v_classes_to_add, ' classes to increase attendance') AS message,
           v_classes_to_add AS classes_added;
END //

DELIMITER ;

-- ============================================================================
-- COMPLETION MESSAGE
-- ============================================================================
SELECT '✓ Attendify Enhanced Database (v2.1) Created Successfully!'   AS status;
SELECT 'Default password for all users: admin123'                      AS note;
SELECT '⚠  Change all passwords after first login!'                   AS warning;
SELECT 'New table teacher_schedules is ready for admin schedule mgmt'  AS info;