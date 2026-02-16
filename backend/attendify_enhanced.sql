-- ============================================================================
-- ATTENDIFY - Enhanced Attendance Management System Database Schema
-- ============================================================================
-- Version: 2.0 Enhanced
-- Features: Unique codes, QR codes, subject allocation, teacher-student mapping
-- ============================================================================

CREATE DATABASE IF NOT EXISTS attendify_db
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE attendify_db;

-- ============================================================================
-- TABLE: users (Enhanced with QR code storage)
-- ============================================================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    reg_id VARCHAR(50) UNIQUE NOT NULL,
    qr_code_path VARCHAR(255),  -- Stores path to QR code image
    qr_code_data VARCHAR(255) UNIQUE,  -- Unique QR data string
    role ENUM('student', 'teacher', 'admin') NOT NULL DEFAULT 'student',
    department VARCHAR(100),
    branch VARCHAR(100),
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    
    INDEX idx_role (role),
    INDEX idx_reg_id (reg_id),
    INDEX idx_email (email),
    INDEX idx_qr_code (qr_code_data),
    INDEX idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE: subjects
-- ============================================================================
CREATE TABLE IF NOT EXISTS subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_name VARCHAR(100) NOT NULL,
    subject_code VARCHAR(20) NOT NULL UNIQUE,
    description TEXT,
    credits INT DEFAULT 3,
    department VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_subject_code (subject_code),
    INDEX idx_subject_name (subject_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE: teacher_subjects (Maps teachers to subjects they teach)
-- ============================================================================
CREATE TABLE IF NOT EXISTS teacher_subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    subject_id INT NOT NULL,
    academic_year VARCHAR(20) DEFAULT '2024-2025',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    UNIQUE KEY unique_teacher_subject (teacher_id, subject_id),
    
    INDEX idx_teacher (teacher_id),
    INDEX idx_subject (subject_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE: student_subjects (Maps students to subjects they're enrolled in)
-- ============================================================================
CREATE TABLE IF NOT EXISTS student_subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    subject_id INT NOT NULL,
    academic_year VARCHAR(20) DEFAULT '2024-2025',
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_subject (student_id, subject_id),
    
    INDEX idx_student (student_id),
    INDEX idx_subject (subject_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE: schedules
-- ============================================================================
CREATE TABLE IF NOT EXISTS schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    subject_id INT NOT NULL,
    day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    class_name VARCHAR(50),
    academic_year VARCHAR(20) DEFAULT '2024-2025',
    room_number VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    
    INDEX idx_teacher (teacher_id),
    INDEX idx_subject (subject_id),
    INDEX idx_day (day_of_week)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE: attendance_sessions (For unique code-based attendance)
-- ============================================================================
CREATE TABLE IF NOT EXISTS attendance_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    subject_id INT NOT NULL,
    session_date DATE NOT NULL,
    unique_code VARCHAR(10) NOT NULL UNIQUE,
    code_expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    
    INDEX idx_unique_code (unique_code),
    INDEX idx_teacher_subject (teacher_id, subject_id),
    INDEX idx_date (session_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE: attendance
-- ============================================================================
CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    subject_id INT NOT NULL,
    teacher_id INT,
    attendance_date DATE NOT NULL,
    attendance_session_id INT,  -- Links to attendance_sessions if marked via unique code
    marking_method ENUM('manual', 'qr', 'unique_code') DEFAULT 'manual',
    status ENUM('present', 'absent') NOT NULL DEFAULT 'present',
    marked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (attendance_session_id) REFERENCES attendance_sessions(id) ON DELETE SET NULL,
    
    UNIQUE KEY unique_attendance (student_id, subject_id, attendance_date),
    INDEX idx_student (student_id),
    INDEX idx_subject (subject_id),
    INDEX idx_teacher (teacher_id),
    INDEX idx_date (attendance_date),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE: attendance_proofs
-- ============================================================================
CREATE TABLE IF NOT EXISTS attendance_proofs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attendance_id INT NOT NULL,
    proof_file_path VARCHAR(255) NOT NULL,
    uploaded_by INT,
    reason TEXT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (attendance_id) REFERENCES attendance(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_attendance (attendance_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE: password_reset_tokens
-- ============================================================================
CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_email (email),
    INDEX idx_expires (expires_at),
    INDEX idx_used (used)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- INSERT DEFAULT DATA
-- ============================================================================

-- Password: admin123 (for all users - change in production!)
SET @default_password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

-- Insert Admin
INSERT INTO users (username, email, password, full_name, reg_id, role, is_active) VALUES
('admin', 'admin@attendify.com', @default_password, 'System Administrator', 'ADMIN001', 'admin', TRUE);

-- Insert Teachers with specific names
INSERT INTO users (username, email, password, full_name, reg_id, qr_code_data, role, department, is_active) VALUES
('bhagyashree.ambulkar', 'bhagyashree@attendify.com', @default_password, 'Bhagyashree Ambulkar', 'TEA2024001', 'QRT001', 'teacher', 'Computer Science', TRUE),
('rekha.israni', 'rekha@attendify.com', @default_password, 'Rekha Israni', 'TEA2024002', 'QRT002', 'teacher', 'Computer Science', TRUE),
('alina.sani', 'alina@attendify.com', @default_password, 'Alina Sani', 'TEA2024003', 'QRT003', 'teacher', 'Management', TRUE);

-- Insert Students with specific names
INSERT INTO users (username, email, password, full_name, reg_id, qr_code_data, role, branch, is_active) VALUES
('nilesh.gale', 'nilesh@student.attendify.com', @default_password, 'Nilesh Gale', 'SEE2004001', 'QRS2004001', 'student', 'Computer Science', TRUE),
('parinita.paigwar', 'parinita@student.attendify.com', @default_password, 'Parinita Paigwar', 'SEE2004002', 'QRS2004002', 'student', 'Computer Science', TRUE),
('mohit.mawre', 'mohit@student.attendify.com', @default_password, 'Mohit Mawre', 'SEE2004003', 'QRS2004003', 'student', 'Computer Science', TRUE),
('prajwal.pimple', 'prajwal@student.attendify.com', @default_password, 'Prajwal Pimple', 'SEE2004004', 'QRS2004004', 'student', 'Computer Science', TRUE),
('vivek.mandve', 'vivek@student.attendify.com', @default_password, 'Vivek Mandve', 'SEE2004005', 'QRS2004005', 'student', 'Management', TRUE),
('karan.konge', 'karan@student.attendify.com', @default_password, 'Karan Konge', 'SEE2004006', 'QRS2004006', 'student', 'Management', TRUE),
('tanvi.rane', 'tanvi@student.attendify.com', @default_password, 'Tanvi Rane', 'SEE2004007', 'QRS2004007', 'student', 'Computer Science', TRUE);

-- Insert Subjects
INSERT INTO subjects (subject_name, subject_code, department, credits) VALUES
('Java Programming', 'CS101', 'Computer Science', 4),
('SQL & Database', 'CS102', 'Computer Science', 4),
('Python Programming', 'CS103', 'Computer Science', 4),
('ICI (Internet & Computing)', 'CS104', 'Computer Science', 3),
('C Programming', 'CS105', 'Computer Science', 4),
('Cloud Computing', 'CS201', 'Computer Science', 3),
('E-Commerce', 'CS202', 'Computer Science', 3),
('PHP Web Development', 'CS203', 'Computer Science', 4),
('Operating System', 'CS204', 'Computer Science', 4),
('Operation Research', 'CS205', 'Computer Science', 3),
('Digital Marketing', 'MG101', 'Management', 3),
('Organisational Behaviour', 'MG102', 'Management', 3),
('Microsoft Excel', 'MG103', 'Management', 2),
('Sales Management', 'MG104', 'Management', 3);

-- Map Teachers to their Subjects
-- Bhagyashree Ambulkar teaches: Java, SQL, Python, ICI, C Programming
INSERT INTO teacher_subjects (teacher_id, subject_id) VALUES
(2, 1), (2, 2), (2, 3), (2, 4), (2, 5);

-- Rekha Israni teaches: Cloud Computing, E-Commerce, PHP, OS, Operation Research
INSERT INTO teacher_subjects (teacher_id, subject_id) VALUES
(3, 6), (3, 7), (3, 8), (3, 9), (3, 10);

-- Alina Sani teaches: Digital Marketing, Organisational Behaviour, Excel, Sales Management
INSERT INTO teacher_subjects (teacher_id, subject_id) VALUES
(4, 11), (4, 12), (4, 13), (4, 14);

-- Map Students to their Subjects
-- Nilesh Gale: SQL, Python, Operating System, PHP
INSERT INTO student_subjects (student_id, subject_id) VALUES
(5, 2), (5, 3), (5, 9), (5, 8);

-- Parinita Paigwar: SQL, Python, Operating System, PHP
INSERT INTO student_subjects (student_id, subject_id) VALUES
(6, 2), (6, 3), (6, 9), (6, 8);

-- Mohit Mawre: E-Commerce, Cloud Computing, Operation Research, Digital Marketing
INSERT INTO student_subjects (student_id, subject_id) VALUES
(7, 7), (7, 6), (7, 10), (7, 11);

-- Prajwal Pimple: Digital Marketing, Excel, Sales Management, Java
INSERT INTO student_subjects (student_id, subject_id) VALUES
(8, 11), (8, 13), (8, 14), (8, 1);

-- Vivek Mandve: Organisational Behaviour, Excel, Operation Research, Digital Marketing
INSERT INTO student_subjects (student_id, subject_id) VALUES
(9, 12), (9, 13), (9, 10), (9, 11);

-- Karan Konge: Excel, Sales Management, E-Commerce, Cloud Computing
INSERT INTO student_subjects (student_id, subject_id) VALUES
(10, 13), (10, 14), (10, 7), (10, 6);

-- Tanvi Rane: SQL, Python, Organisational Behaviour, Java
INSERT INTO student_subjects (student_id, subject_id) VALUES
(11, 2), (11, 3), (11, 12), (11, 1);

-- ============================================================================
-- USEFUL VIEWS
-- ============================================================================

-- View: Student Subject Allocation
CREATE OR REPLACE VIEW view_student_subjects AS
SELECT 
    u.id AS student_id,
    u.reg_id,
    u.full_name AS student_name,
    u.email,
    s.id AS subject_id,
    s.subject_name,
    s.subject_code,
    s.credits,
    ss.academic_year
FROM users u
JOIN student_subjects ss ON u.id = ss.student_id
JOIN subjects s ON ss.subject_id = s.id
WHERE u.role = 'student' AND u.is_active = TRUE
ORDER BY u.full_name, s.subject_name;

-- View: Teacher Subject Allocation
CREATE OR REPLACE VIEW view_teacher_subjects AS
SELECT 
    u.id AS teacher_id,
    u.reg_id,
    u.full_name AS teacher_name,
    u.email,
    s.id AS subject_id,
    s.subject_name,
    s.subject_code,
    ts.academic_year
FROM users u
JOIN teacher_subjects ts ON u.id = ts.teacher_id
JOIN subjects s ON ts.subject_id = s.id
WHERE u.role = 'teacher' AND u.is_active = TRUE
ORDER BY u.full_name, s.subject_name;

-- View: Student Attendance Summary
CREATE OR REPLACE VIEW view_student_attendance_summary AS
SELECT 
    u.id AS student_id,
    u.reg_id,
    u.full_name AS student_name,
    s.subject_name,
    s.subject_code,
    COUNT(a.id) AS total_classes,
    SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS present_count,
    SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) AS absent_count,
    ROUND((SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) / COUNT(a.id)) * 100, 2) AS attendance_percentage
FROM users u
JOIN attendance a ON u.id = a.student_id
JOIN subjects s ON a.subject_id = s.id
WHERE u.role = 'student'
GROUP BY u.id, u.reg_id, u.full_name, s.subject_name, s.subject_code;

-- ============================================================================
-- STORED PROCEDURES
-- ============================================================================

DELIMITER //

-- Generate Unique Attendance Code
CREATE PROCEDURE sp_generate_attendance_code(
    IN p_teacher_id INT,
    IN p_subject_id INT,
    IN p_session_date DATE,
    IN p_validity_minutes INT
)
BEGIN
    DECLARE v_unique_code VARCHAR(10);
    DECLARE v_expires_at DATETIME;
    
    -- Generate 6-character alphanumeric code
    SET v_unique_code = UPPER(SUBSTRING(MD5(RAND()), 1, 6));
    SET v_expires_at = DATE_ADD(NOW(), INTERVAL p_validity_minutes MINUTE);
    
    -- Deactivate old codes for this session
    UPDATE attendance_sessions 
    SET is_active = FALSE 
    WHERE teacher_id = p_teacher_id 
      AND subject_id = p_subject_id 
      AND session_date = p_session_date;
    
    -- Insert new code
    INSERT INTO attendance_sessions (teacher_id, subject_id, session_date, unique_code, code_expires_at)
    VALUES (p_teacher_id, p_subject_id, p_session_date, v_unique_code, v_expires_at);
    
    SELECT v_unique_code AS unique_code, v_expires_at AS expires_at;
END //

-- Mark Attendance via Unique Code
CREATE PROCEDURE sp_mark_attendance_by_code(
    IN p_student_id INT,
    IN p_unique_code VARCHAR(10)
)
BEGIN
    DECLARE v_session_id INT;
    DECLARE v_teacher_id INT;
    DECLARE v_subject_id INT;
    DECLARE v_session_date DATE;
    DECLARE v_is_valid BOOLEAN DEFAULT FALSE;
    
    -- Validate code
    SELECT id, teacher_id, subject_id, session_date
    INTO v_session_id, v_teacher_id, v_subject_id, v_session_date
    FROM attendance_sessions
    WHERE unique_code = p_unique_code
      AND is_active = TRUE
      AND code_expires_at > NOW()
    LIMIT 1;
    
    IF v_session_id IS NOT NULL THEN
        -- Check if student is enrolled in this subject
        IF EXISTS (
            SELECT 1 FROM student_subjects 
            WHERE student_id = p_student_id AND subject_id = v_subject_id
        ) THEN
            -- Mark attendance
            INSERT INTO attendance (student_id, subject_id, teacher_id, attendance_date, attendance_session_id, marking_method, status)
            VALUES (p_student_id, v_subject_id, v_teacher_id, v_session_date, v_session_id, 'unique_code', 'present')
            ON DUPLICATE KEY UPDATE 
                status = 'present',
                marking_method = 'unique_code',
                attendance_session_id = v_session_id,
                marked_at = NOW();
            
            SELECT 'success' AS status, 'Attendance marked successfully' AS message;
        ELSE
            SELECT 'error' AS status, 'You are not enrolled in this subject' AS message;
        END IF;
    ELSE
        SELECT 'error' AS status, 'Invalid or expired code' AS message;
    END IF;
END //

-- Increase Attendance Percentage
CREATE PROCEDURE sp_increase_attendance(
    IN p_student_id INT,
    IN p_subject_id INT,
    IN p_percentage_increase DECIMAL(5,2)
)
BEGIN
    DECLARE v_total_classes INT;
    DECLARE v_present_count INT;
    DECLARE v_current_percentage DECIMAL(5,2);
    DECLARE v_target_percentage DECIMAL(5,2);
    DECLARE v_classes_to_add INT;
    DECLARE v_counter INT DEFAULT 0;
    DECLARE v_attendance_date DATE;
    
    -- Get current stats
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) AS present,
        ROUND((SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) AS percentage
    INTO v_total_classes, v_present_count, v_current_percentage
    FROM attendance
    WHERE student_id = p_student_id AND subject_id = p_subject_id;
    
    SET v_target_percentage = v_current_percentage + p_percentage_increase;
    
    IF v_target_percentage > 100 THEN
        SET v_target_percentage = 100;
    END IF;
    
    -- Calculate classes to add
    SET v_classes_to_add = CEIL((v_target_percentage * (v_total_classes + v_present_count) - 100 * v_present_count) / (100 - v_target_percentage));
    
    -- Add present attendance records
    SET v_attendance_date = CURDATE();
    WHILE v_counter < v_classes_to_add DO
        SET v_attendance_date = DATE_ADD(v_attendance_date, INTERVAL 1 DAY);
        
        INSERT IGNORE INTO attendance (student_id, subject_id, attendance_date, status, marking_method)
        VALUES (p_student_id, p_subject_id, v_attendance_date, 'present', 'manual');
        
        SET v_counter = v_counter + 1;
    END WHILE;
    
    SELECT 'success' AS status, 
           CONCAT('Added ', v_classes_to_add, ' classes to increase attendance') AS message,
           v_classes_to_add AS classes_added;
END //

DELIMITER ;

-- ============================================================================
-- SUCCESS MESSAGE
-- ============================================================================
SELECT '✓ Attendify Enhanced Database Created Successfully!' AS status;
SELECT 'Default Password for all users: admin123' AS note;
SELECT '⚠️  Change passwords after first login!' AS warning;
