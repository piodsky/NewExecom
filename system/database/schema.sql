-- ============================================================
-- EXECOM Computer Sales & Services — private-company logistics
-- Database: execom_db
--
-- Fresh-install schema. Run once against a local MySQL/MariaDB:
--     mysql -u root execom_db < database/schema.sql
-- (create the database first: CREATE DATABASE execom_db CHARACTER SET
-- utf8mb4 COLLATE utf8mb4_unicode_ci;)
--
-- This is a from-scratch schema for EXECOM's own operations — it does not
-- reuse or connect to the government HRMIS procurement tables this folder
-- used to run against. See CLAUDE.md and the approved structure plan for
-- the module-by-module rationale.
-- ============================================================

CREATE DATABASE IF NOT EXISTS execom_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE execom_db;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS notification_reads;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS user_module_access;
DROP TABLE IF EXISTS role_module_access;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS doc_sequences;

DROP TABLE IF EXISTS issuance_items;
DROP TABLE IF EXISTS issuances;
DROP TABLE IF EXISTS stock_transfer_items;
DROP TABLE IF EXISTS stock_transfers;
DROP TABLE IF EXISTS inventory_units;
DROP TABLE IF EXISTS inventory_stock;
DROP TABLE IF EXISTS delivery_report_items;
DROP TABLE IF EXISTS delivery_reports;
DROP TABLE IF EXISTS receiving_report_items;
DROP TABLE IF EXISTS receiving_reports;
DROP TABLE IF EXISTS po_outgoing_items;
DROP TABLE IF EXISTS po_outgoing;
DROP TABLE IF EXISTS po_internal_items;
DROP TABLE IF EXISTS po_internal;
DROP TABLE IF EXISTS outgoing_quotation_items;
DROP TABLE IF EXISTS outgoing_quotations;
DROP TABLE IF EXISTS outgoing_rfq_items;
DROP TABLE IF EXISTS outgoing_rfq;
DROP TABLE IF EXISTS internal_rfq_quotes;
DROP TABLE IF EXISTS internal_rfq_items;
DROP TABLE IF EXISTS internal_rfq;
DROP TABLE IF EXISTS pr_items;
DROP TABLE IF EXISTS pr_requests;

DROP TABLE IF EXISTS clients;
DROP TABLE IF EXISTS suppliers;
DROP TABLE IF EXISTS items;
DROP TABLE IF EXISTS item_categories;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS branches;
SET FOREIGN_KEY_CHECKS = 1;

-- ── Core: branches, users, roles, notifications, audit log ──────────────

CREATE TABLE branches (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(10)  NOT NULL UNIQUE,   -- MLB, MRM, DVO, CDO, VAL
    name        VARCHAR(150) NOT NULL,
    address     VARCHAR(255) NULL,
    contact_no  VARCHAR(50)  NULL,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

-- full_name is derived/denormalized — recomputed from first/middle/last/
-- suffix on every save (see api/profile.php, api/settings.php). Nothing
-- should write to it directly.
--
-- role stays a plain VARCHAR rather than an ENUM: "I will add more roles
-- soon" — a new role must never require a schema migration. ROLE_RANK in
-- config.php is the single source of truth for rank ordering.
--
-- branch_id is NULL only for super_admin (sees every branch). A
-- branch_admin/branch_user always belongs to exactly one branch.
CREATE TABLE users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  UNIQUE NOT NULL,
    password      VARCHAR(255) NOT NULL,
    full_name     VARCHAR(100) NOT NULL,
    first_name    VARCHAR(50)  NULL,
    middle_name   VARCHAR(50)  NULL,
    last_name     VARCHAR(50)  NULL,
    suffix        VARCHAR(20)  NULL,
    branch_id     INT          NULL,
    role          VARCHAR(30)  NOT NULL DEFAULT 'branch_user',
    department    VARCHAR(150) NULL,
    last_login    DATETIME     NULL,
    avatar_path   VARCHAR(255) NULL,
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    INDEX idx_users_branch (branch_id)
);

CREATE TABLE role_module_access (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    role        VARCHAR(30) NOT NULL,
    module_key  VARCHAR(50) NOT NULL,
    can_access  TINYINT(1)  NOT NULL DEFAULT 1,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY role_module (role, module_key)
);

CREATE TABLE user_module_access (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    module_key  VARCHAR(50) NOT NULL,
    can_access  TINYINT(1) NOT NULL DEFAULT 1,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY user_module (user_id, module_key),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE notifications (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    role        VARCHAR(30)  NOT NULL,
    title       VARCHAR(150) NOT NULL,
    message     VARCHAR(255) NULL,
    link        VARCHAR(255) NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE notification_reads (
    notification_id INT NOT NULL,
    user_id         INT NOT NULL,
    read_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (notification_id, user_id),
    FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE audit_logs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT,
    username    VARCHAR(50),
    action      VARCHAR(100) NOT NULL,
    module      VARCHAR(50),
    details     TEXT,
    ip_address  VARCHAR(45),
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Mints branch-prefixed document numbers atomically:
-- {BranchCode}-{DocType}-{Year}-{Sequence}, e.g. MLB-PR-2026-0001.
-- One row per (branch, doc type, year); last_number is incremented inside
-- a transaction by whichever module is minting a number.
CREATE TABLE doc_sequences (
    branch_id    INT         NOT NULL,
    doc_type     VARCHAR(20) NOT NULL,   -- PR, IRFQ, ORFQ, POI, POO, RR, DR, ST, ISS
    year         SMALLINT    NOT NULL,
    last_number  INT         NOT NULL DEFAULT 0,
    PRIMARY KEY (branch_id, doc_type, year),
    FOREIGN KEY (branch_id) REFERENCES branches(id)
);

-- ── Master data ───────────────────────────────────────────────────────

CREATE TABLE item_categories (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100) NOT NULL UNIQUE,
    is_serialized TINYINT(1)   NOT NULL DEFAULT 1,  -- default for items in this category
    is_active     TINYINT(1)   NOT NULL DEFAULT 1
);

CREATE TABLE items (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    sku            VARCHAR(50)   NULL UNIQUE,
    name           VARCHAR(255)  NOT NULL,
    category_id    INT           NULL,
    brand          VARCHAR(100)  NULL,
    unit           VARCHAR(30)   NOT NULL DEFAULT 'unit',
    is_serialized  TINYINT(1)    NOT NULL DEFAULT 1,  -- overridable per item
    default_cost   DECIMAL(15,2) NULL,
    default_price  DECIMAL(15,2) NULL,
    is_active      TINYINT(1)    NOT NULL DEFAULT 1,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES item_categories(id),
    INDEX idx_items_category (category_id)
);

-- EXECOM's own upstream vendors (Internal side — Supplier Management module).
CREATE TABLE suppliers (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(255) NOT NULL,
    address         VARCHAR(255) NULL,
    contact_person  VARCHAR(150) NULL,
    contact_number  VARCHAR(50)  NULL,
    email           VARCHAR(150) NULL,
    payment_terms   VARCHAR(100) NULL,
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Government/other end-clients for the Outgoing side (an LGU office, etc).
-- Documents also snapshot the client name/address as free text at the time
-- (see po_outgoing.client_name/client_address) so an old printed document
-- never silently changes when this master record is edited later.
CREATE TABLE clients (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(255) NOT NULL,
    address         VARCHAR(255) NULL,
    contact_person  VARCHAR(150) NULL,
    contact_number  VARCHAR(50)  NULL,
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ── 1. Purchase Requisition (Internal only) ──────────────────────────────

CREATE TABLE pr_requests (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    pr_number     VARCHAR(30)  NOT NULL UNIQUE,
    branch_id     INT          NOT NULL,
    requested_by  INT          NOT NULL,
    department    VARCHAR(150) NULL,
    purpose       VARCHAR(255) NULL,
    needed_date   DATE         NULL,
    status        ENUM('draft','pending','approved','rejected','converted') NOT NULL DEFAULT 'draft',
    approved_by   INT          NULL,
    approved_at   DATETIME     NULL,
    is_deleted    TINYINT(1)   NOT NULL DEFAULT 0,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (requested_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id),
    INDEX idx_pr_branch (branch_id),
    INDEX idx_pr_status (status)
);

CREATE TABLE pr_items (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    pr_number    VARCHAR(30)   NOT NULL,
    item_id      INT           NULL,
    description  VARCHAR(255)  NULL,
    quantity     DECIMAL(11,2) NOT NULL DEFAULT 1,
    unit         VARCHAR(30)   NULL,
    is_deleted   TINYINT(1)    NOT NULL DEFAULT 0,
    FOREIGN KEY (pr_number) REFERENCES pr_requests(pr_number),
    FOREIGN KEY (item_id) REFERENCES items(id),
    INDEX idx_pri_pr (pr_number)
);

-- ── 2. RFQ / Supplier Quotation — Internal and Outgoing ──────────────────

-- Internal: EXECOM canvassing its own suppliers.
CREATE TABLE internal_rfq (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    rfq_number  VARCHAR(30) NOT NULL UNIQUE,
    branch_id   INT         NOT NULL,
    pr_number   VARCHAR(30) NULL,
    sent_date   DATE        NULL,
    created_by  INT         NOT NULL,
    is_deleted  TINYINT(1)  NOT NULL DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (pr_number) REFERENCES pr_requests(pr_number),
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_irfq_branch (branch_id)
);

CREATE TABLE internal_rfq_items (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    rfq_number   VARCHAR(30)   NOT NULL,
    item_id      INT           NULL,
    description  VARCHAR(255)  NULL,
    quantity     DECIMAL(11,2) NOT NULL DEFAULT 1,
    unit         VARCHAR(30)   NULL,
    is_deleted   TINYINT(1)    NOT NULL DEFAULT 0,
    FOREIGN KEY (rfq_number) REFERENCES internal_rfq(rfq_number),
    FOREIGN KEY (item_id) REFERENCES items(id),
    INDEX idx_irfqi_rfq (rfq_number)
);

-- One row per supplier's quoted price per RFQ line, so a branch admin can
-- compare and pick a winner before creating the Internal PO.
CREATE TABLE internal_rfq_quotes (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    rfq_number       VARCHAR(30)   NOT NULL,
    supplier_id      INT           NOT NULL,
    rfq_item_id      INT           NOT NULL,
    quoted_price     DECIMAL(15,2) NULL,
    lead_time_days   INT           NULL,
    is_selected      TINYINT(1)    NOT NULL DEFAULT 0,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rfq_number) REFERENCES internal_rfq(rfq_number),
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    FOREIGN KEY (rfq_item_id) REFERENCES internal_rfq_items(id),
    INDEX idx_irfqq_rfq (rfq_number)
);

-- Outgoing: a government client's canvass received, and EXECOM's reply.
CREATE TABLE outgoing_rfq (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    rfq_number     VARCHAR(30)  NOT NULL UNIQUE,
    branch_id      INT          NOT NULL,
    client_id      INT          NULL,
    client_name    VARCHAR(255) NULL,   -- snapshot, in case client_id is unset or edited later
    client_address VARCHAR(255) NULL,
    received_date  DATE         NULL,
    due_date       DATE         NULL,
    created_by     INT          NOT NULL,
    is_deleted     TINYINT(1)   NOT NULL DEFAULT 0,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (client_id) REFERENCES clients(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_orfq_branch (branch_id)
);

CREATE TABLE outgoing_rfq_items (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    rfq_number   VARCHAR(30)   NOT NULL,
    item_id      INT           NULL,
    description  VARCHAR(255)  NULL,
    quantity     DECIMAL(11,2) NOT NULL DEFAULT 1,
    unit         VARCHAR(30)   NULL,
    is_deleted   TINYINT(1)    NOT NULL DEFAULT 0,
    FOREIGN KEY (rfq_number) REFERENCES outgoing_rfq(rfq_number),
    FOREIGN KEY (item_id) REFERENCES items(id),
    INDEX idx_orfqi_rfq (rfq_number)
);

CREATE TABLE outgoing_quotations (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    quotation_number     VARCHAR(30) NOT NULL UNIQUE,
    rfq_number           VARCHAR(30) NULL,
    branch_id            INT         NOT NULL,
    price_validity_days  INT         NULL,
    lead_time            VARCHAR(100) NULL,
    prepared_by          INT         NULL,
    is_deleted           TINYINT(1)  NOT NULL DEFAULT 0,
    created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rfq_number) REFERENCES outgoing_rfq(rfq_number),
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (prepared_by) REFERENCES users(id),
    INDEX idx_oq_branch (branch_id)
);

CREATE TABLE outgoing_quotation_items (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    quotation_number   VARCHAR(30)   NOT NULL,
    item_id            INT           NULL,
    description        VARCHAR(255)  NULL,
    quantity           DECIMAL(11,2) NOT NULL DEFAULT 1,
    unit               VARCHAR(30)   NULL,
    unit_price         DECIMAL(15,2) NULL,
    total_price        DECIMAL(15,2) NULL,
    is_deleted         TINYINT(1)    NOT NULL DEFAULT 0,
    FOREIGN KEY (quotation_number) REFERENCES outgoing_quotations(quotation_number),
    FOREIGN KEY (item_id) REFERENCES items(id),
    INDEX idx_oqi_quotation (quotation_number)
);

-- ── 3. Purchase Order — Internal and Outgoing ────────────────────────────

-- Internal: EXECOM → its own supplier (mirrors files/SAMPLE P.O.pdf).
CREATE TABLE po_internal (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    po_number          VARCHAR(30)   NOT NULL UNIQUE,
    branch_id          INT           NOT NULL,
    supplier_id        INT           NULL,
    supplier_name      VARCHAR(255)  NULL,  -- snapshot as printed
    supplier_address   VARCHAR(255)  NULL,
    internal_rfq_number VARCHAR(30)  NULL,
    pr_number          VARCHAR(30)   NULL,
    forwarder          VARCHAR(150)  NULL,
    ship_to_address    VARCHAR(255)  NULL,
    status             ENUM('draft','sent','partially_received','received','cancelled') NOT NULL DEFAULT 'draft',
    total_amount       DECIMAL(15,2) NOT NULL DEFAULT 0,
    created_by         INT           NOT NULL,
    is_deleted         TINYINT(1)    NOT NULL DEFAULT 0,
    created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    FOREIGN KEY (internal_rfq_number) REFERENCES internal_rfq(rfq_number),
    FOREIGN KEY (pr_number) REFERENCES pr_requests(pr_number),
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_poi_branch (branch_id),
    INDEX idx_poi_status (status)
);

CREATE TABLE po_internal_items (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    po_number     VARCHAR(30)   NOT NULL,
    item_id       INT           NULL,
    end_user      VARCHAR(150)  NULL,
    description   VARCHAR(255)  NULL,
    quantity      DECIMAL(11,2) NOT NULL DEFAULT 1,
    unit          VARCHAR(30)   NULL,
    unit_cost     DECIMAL(15,2) NOT NULL DEFAULT 0,
    total_cost    DECIMAL(15,2) NOT NULL DEFAULT 0,
    is_deleted    TINYINT(1)    NOT NULL DEFAULT 0,
    FOREIGN KEY (po_number) REFERENCES po_internal(po_number),
    FOREIGN KEY (item_id) REFERENCES items(id),
    INDEX idx_poii_po (po_number)
);

-- Outgoing: client (LGU) → EXECOM, the awarded sale.
CREATE TABLE po_outgoing (
    id                       INT AUTO_INCREMENT PRIMARY KEY,
    po_number                VARCHAR(30)   NOT NULL UNIQUE,
    branch_id                INT           NOT NULL,
    client_id                INT           NULL,
    client_name              VARCHAR(255)  NULL,  -- snapshot as printed
    client_address           VARCHAR(255)  NULL,
    outgoing_quotation_number VARCHAR(30)  NULL,
    end_user                 VARCHAR(150)  NULL,
    place_of_delivery        VARCHAR(255)  NULL,
    delivery_term            VARCHAR(100)  NULL,
    payment_term             VARCHAR(100)  NULL,
    status                   ENUM('open','partially_delivered','delivered','completed','cancelled') NOT NULL DEFAULT 'open',
    total_amount             DECIMAL(15,2) NOT NULL DEFAULT 0,
    created_by               INT           NOT NULL,
    is_deleted               TINYINT(1)    NOT NULL DEFAULT 0,
    created_at               TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (client_id) REFERENCES clients(id),
    FOREIGN KEY (outgoing_quotation_number) REFERENCES outgoing_quotations(quotation_number),
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_poo_branch (branch_id),
    INDEX idx_poo_status (status)
);

CREATE TABLE po_outgoing_items (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    po_number     VARCHAR(30)   NOT NULL,
    item_id       INT           NULL,
    description   VARCHAR(255)  NULL,
    quantity      DECIMAL(11,2) NOT NULL DEFAULT 1,
    unit          VARCHAR(30)   NULL,
    unit_price    DECIMAL(15,2) NOT NULL DEFAULT 0,
    total_price   DECIMAL(15,2) NOT NULL DEFAULT 0,
    is_deleted    TINYINT(1)    NOT NULL DEFAULT 0,
    FOREIGN KEY (po_number) REFERENCES po_outgoing(po_number),
    FOREIGN KEY (item_id) REFERENCES items(id),
    INDEX idx_pooi_po (po_number)
);

-- ── 4. Receiving Report — Internal and Outgoing ──────────────────────────

-- Internal: goods arriving from EXECOM's supplier into a branch.
CREATE TABLE receiving_reports (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    rr_number        VARCHAR(30) NOT NULL UNIQUE,
    branch_id        INT         NOT NULL,
    po_number        VARCHAR(30) NULL,
    received_by      INT         NOT NULL,
    date_received    DATE        NULL,
    condition_notes  VARCHAR(255) NULL,
    is_deleted       TINYINT(1)  NOT NULL DEFAULT 0,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (po_number) REFERENCES po_internal(po_number),
    FOREIGN KEY (received_by) REFERENCES users(id),
    INDEX idx_rr_branch (branch_id),
    INDEX idx_rr_po (po_number)
);

CREATE TABLE receiving_report_items (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    rr_number      VARCHAR(30)   NOT NULL,
    item_id        INT           NOT NULL,
    quantity       DECIMAL(11,2) NOT NULL DEFAULT 1,
    unit_cost      DECIMAL(15,2) NOT NULL DEFAULT 0,
    serial_number  VARCHAR(100)  NULL,   -- filled when items.is_serialized
    is_deleted     TINYINT(1)    NOT NULL DEFAULT 0,
    FOREIGN KEY (rr_number) REFERENCES receiving_reports(rr_number),
    FOREIGN KEY (item_id) REFERENCES items(id),
    INDEX idx_rri_rr (rr_number)
);

-- Outgoing: EXECOM delivering to the government client — the delivery/
-- acceptance counterpart that closes a po_outgoing.
CREATE TABLE delivery_reports (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    dr_number       VARCHAR(30)  NOT NULL UNIQUE,
    branch_id       INT          NOT NULL,
    po_number       VARCHAR(30)  NULL,
    delivered_by    INT          NOT NULL,
    forwarder       VARCHAR(150) NULL,
    date_delivered  DATE         NULL,
    received_by     VARCHAR(150) NULL,   -- client-side signatory (free text)
    date_accepted   DATE         NULL,
    is_deleted      TINYINT(1)   NOT NULL DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (po_number) REFERENCES po_outgoing(po_number),
    FOREIGN KEY (delivered_by) REFERENCES users(id),
    INDEX idx_dr_branch (branch_id),
    INDEX idx_dr_po (po_number)
);

CREATE TABLE delivery_report_items (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    dr_number    VARCHAR(30)   NOT NULL,
    item_id      INT           NOT NULL,
    quantity     DECIMAL(11,2) NOT NULL DEFAULT 1,
    is_deleted   TINYINT(1)    NOT NULL DEFAULT 0,
    FOREIGN KEY (dr_number) REFERENCES delivery_reports(dr_number),
    FOREIGN KEY (item_id) REFERENCES items(id),
    INDEX idx_dri_dr (dr_number)
);

-- ── 6. Inventory — Internal, hybrid serialized + bulk ────────────────────
-- (Module 5, Warehouse, has no table of its own — a branch IS a warehouse;
-- see the `location` free-text column below for shelf/bin.)

CREATE TABLE inventory_units (
    id                    INT AUTO_INCREMENT PRIMARY KEY,
    item_id               INT           NOT NULL,
    branch_id             INT           NOT NULL,   -- current location
    serial_number         VARCHAR(100)  NULL,
    status                ENUM('in_stock','reserved','issued','in_transit','returned','defective') NOT NULL DEFAULT 'in_stock',
    unit_cost             DECIMAL(15,2) NOT NULL DEFAULT 0,
    source_rr_number      VARCHAR(30)   NULL,
    reserved_for_client_id INT          NULL,
    reserved_for_po_number VARCHAR(30)  NULL,
    date_received         DATE          NULL,
    date_out              DATE          NULL,
    location              VARCHAR(100)  NULL,   -- shelf/bin, free text
    remarks               VARCHAR(255)  NULL,
    is_deleted            TINYINT(1)    NOT NULL DEFAULT 0,
    created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id),
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (source_rr_number) REFERENCES receiving_reports(rr_number),
    FOREIGN KEY (reserved_for_client_id) REFERENCES clients(id),
    INDEX idx_invu_branch (branch_id),
    INDEX idx_invu_item (item_id),
    INDEX idx_invu_serial (serial_number),
    INDEX idx_invu_status (status)
);

CREATE TABLE inventory_stock (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    item_id           INT NOT NULL,
    branch_id         INT NOT NULL,
    quantity_on_hand  DECIMAL(14,2) NOT NULL DEFAULT 0,
    reorder_level     DECIMAL(14,2) NOT NULL DEFAULT 0,
    UNIQUE KEY item_branch (item_id, branch_id),
    FOREIGN KEY (item_id) REFERENCES items(id),
    FOREIGN KEY (branch_id) REFERENCES branches(id)
);

-- ── 7. Stock Transfer — Internal, branch to branch ───────────────────────

CREATE TABLE stock_transfers (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    transfer_number  VARCHAR(30) NOT NULL UNIQUE,
    from_branch_id   INT         NOT NULL,
    to_branch_id     INT         NOT NULL,
    requested_by     INT         NOT NULL,
    approved_by      INT         NULL,
    status           ENUM('pending','in_transit','received','cancelled') NOT NULL DEFAULT 'pending',
    date_created     DATETIME    NULL,
    date_received    DATETIME    NULL,
    is_deleted       TINYINT(1)  NOT NULL DEFAULT 0,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (from_branch_id) REFERENCES branches(id),
    FOREIGN KEY (to_branch_id) REFERENCES branches(id),
    FOREIGN KEY (requested_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id),
    INDEX idx_st_from (from_branch_id),
    INDEX idx_st_to (to_branch_id)
);

CREATE TABLE stock_transfer_items (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    transfer_number     VARCHAR(30)   NOT NULL,
    inventory_unit_id   INT           NULL,   -- serialized item being moved
    item_id             INT           NULL,   -- bulk item being moved
    quantity            DECIMAL(11,2) NULL,   -- for bulk items
    description_snapshot VARCHAR(255) NULL,
    serial_snapshot     VARCHAR(100)  NULL,
    is_deleted          TINYINT(1)    NOT NULL DEFAULT 0,
    FOREIGN KEY (transfer_number) REFERENCES stock_transfers(transfer_number),
    FOREIGN KEY (inventory_unit_id) REFERENCES inventory_units(id),
    FOREIGN KEY (item_id) REFERENCES items(id),
    INDEX idx_sti_transfer (transfer_number)
);

-- ── 8. Issuance (POS-style) — Internal ───────────────────────────────────

CREATE TABLE issuances (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    issuance_number  VARCHAR(30) NOT NULL UNIQUE,
    branch_id        INT         NOT NULL,
    client_id        INT         NULL,   -- NULL = walk-in retail sale
    po_number        VARCHAR(30) NULL,   -- fulfilling a specific government order
    issued_by        INT         NOT NULL,
    date_created     DATETIME    NULL,
    total_amount     DECIMAL(15,2) NOT NULL DEFAULT 0,
    is_deleted       TINYINT(1)  NOT NULL DEFAULT 0,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (client_id) REFERENCES clients(id),
    FOREIGN KEY (po_number) REFERENCES po_outgoing(po_number),
    FOREIGN KEY (issued_by) REFERENCES users(id),
    INDEX idx_iss_branch (branch_id)
);

CREATE TABLE issuance_items (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    issuance_number    VARCHAR(30)   NOT NULL,
    inventory_unit_id  INT           NULL,   -- serialized item sold
    item_id            INT           NULL,   -- bulk item sold
    quantity           DECIMAL(11,2) NULL,   -- for bulk items
    unit_price         DECIMAL(15,2) NOT NULL DEFAULT 0,
    total_price        DECIMAL(15,2) NOT NULL DEFAULT 0,
    is_deleted         TINYINT(1)    NOT NULL DEFAULT 0,
    FOREIGN KEY (issuance_number) REFERENCES issuances(issuance_number),
    FOREIGN KEY (inventory_unit_id) REFERENCES inventory_units(id),
    FOREIGN KEY (item_id) REFERENCES items(id),
    INDEX idx_issi_issuance (issuance_number)
);

-- Module 9 (Supplier Management) and Module 10 (Cost/Profit Reports) need no
-- tables of their own: Supplier Management is CRUD over `suppliers` above
-- (already linked from po_internal/internal_rfq_quotes), and Cost/Profit
-- Reports is read-only SQL over delivery_reports/po_outgoing (revenue) vs.
-- issuance_items -> inventory_units.unit_cost (matched cost).

-- ── Seed data ─────────────────────────────────────────────────────────

INSERT INTO branches (code, name) VALUES
    ('MLB', 'Malaybalay City'),
    ('MRM', 'Maramag City'),
    ('DVO', 'Davao City'),
    ('CDO', 'Cagayan De Oro City'),
    ('VAL', 'Valencia City');

INSERT INTO role_module_access (role, module_key, can_access) VALUES
    ('branch_user', 'settings', 0);

-- Default super_admin — username: admin / password: admin123
-- CHANGE THIS PASSWORD immediately after first login (via My Profile).
INSERT INTO users (username, password, full_name, first_name, last_name, role, branch_id) VALUES
('admin', '$2y$10$0w832hH3EFlb4KMRhAW6zOnCB9MVpvgCg8nP54Wkc/.mVdev4LG/u', 'System Administrator', 'System', 'Administrator', 'super_admin', NULL);

INSERT INTO item_categories (name, is_serialized) VALUES
    ('Monitor', 1),
    ('Laptop / All-in-One', 1),
    ('Processor / Motherboard', 1),
    ('SSD / HDD / External Storage', 1),
    ('RAM', 0),
    ('Printer', 1),
    ('CCTV', 1),
    ('Projector / Scanner / Webcam', 1),
    ('Others', 0);
