-- Audit trail for the Purchase Order app, on the HRMIS server.
--
-- Lives in `purchase_request` alongside tbl_po_notifications rather than in a
-- separate local database, so a deploy box needs no MySQL of its own. Run once
-- against the HRMIS server:
--
--     mysql -u prroot -p purchase_request < database/tbl_po_audit_logs.sql
--
-- utf8, not utf8mb4: this HRMIS server is MySQL 5.1.30, which predates utf8mb4
-- (added in 5.5.3) and rejects it with "Unknown character set". utf8 matches
-- tbl_po_notifications, the other table this project created here.
--
-- Additive only: CREATE TABLE IF NOT EXISTS touches no existing table and
-- cannot affect the 26,000 live purchase orders.
--
-- User_ID holds an HRMIS users.user_id (the id a session actually carries —
-- see api/auth.php), NOT an id from the retired local pyobasesystem_db.

    CREATE TABLE IF NOT EXISTS tbl_po_audit_logs (
        ID           INT AUTO_INCREMENT PRIMARY KEY,
        User_ID      INT          NULL,
        Username     VARCHAR(50)  NULL,
        Action       VARCHAR(100) NOT NULL,
        Module       VARCHAR(50)  NULL,
        Details      TEXT         NULL,
        IP_Address   VARCHAR(45)  NULL,
        Date_Created TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_po_audit_user    (User_ID),
        INDEX idx_po_audit_created (Date_Created),
        -- "when did this user last log in" is the single hottest lookup (every
        -- successful login runs it), so it gets its own composite index.
        INDEX idx_po_audit_login   (Username, Action, Date_Created)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
