ALTER TABLE lessons
DROP CONSTRAINT IF EXISTS lessons_ibfk_1,
ADD CONSTRAINT lessons_module_fk
    FOREIGN KEY (module_id)
    REFERENCES modules(id)
    ON DELETE CASCADE;