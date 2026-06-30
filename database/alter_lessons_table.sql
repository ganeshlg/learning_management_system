-- Update existing lessons table to match Lesson model
ALTER TABLE lessons 
DROP PRIMARY KEY,
ADD PRIMARY KEY (id),
ADD COLUMN content TEXT AFTER lesson_type,
ADD COLUMN `order` INT DEFAULT 0 AFTER content,
MODIFY lesson_type ENUM('liveSession', 'text', 'resource', 'assignment', 'quiz');

-- Create lesson_resources table
CREATE TABLE IF NOT EXISTS lesson_resources (
    id VARCHAR(50) PRIMARY KEY,
    lesson_id VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    url TEXT NOT NULL,
    file_type VARCHAR(100),
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
);
