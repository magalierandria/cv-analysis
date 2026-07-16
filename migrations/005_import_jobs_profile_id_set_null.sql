ALTER TABLE import_jobs DROP CONSTRAINT import_jobs_profile_id_fkey;
ALTER TABLE import_jobs ADD CONSTRAINT import_jobs_profile_id_fkey
    FOREIGN KEY (profile_id) REFERENCES profils (id) ON DELETE SET NULL;
