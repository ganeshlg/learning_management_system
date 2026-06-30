-- Fix the foreign key constraint on lessons table to cascade deletes
ALTER TABLE lessons 
DROP FOREIGN KEY lessons_ibfk_1,
ADD CONSTRAINT lessons_module_fk FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE;
