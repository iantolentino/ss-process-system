-- Approval before publishing: a version uploaded or saved by a non-admin waits as 'pending' until an admin decides.
-- Existing versions count as approved.
ALTER TABLE document_versions
  ADD COLUMN approval ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved',
  ADD COLUMN decided_by INT UNSIGNED NULL,
  ADD COLUMN decided_at DATETIME NULL,
  ADD COLUMN decision_note VARCHAR(500) NOT NULL DEFAULT '';
ALTER TABLE process_versions
  ADD COLUMN approval ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved',
  ADD COLUMN decided_by INT UNSIGNED NULL,
  ADD COLUMN decided_at DATETIME NULL,
  ADD COLUMN decision_note VARCHAR(500) NOT NULL DEFAULT '';

-- Read and confirm: when set, every person is asked to confirm they have read the current version (rows go to acknowledgements).
ALTER TABLE documents ADD COLUMN ack_required TINYINT(1) NOT NULL DEFAULT 0;
