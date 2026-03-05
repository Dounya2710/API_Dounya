-- Group roles
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'klcd_read') THEN
        CREATE ROLE klcd_read NOLOGIN;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'klcd_write') THEN
        CREATE ROLE klcd_write NOLOGIN;
    END IF;
END $$;

-- Use default "public" schema (since your SQL creates tables without schema prefix)
GRANT USAGE ON SCHEMA public TO klcd_read, klcd_write;

-- Read access
GRANT SELECT ON ALL TABLES IN SCHEMA public TO klcd_read;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT ON TABLES TO klcd_read;

-- Write access (adapt if you want to restrict)
GRANT INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO klcd_write;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT INSERT, UPDATE, DELETE ON TABLES TO klcd_write;

-- Optional: allow sequences (IDs) usage for writers
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO klcd_write;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT USAGE, SELECT ON SEQUENCES TO klcd_write;
