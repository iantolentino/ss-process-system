-- Indexes for the queries that grow with use: review-due lists (dashboard, weekly cron), open change requests, onboarding by department.
ALTER TABLE documents ADD INDEX idx_review (status, review_due);
ALTER TABLE change_requests ADD INDEX idx_process_status (process_id, status);
ALTER TABLE onboarding_items ADD INDEX idx_dept_week (department_id, week_no);
ALTER TABLE users ADD INDEX idx_name (last_name, first_name);
