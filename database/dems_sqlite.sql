DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS employee_loads;
DROP TABLE IF EXISTS feedback;
DROP TABLE IF EXISTS schedules;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS announcements;
DROP TABLE IF EXISTS submissions;
DROP TABLE IF EXISTS assignments;
DROP TABLE IF EXISTS modules;
DROP TABLE IF EXISTS courses;
DROP TABLE IF EXISTS instructors;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS roles;

CREATE TABLE roles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(50) NOT NULL UNIQUE,
    label VARCHAR(100) NOT NULL
);

CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    role_id INT NOT NULL,
    full_name VARCHAR(160) NOT NULL,
    username VARCHAR(80) NOT NULL UNIQUE,
    email VARCHAR(160) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(40) NULL,
    status TEXT NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (role_id) REFERENCES roles(id)
);

CREATE TABLE students (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INT NOT NULL UNIQUE,
    student_no VARCHAR(40) NOT NULL UNIQUE,
    program VARCHAR(120) NOT NULL,
    year_level INT NOT NULL DEFAULT 1,
    semester INT NOT NULL DEFAULT 1,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE instructors (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INT NOT NULL UNIQUE,
    employee_no VARCHAR(40) NOT NULL UNIQUE,
    department VARCHAR(120) NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE courses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code VARCHAR(30) NOT NULL UNIQUE,
    title VARCHAR(160) NOT NULL,
    credits INT NOT NULL DEFAULT 3,
    department VARCHAR(120) NOT NULL,
    instructor_id INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (instructor_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE modules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    course_id INT NOT NULL,
    uploaded_by INT NOT NULL,
    title VARCHAR(180) NOT NULL,
    description TEXT NULL,
    file_path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
);

CREATE TABLE assignments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    course_id INT NOT NULL,
    uploaded_by INT NOT NULL,
    title VARCHAR(180) NOT NULL,
    description TEXT NULL,
    due_date DATE NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
);

CREATE TABLE submissions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    assignment_id INT NOT NULL,
    student_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    grade DECIMAL(5,2) NULL,
    feedback TEXT NULL,
    graded_by INT NULL,
    approval_status TEXT NOT NULL DEFAULT 'pending',
    approved_by INT NULL,
    approved_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE (assignment_id, student_id),
    FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (graded_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE announcements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title VARCHAR(180) NOT NULL,
    body TEXT NOT NULL,
    audience TEXT NOT NULL DEFAULT 'all',
    posted_by INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (posted_by) REFERENCES users(id)
);

CREATE TABLE payments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    student_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending',
    receipt_no VARCHAR(80) NULL,
    verified_by INT NULL,
    paid_at DATE NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE schedules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title VARCHAR(180) NOT NULL,
    event_type TEXT NOT NULL,
    event_date DATE NOT NULL,
    details TEXT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE feedback (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    student_id INT NOT NULL,
    subject VARCHAR(180) NOT NULL,
    message TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'open',
    response TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

CREATE TABLE employee_loads (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INT NOT NULL,
    period_label VARCHAR(80) NOT NULL,
    hours_worked DECIMAL(8,2) NOT NULL DEFAULT 0,
    status TEXT NOT NULL DEFAULT 'draft',
    submitted_by INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (submitted_by) REFERENCES users(id)
);

CREATE TABLE activity_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INT NULL,
    action VARCHAR(160) NOT NULL,
    details TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

INSERT INTO roles (id, name, label) VALUES
(1, 'administrator', 'Administrator'),
(2, 'student', 'Student'),
(3, 'instructor', 'Instructor'),
(4, 'cde_officer', 'CDE Officer'),
(5, 'registrar', 'Registrar Officer'),
(6, 'finance', 'Finance Staff'),
(7, 'department_head', 'Department Head'),
(8, 'academic_vp', 'Academic Vice President'),
(9, 'college_dean', 'College Dean');

INSERT INTO users (id, role_id, full_name, username, email, password_hash, phone, status) VALUES
(1, 1, 'System Administrator', 'admin', 'admin@nac.edu.et', 'sha256:d3ad9315b7be5dd53b31a273b3b3aba5defe700808305aa16a3062b76658a791', '+251911000001', 'active'),
(2, 2, 'Eyob Terefe', 'student', 'student@nac.edu.et', 'sha256:d3ad9315b7be5dd53b31a273b3b3aba5defe700808305aa16a3062b76658a791', '+251911000002', 'active'),
(3, 3, 'Alemu Bekele', 'instructor', 'instructor@nac.edu.et', 'sha256:d3ad9315b7be5dd53b31a273b3b3aba5defe700808305aa16a3062b76658a791', '+251911000003', 'active'),
(4, 4, 'Mekdes Tesfaye', 'cde', 'cde@nac.edu.et', 'sha256:d3ad9315b7be5dd53b31a273b3b3aba5defe700808305aa16a3062b76658a791', '+251911000004', 'active'),
(5, 5, 'Registrar Officer', 'registrar', 'registrar@nac.edu.et', 'sha256:d3ad9315b7be5dd53b31a273b3b3aba5defe700808305aa16a3062b76658a791', '+251911000005', 'active'),
(6, 6, 'Finance Staff', 'finance', 'finance@nac.edu.et', 'sha256:d3ad9315b7be5dd53b31a273b3b3aba5defe700808305aa16a3062b76658a791', '+251911000006', 'active'),
(7, 7, 'Department Head', 'depthead', 'depthead@nac.edu.et', 'sha256:d3ad9315b7be5dd53b31a273b3b3aba5defe700808305aa16a3062b76658a791', '+251911000007', 'active'),
(8, 8, 'Academic Vice President', 'avp', 'avp@nac.edu.et', 'sha256:d3ad9315b7be5dd53b31a273b3b3aba5defe700808305aa16a3062b76658a791', '+251911000008', 'active'),
(9, 9, 'College Dean', 'dean', 'dean@nac.edu.et', 'sha256:d3ad9315b7be5dd53b31a273b3b3aba5defe700808305aa16a3062b76658a791', '+251911000009', 'active');

INSERT INTO students (id, user_id, student_no, program, year_level, semester) VALUES
(1, 2, '15,923,22', 'Computer Science', 3, 2);

INSERT INTO instructors (id, user_id, employee_no, department) VALUES
(1, 3, 'EMP-1021', 'Computer Science');

INSERT INTO courses (id, code, title, credits, department, instructor_id) VALUES
(1, 'CS301', 'Database Systems', 3, 'Computer Science', 3),
(2, 'CS302', 'Web Programming', 3, 'Computer Science', 3),
(3, 'MGT201', 'Principles of Management', 3, 'Management', NULL);

INSERT INTO modules (id, course_id, uploaded_by, title, description, file_path) VALUES
(1, 1, 3, 'Introduction to Relational Databases', 'Module for database concepts and MySQL basics.', 'modules/sample_database_module.txt');

INSERT INTO assignments (id, course_id, uploaded_by, title, description, due_date, file_path) VALUES
(1, 1, 3, 'Database Design Assignment', 'Prepare an ER model and explain the main tables for a distance education system.', date('now', '+14 days'), 'assignments/sample_database_assignment.txt');

INSERT INTO submissions (id, assignment_id, student_id, file_path, grade, feedback, graded_by, approval_status) VALUES
(1, 1, 1, 'submissions/sample_submission.txt', 88.00, 'Good structure. Improve explanation of relationships.', 3, 'pending');

INSERT INTO announcements (title, body, audience, posted_by) VALUES
('Registration Week Open', 'Distance education registration is open this week. Students should check payment status before course access.', 'all', 4),
('Database Systems Assignment Posted', 'Students enrolled in Database Systems should download the first assignment and submit before the deadline.', 'students', 3);

INSERT INTO payments (student_id, amount, status, receipt_no, verified_by, paid_at) VALUES
(1, 2500.00, 'paid', 'NAC-REC-1001', 6, date('now'));

INSERT INTO schedules (title, event_type, event_date, details, created_by) VALUES
('Module Distribution Schedule', 'module', date('now', '+3 days'), 'First round modules will be available online.', 4),
('Mid Semester Examination', 'exam', date('now', '+30 days'), 'Exam timetable will be confirmed by the registrar.', 5);

INSERT INTO feedback (student_id, subject, message, status) VALUES
(1, 'Module access', 'The online module download page is useful for distance learners.', 'reviewed');

INSERT INTO employee_loads (user_id, period_label, hours_worked, status, submitted_by) VALUES
(3, 'March 2026', 42.00, 'submitted', 7);

INSERT INTO activity_logs (user_id, action, details) VALUES
(1, 'seed', 'Initial class project demo data loaded.');
