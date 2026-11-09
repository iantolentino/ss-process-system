-- Memos: who sent it, who it is for, and when it stops applying. Unused (NULL) for other document types.
ALTER TABLE documents
  ADD COLUMN memo_from VARCHAR(160) NULL AFTER sharepoint_url,
  ADD COLUMN audience VARCHAR(160) NULL AFTER memo_from,
  ADD COLUMN expires_on DATE NULL AFTER audience;
