-- Users table
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL
);

-- Courses, modules and lessons
CREATE TABLE IF NOT EXISTS courses (
    id VARCHAR(50) PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    thumbnail_url VARCHAR(500),
    price DECIMAL(10,2),
    duration_hours INT,
    instructor_name VARCHAR(255)
);

CREATE TABLE IF NOT EXISTS modules (
    id VARCHAR(50) PRIMARY KEY,
    course_id VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    video_url TEXT,
    type VARCHAR(100),
    live_link VARCHAR(500),
    recorded_video_url TEXT,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS lessons (
    id VARCHAR(50) PRIMARY KEY,
    module_id VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    lesson_type ENUM('liveSession', 'text', 'resource', 'assignment', 'quiz'),
    content TEXT,
    `order` INT DEFAULT 0,
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS lesson_resources (
    id VARCHAR(50) PRIMARY KEY,
    lesson_id VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    url TEXT NOT NULL,
    file_type VARCHAR(100),
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
);

-- Purchases: which user purchased which course
CREATE TABLE IF NOT EXISTS purchases (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  course_id VARCHAR(50) NOT NULL,
  purchased_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY user_course_unique (user_id, course_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

-- Admins table for managing courses
CREATE TABLE IF NOT EXISTS admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  name VARCHAR(255) DEFAULT NULL,
  role ENUM('super_admin','trainer') NOT NULL DEFAULT 'trainer',
  last_logged_in TIMESTAMP NULL DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS activity_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  `user` VARCHAR(255) NOT NULL,
  activity TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
