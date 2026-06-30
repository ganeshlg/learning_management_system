-- Add new columns to existing modules table
ALTER TABLE modules 
ADD COLUMN description TEXT AFTER title,
ADD COLUMN type VARCHAR(100) AFTER video_url,
ADD COLUMN live_link VARCHAR(500) AFTER type,
ADD COLUMN recorded_video_url TEXT AFTER live_link;
