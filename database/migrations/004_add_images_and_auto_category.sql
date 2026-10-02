-- Run this only if your database was created before:
--   - image_path columns on materials/assets (photo attachments)
--   - materials' category_id being auto-assigned from the first Thai
--     consonant of the item name (instead of a manually picked category)
-- (A fresh install using database/schema.sql already includes these.)

USE nsokhkaen_inventory;

ALTER TABLE materials
    ADD COLUMN image_path VARCHAR(255) NULL AFTER storage_location;

ALTER TABLE assets
    ADD COLUMN image_path VARCHAR(255) NULL AFTER acquired_date;

-- New materials created/edited from now on get their category auto-assigned
-- automatically by the app. To also re-categorize materials that already
-- existed before this migration, run (from the project root, after this
-- SQL file):
--
--   php database/migrations/004_backfill_material_categories.php
