-- Database structure for ENSA Projects Management Application

-- Create database
CREATE DATABASE IF NOT EXISTS ensa_project_db;
USE ensa_project_db;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('student', 'teacher', 'admin') NOT NULL,
    department VARCHAR(100),
    year_of_study ENUM('3', '4', '5') DEFAULT NULL,
    creation_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    status BOOLEAN DEFAULT TRUE
);

-- Project categories table
CREATE TABLE IF NOT EXISTS project_categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT
);

-- Project types table
CREATE TABLE IF NOT EXISTS project_types (
    type_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT
);

-- Modules table
CREATE TABLE IF NOT EXISTS modules (
    module_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    semester INT,
    year VARCHAR(20)
);

-- Projects table
CREATE TABLE IF NOT EXISTS projects (
    project_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    student_id INT NOT NULL,
    supervisor_id INT,
    category_id INT,
    type_id INT NOT NULL,
    module_id INT,
    submission_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('submitted', 'approved', 'rejected', 'pending_revision') DEFAULT 'submitted',
    academic_year VARCHAR(20),
    FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (supervisor_id) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (category_id) REFERENCES project_categories(category_id) ON DELETE SET NULL,
    FOREIGN KEY (type_id) REFERENCES project_types(type_id) ON DELETE CASCADE,
    FOREIGN KEY (module_id) REFERENCES modules(module_id) ON DELETE SET NULL
);

-- Project deliverables table
CREATE TABLE IF NOT EXISTS deliverables (
    deliverable_id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_type VARCHAR(50) NOT NULL,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    description TEXT,
    FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE CASCADE
);

-- Project evaluations table
CREATE TABLE IF NOT EXISTS evaluations (
    evaluation_id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    evaluator_id INT NOT NULL,
    comments TEXT,
    grade DECIMAL(5,2),
    evaluation_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE CASCADE,
    FOREIGN KEY (evaluator_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Insert default project types
INSERT INTO project_types (name, description) VALUES 
('Stage d\'initiation', 'Stage réalisé en 3ème année'),
('Stage d\'ingénieur adjoint', 'Stage réalisé en 4ème année'),
('Stage de fin d\'études (PFE)', 'Stage réalisé en 5ème année'),
('Projet pédagogique', 'Projet réalisé dans le cadre d\'un module');

-- Insert default project categories
INSERT INTO project_categories (name, description) VALUES 
('Développement Web', 'Projets de développement d\'applications web'),
('Développement Mobile', 'Projets de développement d\'applications mobiles'),
('Intelligence Artificielle', 'Projets liés à l\'IA et machine learning'),
('Réseaux', 'Projets liés aux réseaux informatiques'),
('Base de données', 'Projets centrés sur les bases de données'),
('Sécurité Informatique', 'Projets liés à la cybersécurité'),
('Systèmes Embarqués', 'Projets liés aux systèmes embarqués et IoT'),
('Autre', 'Autres types de projets');

-- Create an admin user (password: admin123)
INSERT INTO users (username, password, email, full_name, role) VALUES 
('admin', '$2y$10$hKmN3wOCNTTLXJl5K2QQ3.PaULIPc9YJcTM0Zz2EQC.UKi1TRt5Aa', 'admin@ensa.ma', 'Administrateur', 'admin');
