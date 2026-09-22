-- Run this only if your database was created before the unit_cost column existed.
-- (A fresh install using database/schema.sql already includes this column.)

USE nsokhkaen_inventory;

ALTER TABLE materials
    ADD COLUMN unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER unit;
