-- How often a process happens (for daily / weekly / monthly views) and whether a row is a reusable template instead of a live process.
ALTER TABLE processes ADD COLUMN cadence ENUM('daily','weekly','monthly','quarterly','yearly','as_needed') NOT NULL DEFAULT 'as_needed' AFTER owner_position;
ALTER TABLE processes ADD COLUMN kind ENUM('process','template') NOT NULL DEFAULT 'process' AFTER cadence;
ALTER TABLE processes ADD INDEX idx_dept_kind_cadence (department_id, kind, cadence);

-- Best guess for existing processes from their free-text "how often" (owners can correct it on the edit form).
UPDATE processes p JOIN process_versions v ON v.id = p.current_version_id SET p.cadence = CASE
  WHEN LOWER(JSON_UNQUOTE(JSON_EXTRACT(v.snapshot, '$.frequency'))) LIKE '%daily%' OR LOWER(JSON_UNQUOTE(JSON_EXTRACT(v.snapshot, '$.frequency'))) LIKE '%every day%' THEN 'daily'
  WHEN LOWER(JSON_UNQUOTE(JSON_EXTRACT(v.snapshot, '$.frequency'))) LIKE '%week%' THEN 'weekly'
  WHEN LOWER(JSON_UNQUOTE(JSON_EXTRACT(v.snapshot, '$.frequency'))) LIKE '%quarter%' THEN 'quarterly'
  WHEN LOWER(JSON_UNQUOTE(JSON_EXTRACT(v.snapshot, '$.frequency'))) LIKE '%month%' THEN 'monthly'
  WHEN LOWER(JSON_UNQUOTE(JSON_EXTRACT(v.snapshot, '$.frequency'))) LIKE '%year%' OR LOWER(JSON_UNQUOTE(JSON_EXTRACT(v.snapshot, '$.frequency'))) LIKE '%annual%' THEN 'yearly'
  ELSE 'as_needed' END;
