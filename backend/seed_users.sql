-- ============================================================================
-- ATTENDIFY - DEFAULT USERS SEED
-- Password for all users: admin123
-- ============================================================================

INSERT IGNORE INTO users (username, email, password, full_name, reg_id, role, department)
VALUES 
('admin', 'admin@attendify.com', '$2y$10$J/R5tPRXtLwWWXMN8CDReOi9A6YqKR5LzL4CpPUQ4Yi0SA4Dgc.eG', 'System Admin', 'ADMIN2024001', 'admin', 'Administration'),
('teacher1', 'teacher1@attendify.com', '$2y$10$J/R5tPRXtLwWWXMN8CDReOi9A6YqKR5LzL4CpPUQ4Yi0SA4Dgc.eG', 'Dr. Smith', 'TEA2024001', 'teacher', 'Computer Science'),
('student1', 'student1@attendify.com', '$2y$10$J/R5tPRXtLwWWXMN8CDReOi9A6YqKR5LzL4CpPUQ4Yi0SA4Dgc.eG', 'John Doe', 'SEE2004001', 'student', 'Computer Science');
