-- MedLine Rural - Initial Database Seed
-- Creates application roles only. No administrator account or credentials are seeded.
-- See README.md for creating a local administrator with a password chosen by the developer.
-- ============================================================================

INSERT IGNORE INTO roles (role_name) VALUES
    ('Admin'),
    ('Dispatcher'),
    ('Driver'),
    ('Clinic Staff');
