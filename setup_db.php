<?php
// setup_db.php
require_once 'config.php';

echo "<h2>Initializing Database Setup</h2>";

try {
    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS erp_system DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE erp_system");
    echo "<p>Database 'erp_system' created or already exists.</p>";

    // Drop existing tables to ensure a clean migrations structure
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $tables = [
        'assignment_submissions', 'assignments', 'leave_requests', 'grievances',
        'notices', 'notifications', 'subjects', 'divisions', 'departments',
        'students', 'faculty', 'hod', 'admin', 'settings', 'users',
        'subject_assignments', 'assignment_grievances'
    ];
    foreach ($tables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS `$table` CASCADE");
    }
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    echo "<p>Old tables cleaned up.</p>";

    // Create departments table
    $pdo->exec("CREATE TABLE IF NOT EXISTS departments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        code VARCHAR(20) NOT NULL UNIQUE,
        intake INT DEFAULT 120,
        hod_name VARCHAR(100) NULL
    )");
    echo "<p>Table 'departments' created.</p>";

    // Create divisions table
    $pdo->exec("CREATE TABLE IF NOT EXISTS divisions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        department_id INT,
        name VARCHAR(10) NOT NULL,
        FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
    )");
    echo "<p>Table 'divisions' created.</p>";

    // Create subjects table
    $pdo->exec("CREATE TABLE IF NOT EXISTS subjects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        department_id INT,
        name VARCHAR(100) NOT NULL,
        code VARCHAR(20) NULL,
        FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
    )");
    echo "<p>Table 'subjects' created.</p>";

    // Create students table
    $pdo->exec("CREATE TABLE IF NOT EXISTS students (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_name VARCHAR(255) NOT NULL,
        zprn VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        department VARCHAR(100) NOT NULL,
        division VARCHAR(10) NOT NULL,
        semester VARCHAR(50) NOT NULL,
        email VARCHAR(100) NULL,
        mobile VARCHAR(20) NULL,
        role VARCHAR(20) DEFAULT 'STUDENT',
        must_change_password TINYINT(1) DEFAULT 0,
        attendance VARCHAR(10) DEFAULT NULL,
        status VARCHAR(20) DEFAULT 'Active',
        avatar VARCHAR(255) DEFAULT '',
        profile_details LONGTEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    echo "<p>Table 'students' created.</p>";

    // Create faculty table
    $pdo->exec("CREATE TABLE IF NOT EXISTS faculty (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(100) NULL,
        phone VARCHAR(20) NULL,
        designation VARCHAR(100) DEFAULT 'Assistant Professor',
        workload VARCHAR(50) DEFAULT '16 Hours / Week',
        attendance VARCHAR(10) DEFAULT NULL,
        subjects VARCHAR(255) NULL,
        avatar VARCHAR(255) DEFAULT '',
        role VARCHAR(20) DEFAULT 'FACULTY',
        assigned_divisions VARCHAR(255) DEFAULT 'A,B,C',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    echo "<p>Table 'faculty' created.</p>";

    // Create hod table
    $pdo->exec("CREATE TABLE IF NOT EXISTS hod (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(100) NULL,
        phone VARCHAR(20) NULL,
        designation VARCHAR(100) DEFAULT 'Professor & HOD',
        workload VARCHAR(50) DEFAULT '8 Hours / Week',
        attendance VARCHAR(10) DEFAULT NULL,
        subjects VARCHAR(255) NULL,
        avatar VARCHAR(255) DEFAULT '',
        role VARCHAR(20) DEFAULT 'HOD',
        department VARCHAR(100) DEFAULT 'Information Technology',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    echo "<p>Table 'hod' created.</p>";

    // Create admin table
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(100) NULL,
        role VARCHAR(20) DEFAULT 'ADMIN',
        avatar VARCHAR(255) DEFAULT '',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    echo "<p>Table 'admin' created.</p>";

    // Create notices table
    $pdo->exec("CREATE TABLE IF NOT EXISTS notices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        author VARCHAR(100) NOT NULL,
        role VARCHAR(100) NOT NULL,
        date_posted VARCHAR(50) NOT NULL,
        expiry VARCHAR(50) NULL,
        attachment VARCHAR(255) NULL,
        size VARCHAR(50) NULL
    )");
    echo "<p>Table 'notices' created.</p>";

    // Create leave_requests table
    $pdo->exec("CREATE TABLE IF NOT EXISTS leave_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(50) NULL,
        applicant_name VARCHAR(255) NOT NULL,
        applicant_role VARCHAR(50) NOT NULL,
        file VARCHAR(255) NOT NULL,
        reason VARCHAR(255) NOT NULL,
        from_date VARCHAR(50) NOT NULL,
        to_date VARCHAR(50) NOT NULL,
        status VARCHAR(50) DEFAULT 'Pending',
        remarks TEXT NULL
    )");
    echo "<p>Table 'leave_requests' created.</p>";

    // Create grievances table
    $pdo->exec("CREATE TABLE IF NOT EXISTS grievances (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(50) NOT NULL,
        student_name VARCHAR(255) NOT NULL,
        title VARCHAR(255) NOT NULL,
        category VARCHAR(100) NOT NULL,
        description TEXT NOT NULL,
        date_posted VARCHAR(50) NOT NULL,
        status VARCHAR(50) DEFAULT 'Pending',
        replies JSON NULL
    )");
    echo "<p>Table 'grievances' created.</p>";

    // Create assignments table (units table)
    $pdo->exec("CREATE TABLE IF NOT EXISTS assignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        unit INT NOT NULL UNIQUE,
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL
    )");
    echo "<p>Table 'assignments' created.</p>";

    // Create subject_assignments table
    $pdo->exec("CREATE TABLE IF NOT EXISTS subject_assignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        assignment_id INT NOT NULL,
        subject_name VARCHAR(255) NOT NULL,
        assignment_title VARCHAR(255) NOT NULL,
        question_pdf VARCHAR(255) NOT NULL,
        due_date VARCHAR(50) NOT NULL,
        created_by VARCHAR(255) NOT NULL,
        department VARCHAR(100) NOT NULL,
        division VARCHAR(50) NOT NULL,
        semester VARCHAR(50) NOT NULL,
        description TEXT NULL,
        published_date VARCHAR(50) NULL,
        FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE
    )");
    echo "<p>Table 'subject_assignments' created.</p>";

    // Create assignment_submissions table
    $pdo->exec("CREATE TABLE IF NOT EXISTS assignment_submissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        submission_id VARCHAR(100) NOT NULL UNIQUE,
        subject_assignment_id INT NOT NULL,
        student_id VARCHAR(50) NOT NULL,
        student_name VARCHAR(255) NOT NULL,
        subject_id VARCHAR(255) NOT NULL,
        unit INT NOT NULL,
        file_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        file_type VARCHAR(50) NOT NULL,
        file_size VARCHAR(50) NOT NULL,
        submitted_at VARCHAR(50) NOT NULL,
        status VARCHAR(50) DEFAULT 'Submitted',
        marks VARCHAR(50) DEFAULT 'Pending',
        remarks TEXT NULL,
        evaluated_at VARCHAR(50) NULL,
        history JSON NULL,
        FOREIGN KEY (subject_assignment_id) REFERENCES subject_assignments(id) ON DELETE CASCADE
    )");
    echo "<p>Table 'assignment_submissions' created.</p>";

    // Create assignment_grievances table
    $pdo->exec("CREATE TABLE IF NOT EXISTS assignment_grievances (
        id INT AUTO_INCREMENT PRIMARY KEY,
        subject_assignment_id INT NOT NULL,
        student_id VARCHAR(50) NOT NULL,
        student_name VARCHAR(255) NOT NULL,
        issue_type VARCHAR(100) NOT NULL,
        description TEXT NOT NULL,
        screenshot VARCHAR(255) NULL,
        status VARCHAR(50) DEFAULT 'Pending',
        reply TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (subject_assignment_id) REFERENCES subject_assignments(id) ON DELETE CASCADE
    )");
    echo "<p>Table 'assignment_grievances' created.</p>";

    // Create notifications table
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        time VARCHAR(100) NOT NULL
    )");
    echo "<p>Table 'notifications' created.</p>";

    // Create settings table
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL UNIQUE,
        setting_value TEXT NULL
    )");
    echo "<p>Table 'settings' created.</p>";


    // Insert Seed Data for Departments & Divisions
    $pdo->exec("INSERT INTO departments (name, code, intake, hod_name) VALUES 
        ('Information Technology', 'IT-ENGG', 120, 'Prof. Amit Deshmukh'),
        ('Computer Engineering', 'CE-ENGG', 180, 'Dr. Neha Sharma')");
    $it_dept_id = $pdo->lastInsertId();
    $pdo->exec("INSERT INTO divisions (department_id, name) VALUES 
        ($it_dept_id, 'A'),
        ($it_dept_id, 'B'),
        ($it_dept_id, 'C')");
    echo "<p>Departments and Divisions populated.</p>";

    // Seed 6 Units in assignments table
    $pdo->exec("INSERT INTO assignments (unit, title, description) VALUES 
        (1, 'Unit 1 - Introduction to Basics', 'Foundational concepts and intro material.'),
        (2, 'Unit 2 - Data Structures', 'Basic data structure concepts.'),
        (3, 'Unit 3 - Object Oriented Programming', 'OOP paradigms, classes, and objects.'),
        (4, 'Unit 4 - Database Management Systems', 'RDBMS, SQL, and database design.'),
        (5, 'Unit 5 - Operating Systems', 'Kernels, processes, memory management.'),
        (6, 'Unit 6 - Computer Networks', 'OSI model, networking protocols.')
        ON DUPLICATE KEY UPDATE title=VALUES(title), description=VALUES(description)");
    echo "<p>6 Units populated in assignments table.</p>";

    // Seed 5 Subjects in subjects table under Information Technology department
    $pdo->exec("INSERT INTO subjects (name, code, department_id) VALUES 
        ('Foundations of C++ Programming (C++)', 'IT-CPP', $it_dept_id),
        ('Engineering Mathematics II (EM-II)', 'IT-EM2', $it_dept_id),
        ('Fundamentals of Computer Systems & Networking (FCSN)', 'IT-FCSN', $it_dept_id),
        ('Engineering Physics (EP)', 'IT-EP', $it_dept_id),
        ('Digital Systems Design & Architecture (DSDA)', 'IT-DSDA', $it_dept_id)
        ON DUPLICATE KEY UPDATE name=VALUES(name), department_id=VALUES(department_id)");
    echo "<p>5 Subjects populated in subjects table.</p>";

    // Seed Staff Accounts
    $default_pass = password_hash('12345678', PASSWORD_BCRYPT);
    $admin_pass = password_hash('12345', PASSWORD_BCRYPT);

    $pdo->prepare("INSERT INTO admin (username, password, name, email, avatar) VALUES (?, ?, ?, ?, ?)")
        ->execute(['admin1', $admin_pass, 'System Admin', 'admin@erp.edu', 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?q=80&w=150&auto=format&fit=crop']);

    $pdo->prepare("INSERT INTO hod (username, password, name, email, phone, subjects, avatar) VALUES (?, ?, ?, ?, ?, ?, ?)")
        ->execute(['hod1', $default_pass, 'Prof. Amit Deshmukh', 'amit.deshmukh@erp.edu', '+91 93344 55667', 'Operating Systems, Software Engineering', 'https://images.unsplash.com/photo-1560250097-0b93528c311a?q=80&w=150&auto=format&fit=crop']);

    $insert_faculty = $pdo->prepare("INSERT INTO faculty (username, password, name, email, phone, designation, workload, attendance, subjects, avatar, assigned_divisions) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    // 1. Ms. Priyanka Patil
    $insert_faculty->execute([
        'em2.faculty', $default_pass, 'Ms. Priyanka Patil', 'em2.faculty@erp.edu', '+91 91122 33001', 
        'Assistant Professor', '16 Hours / Week', null, 'Engineering Mathematics II (EM-II)', 
        'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?q=80&w=150&auto=format&fit=crop', 'A,B,C'
    ]);
    
    // 2. Dr. Yogesh Sonawane
    $insert_faculty->execute([
        'physics.faculty', $default_pass, 'Dr. Yogesh Sonawane', 'physics.faculty@erp.edu', '+91 91122 33002', 
        'Associate Professor', '14 Hours / Week', null, 'Engineering Physics (EP)', 
        'https://images.unsplash.com/photo-1560250097-0b93528c311a?q=80&w=150&auto=format&fit=crop', 'A,B,C'
    ]);
    
    // 3. Dr. Ashwini Kumar Mishra
    $insert_faculty->execute([
        'dsda.faculty', $default_pass, 'Dr. Ashwini Kumar Mishra', 'dsda.faculty@erp.edu', '+91 91122 33003', 
        'Professor', '12 Hours / Week', null, 'Digital Systems Design & Architecture (DSDA)', 
        'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?q=80&w=150&auto=format&fit=crop', 'A,B,C'
    ]);
    
    // 4. Mr. Karan Jadhav
    $insert_faculty->execute([
        'cpp.faculty', $default_pass, 'Mr. Karan Jadhav', 'cpp.faculty@erp.edu', '+91 91122 33004', 
        'Assistant Professor', '16 Hours / Week', null, 'Foundations of C++ Programming (C++)', 
        'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?q=80&w=150&auto=format&fit=crop', 'A,B,C'
    ]);
    
    // 5. Mr. Sumesh Shinde
    $insert_faculty->execute([
        'fcsn.faculty', $default_pass, 'Mr. Sumesh Shinde', 'fcsn.faculty@erp.edu', '+91 91122 33005', 
        'Assistant Professor', '16 Hours / Week', null, 'Fundamentals of Computer Systems & Networking (FCSN)', 
        'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?q=80&w=150&auto=format&fit=crop', 'A,B,C'
    ]);
    
    echo "<p>HOD and Faculty seeded.</p>";

    // Seed Students
    $students_list = [
        // Division A
        ['SURVE VALLABH KSHITIJ', '125UIT1086', 'A'],
        ['SWARANJALI OMPRAKASH GHODKE', '125UIT1145', 'A'],
        ['SWAROOP SATISH PARDESHI', '125UIT1206', 'A'],
        ['TANTAK PARTH NITIN', '125UIT1002', 'A'],
        ['UGLE MAYURI MAROTI', '125UIT1022', 'A'],
        ['UNDALE SOHAM SHASHIKANT', '125UIT1195', 'A'],
        ['VAISHNAVI AVINASH KANDHARE', '125UIT1156', 'A'],
        ['WAGHMALE SRUSHTI SANTOSH', '125UIT1149', 'A'],

        // Division B
        ['KHEDKAR SURAJ UDDHAV', '125UIT1204', 'B'],
        ['KHODADE SAMRUDDHI SHAILESH', '125UIT1131', 'B'],
        ['KHODE RIDDHI KRUSHNA', '125UIT1016', 'B'],
        ['LAHAMGE SHUBHAM PRAVIN', '125UIT1001', 'B'],
        ['MACHAREKAR ARNAV RAJAN', '125UIT1211', 'B'],
        ['MAHURE ADITYA SUNIL', '125UIT1035', 'B'],
        ['MOMIN BUSHARA MEHMUD', '125UIT1197', 'B'],
        ['OROKAR MAHESHWARI SANDIP', '125UIT1038', 'B'],
        ['PADWAL SURAJ NARESH', '125UIT1048', 'B'],
        ['PANCHAL ROHAN RAMESH', '125UIT1111', 'B'],
        ['PATIL ROHINI GAUDAPPA', '125UIT1040', 'B'],
        ['PAWAR KARAN BABURAO', '125UIT1076', 'B'],
        ['PAWAR SATYAM HIRAMAN', '125UIT1067', 'B'],
        ['PAYGUDE SHUBHANGI VIJAY', '125UIT1118', 'B'],
        ['POTDAR TUSHAR VIVEK', '125UIT1139', 'B'],
        ['POTRAJE RUCHIRA RAVI', '125UIT1075', 'B'],
        ['PRASAD VIVEK KULKARNI', '125UIT1187', 'B'],
        ['RAMGUDE PAVAN AMAR', '125UIT1155', 'B'],
        ['RATHOD DISHA AVINASH', '125UIT1108', 'B'],
        ['RATHOD RAJ NEPAL', '125UIT1043', 'B'],
        ['RUTUJA SANJIVKUMAR PANCHAL', '125UIT1182', 'B'],
        ['SADICHHA KALIDAS PAWAR', '125UIT1199', 'B'],
        ['SATPUTE ADITYA PANDHARINATH', '125UIT1132', 'B'],
        ['SHEVARE AJAY VINOD', '125UIT1080', 'B'],
        ['SHINDE TEJASWINI PRAKASH', '125UIT1171', 'B'],
        ['SHRAWANI KRISHNA ROKDE', '125UIT1180', 'B'],
        ['SHRINIDHI MADHAV SHINDE', '125UIT1004', 'B'],
        ['SHUBHAM MACHHINDRA SURVASE', '125UIT1120', 'B'],
        ['SIDDHESH VINODKUMAR DHAVALE', '125UIT1209', 'B'],
        ['SINGH NAVYA RAJKUMAR', '125UIT1110', 'B'],
        ['SOLANKAR SOHAM SIDDHESHWAR', '125UIT1024', 'B'],
        ['SUCHITA RAMCHANDRA SIHORE', '125UIT1140', 'B'],

        // Division C
        ['ADE ISHWARI SHAMRAO', '125UIT1103', 'C'],
        ['BADAK ROHIT ASHOK', '125UIT1095', 'C'],
        ['BANKAR PRERANA SANDIP', '125UIT1036', 'C'],
        ['BARGAJE GAYATRY SANTOSH', '125UIT1029', 'C'],
        ['BHAVSAR SOHAM ABHAY', '125UIT1090', 'C'],
        ['BORKAR AYUSH RAMBHAU', '125UIT1148', 'C'],
        ['CHAITANYA JYOTIRAM BHOSALE', '125UIT1061', 'C'],
        ['CHOLE PRANAV SHARAD', '125UIT1137', 'C'],
        ['DALVE PRUTHVIRAJ AMAR', '125UIT1074', 'C'],
        ['DAVHALE SUMIT KISHOR', '125UIT1147', 'C'],
        ['DHADVE MADHUR SANJAY', '125UIT1189', 'C'],
        ['DIVY ANIL KOKATE', '125UIT1092', 'C'],
        ['DIXIT SHIVAMSINGH NARENDRASINGH', '125UIT1165', 'C'],
        ['DOLASE PRANAV PRAVIN', '125UIT1122', 'C'],
        ['GADAKH RISHI ASHOK', '125UIT1107', 'C'],
        ['GARJE SUJIT ARUN', '125UIT1065', 'C'],
        ['GAURAV RAJENDRA NANVARE', '125UIT1125', 'C'],
        ['GHARAT SWARA SANTOSH', '125UIT1212', 'C'],
        ['GHULE ARJUN GAJANAN', '125UIT1019', 'C'],
        ['GODAMBE SWARAJ VISHNU', '125UIT1130', 'C'],
        ['GOUR YUVRAJSINHA RAVINDRA', '125UIT1033', 'C'],
        ['INGLE PRUTHVIRAJ PRABHU', '125UIT1116', 'C'],
        ['KADAM PRANAV PREMNATH', '125UIT1085', 'C'],
        ['KALAMKAR RASIKA SUHAS', '125UIT1017', 'C'],
        ['KANHAIYA KISHOR MARATHE', '125UIT1066', 'C'],
        ['KAWADE AYUSHI KIRAN', '125UIT1192', 'C']
    ];

    $insert_student = $pdo->prepare("INSERT INTO students (student_name, zprn, password, department, division, semester, email, mobile, must_change_password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($students_list as $s) {
        $student_name = $s[0];
        $zprn = $s[1];
        $div = $s[2];
        $hashed = password_hash($zprn, PASSWORD_BCRYPT);
        $student_parts = explode(' ', $student_name);
        $email = strtolower($student_parts[0]) . '.' . strtolower(end($student_parts)) . '@erp.edu';
        $mobile = '+91 9' . rand(10000000, 99999999);
        $insert_student->execute([$student_name, $zprn, $hashed, 'Information Technology', $div, '5th Semester', $email, $mobile, 0]);
    }
    echo "<p>Seeded " . count($students_list) . " students successfully.</p>";

    // Seed Sample Notices
    $pdo->exec("INSERT INTO notices (title, description, author, role, date_posted, expiry, attachment, size) VALUES 
        ('Internal Exam Schedule', 'Internal examinations will be held from 20th July 2026. Please check the timetable.', 'Ms. Priyanka Patil', 'Faculty', '2026-07-15 10:30:00', '2026-08-30', 'schedule.pdf', '245 KB'),
        ('Project Submission', 'Final year project reports to be submitted by 5th August 2026.', 'Dr. Yogesh Sonawane', 'Faculty', '2026-07-14 14:15:00', '2026-08-05', 'guidelines.docx', '512 KB'),
        ('Holiday Notice', 'College will remain closed on 18th July 2026 on account of Muharram.', 'Admin Office', 'Administration', '2026-07-12 09:00:00', '2026-07-19', '', '')");
    echo "<p>Sample notices inserted.</p>";

    // No dummy/mock assignments seeded. Assignments must be published by faculty before they appear.

    // Seed Sample Leaves
    $pdo->exec("INSERT INTO leave_requests (student_id, applicant_name, applicant_role, file, reason, from_date, to_date, status, remarks) VALUES 
        ('125UIT1080', 'SHEVARE AJAY VINOD', 'Student', 'Leave_Form_15_Jan_2026.pdf', 'Medical', '15 Jan 2026', '17 Jan 2026', 'Approved', 'Approved based on medical certificate.'),
        ('125UIT1080', 'SHEVARE AJAY VINOD', 'Student', 'Leave_Form_02_Feb_2026.docx', 'Student', '02 Feb 2026', '03 Feb 2026', 'Pending', '')");
    echo "<p>Sample leaves inserted.</p>";

    // Seed Sample Grievance
    $pdo->exec("INSERT INTO grievances (student_id, student_name, title, category, description, date_posted, status, replies) VALUES 
        ('125UIT1080', 'SHEVARE AJAY VINOD', 'Wi-Fi Issues in Lab 2', 'Infrastructure', 'The Wi-Fi connection in Computer Lab 2 is extremely unstable.', '16 Jul 2026 11:30 AM', 'In Progress', '[{\"author\": \"Prof. Amit Deshmukh\", \"role\": \"HOD\", \"message\": \"I have escalated this issue to the IT systems team.\", \"date\": \"16 Jul 2026 03:45 PM\"}]')");
    echo "<p>Sample grievances inserted.</p>";

    // Seed Notifications
    $pdo->exec("INSERT INTO notifications (title, description, time) VALUES 
        ('New Leave Application', 'SHEVARE AJAY VINOD applied for Medical Leave', '2 hours ago'),
        ('Notice Published', 'Ms. Priyanka Patil published \"Internal Exam Schedule\"', '4 hours ago')");
    echo "<p>Sample notifications inserted.</p>";

    // Seed Settings
    $pdo->exec("INSERT INTO settings (setting_key, setting_value) VALUES 
        ('dept_name', 'Information Technology'),
        ('dept_code', 'IT-ENGG'),
        ('intake', '120'),
        ('hod_name', 'Prof. Amit Deshmukh'),
        ('hod_email', 'amit.deshmukh@erp.edu'),
        ('hod_phone', '+91 93344 55667'),
        ('notifications_enabled', '1'),
        ('captcha_enabled', '1'),
        ('maintenance_mode', '0')");
    echo "<p>Global settings seeded.</p>";

    echo "<h3>Setup Complete! <a href='login.php'>Go to Login</a></h3>";

} catch(PDOException $e) {
    die("Database setup failed: " . $e->getMessage());
}
?>
