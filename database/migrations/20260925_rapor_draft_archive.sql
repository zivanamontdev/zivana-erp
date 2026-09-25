-- Run once on existing databases to track teacher-archived incomplete drafts.
ALTER TABLE rapor
    ADD COLUMN diarsipkan_at TIMESTAMP NULL DEFAULT NULL AFTER status;
