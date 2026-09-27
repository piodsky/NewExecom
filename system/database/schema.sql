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
DROP TABLE IF EXISTS notification_dismissals;
DROP TABLE IF EXISTS notification_reads;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS user_module_access;
DROP TABLE IF EXISTS role_module_access;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS doc_sequences;

DROP TABLE IF EXISTS job_order_parts;
DROP TABLE IF EXISTS job_orders;
DROP TABLE IF EXISTS post_dated_checks;
DROP TABLE IF EXISTS disbursement_applications;
DROP TABLE IF EXISTS disbursements;
DROP TABLE IF EXISTS supplier_invoices;
DROP TABLE IF EXISTS collection_applications;
DROP TABLE IF EXISTS collections;
DROP TABLE IF EXISTS sales_invoices;

DROP TABLE IF EXISTS issuance_items;
DROP TABLE IF EXISTS issuances;
DROP TABLE IF EXISTS stock_transfer_items;
DROP TABLE IF EXISTS stock_transfers;
DROP TABLE IF EXISTS inventory_units;
DROP TABLE IF EXISTS inventory_unassigned_units;
DROP TABLE IF EXISTS inventory_stock;
DROP TABLE IF EXISTS warehouse_locations;
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

DROP TABLE IF EXISTS clients;
DROP TABLE IF EXISTS salesmen;
DROP TABLE IF EXISTS suppliers;
DROP TABLE IF EXISTS items;
DROP TABLE IF EXISTS item_categories;
DROP TABLE IF EXISTS departments;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS branches;
DROP TABLE IF EXISTS ref_branches;
DROP TABLE IF EXISTS ref_roles;
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

-- A notification is one row broadcast to every user of a role (see
-- notifyRoles()), so "deleting" it for one person can't touch the shared
-- row — this just hides it from that person's own bell, the same way
-- notification_reads tracks read state per user rather than per row.
CREATE TABLE notification_dismissals (
    notification_id INT NOT NULL,
    user_id         INT NOT NULL,
    dismissed_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
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
    doc_type     VARCHAR(20) NOT NULL,   -- ORFQ, POI, POO, RR, DR, ST, ISS, SI, OR, PDC, JO, PINV, DV
    year         SMALLINT    NOT NULL,
    last_number  INT         NOT NULL DEFAULT 0,
    PRIMARY KEY (branch_id, doc_type, year),
    FOREIGN KEY (branch_id) REFERENCES branches(id)
);

-- ── Master data ───────────────────────────────────────────────────────

-- Reference-only master list of branches, kept separate from the
-- operational `branches` table (which every inventory/PO/issuance FK
-- points at and which getUserBranchScope() uses to scope a branch_user's
-- visibility). ADMIN isn't a physical warehouse — it's Head Office's
-- "oversee everything" row — so it lives here for dropdowns/reports/reference
-- rather than as a `branches` row a real account could get branch-scoped to.
CREATE TABLE ref_branches (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    code            VARCHAR(20)  NOT NULL UNIQUE,
    name            VARCHAR(100) NOT NULL,
    is_all_branches TINYINT(1)   NOT NULL DEFAULT 0,  -- true only for the ADMIN row
    sort_order      INT          NOT NULL DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Reference-only master list of roles, mirroring ROLE_RANK in config.php.
-- users.role stays a plain VARCHAR on purpose (see the comment on that
-- column) so a new role never needs a schema migration — this table is not
-- a FK target for it, just a maintained lookup for dropdowns/reports.
CREATE TABLE ref_roles (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    role_key    VARCHAR(30)  NOT NULL UNIQUE,
    label       VARCHAR(100) NOT NULL,
    rank        INT          NOT NULL,
    sort_order  INT          NOT NULL DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Which section of a branch is requesting on a Purchase Requisition (Sales,
-- Warehouse, Technical/Service, Admin, ...) — free text on pr_requests.department
-- itself, this table just gives the New Requisition form a maintained
-- dropdown instead of everyone typing their own spelling of the same thing.
CREATE TABLE departments (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL UNIQUE,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE item_categories (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100) NOT NULL UNIQUE,
    parent_id     INT          NULL,  -- self-referencing: NULL = top-level category
    is_serialized TINYINT(1)   NOT NULL DEFAULT 1,  -- default for items in this category
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    FOREIGN KEY (parent_id) REFERENCES item_categories(id),
    INDEX idx_itemcat_parent (parent_id)
);

CREATE TABLE items (
    id                         INT AUTO_INCREMENT PRIMARY KEY,
    sku                        VARCHAR(50)   NULL UNIQUE,
    name                       VARCHAR(255)  NOT NULL,
    category_id                INT           NULL,
    brand                      VARCHAR(100)  NULL,
    unit                       VARCHAR(30)   NOT NULL DEFAULT 'unit',  -- the sell/base unit; stock is always counted in this unit
    purchase_unit              VARCHAR(30)   NULL,      -- e.g. "box"; NULL = same as unit, no conversion
    purchase_conversion_factor DECIMAL(10,3) NOT NULL DEFAULT 1.000,   -- 1 purchase_unit = this many `unit` (applied only on receiving, non-serialized items)
    is_serialized              TINYINT(1)    NOT NULL DEFAULT 1,  -- overridable per item
    default_cost               DECIMAL(15,2) NULL,
    default_price              DECIMAL(15,2) NULL,
    is_active                  TINYINT(1)    NOT NULL DEFAULT 1,
    created_at                 TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES item_categories(id),
    INDEX idx_items_category (category_id)
);

-- EXECOM's own upstream vendors (Internal side — Supplier Management module).
CREATE TABLE suppliers (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    name               VARCHAR(255) NOT NULL,
    address            VARCHAR(255) NULL,
    contact_person     VARCHAR(150) NULL,
    contact_number     VARCHAR(50)  NULL,
    email              VARCHAR(150) NULL,
    payment_terms      VARCHAR(100) NULL,
    payment_terms_days INT           NULL,      -- e.g. 30 for "Net 30"; NULL = cash/COD
    credit_limit       DECIMAL(15,2) NOT NULL DEFAULT 0,
    is_active          TINYINT(1)   NOT NULL DEFAULT 1,
    created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Government/other end-clients for the Outgoing side (an LGU office, etc),
-- plus walk-in retail buyers registered from the Issuance POS screen.
-- Documents also snapshot the client name/address as free text at the time
-- (see po_outgoing.client_name/client_address) so an old printed document
-- never silently changes when this master record is edited later.
-- A plain lookup list, not tied to `users` — mirrors Globalchips' salesman.dbf being a
-- separate roster from system logins. No commission/payroll fields (GL/Payroll stays out
-- of scope for this system) — just who to credit a sale to.
CREATE TABLE salesmen (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    name           VARCHAR(150) NOT NULL,
    code           VARCHAR(20)  NULL UNIQUE,
    contact_number VARCHAR(50)  NULL,
    email          VARCHAR(150) NULL,
    is_active      TINYINT(1)   NOT NULL DEFAULT 1,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE clients (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    name                VARCHAR(255) NOT NULL,
    address             VARCHAR(255) NULL,
    contact_person      VARCHAR(150) NULL,
    contact_number      VARCHAR(50)  NULL,
    client_type         ENUM('business','walkin') NOT NULL DEFAULT 'business',
    payment_terms_days  INT           NULL,      -- e.g. 30 for "Net 30"; NULL = cash on delivery
    credit_limit        DECIMAL(15,2) NOT NULL DEFAULT 0,
    default_salesman_id INT           NULL,       -- prefills Issuance's salesman field for this client
    is_active           TINYINT(1)   NOT NULL DEFAULT 1,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (default_salesman_id) REFERENCES salesmen(id)
);

-- ── 2. RFQ / Supplier Quotation — Outgoing only ──────────────────────────
-- No internal-side canvass step: EXECOM buys directly from its own supplier
-- (po_internal below), matching Globalchips' direct PURCHTRN entry — the
-- client explicitly asked to drop the Purchase Requisition -> Internal RFQ
-- approval chain in favor of that simpler flow.

-- Outgoing: a government client's canvass received, and EXECOM's reply.
CREATE TABLE outgoing_rfq (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    rfq_number     VARCHAR(30)  NOT NULL UNIQUE,
    branch_id      INT          NOT NULL,
    client_id      INT          NULL,
    client_name    VARCHAR(255) NULL,   -- snapshot, in case client_id is unset or edited later
    client_address VARCHAR(255) NULL,
    received_date  DATETIME     NULL,
    due_date       DATETIME     NULL,
    status         ENUM('open','quoted','converted','cancelled') NOT NULL DEFAULT 'open',
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
    client_id            INT         NULL,
    client_name          VARCHAR(255) NULL,   -- snapshot, same pattern as every other outgoing document
    client_address       VARCHAR(255) NULL,
    price_validity_days  INT         NULL,
    lead_time            VARCHAR(100) NULL,
    total_amount         DECIMAL(15,2) NOT NULL DEFAULT 0,
    status               ENUM('active','converted','cancelled') NOT NULL DEFAULT 'active',
    prepared_by          INT         NULL,
    is_deleted           TINYINT(1)  NOT NULL DEFAULT 0,
    created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rfq_number) REFERENCES outgoing_rfq(rfq_number),
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (client_id) REFERENCES clients(id),
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

-- Internal: EXECOM → its own supplier, created directly (mirrors
-- files/SAMPLE P.O.pdf and Globalchips' PURCHTRN) — no requisition/canvass
-- step precedes it.
CREATE TABLE po_internal (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    po_number          VARCHAR(30)   NOT NULL UNIQUE,
    branch_id          INT           NOT NULL,
    supplier_id        INT           NULL,
    supplier_name      VARCHAR(255)  NULL,  -- snapshot as printed
    supplier_address   VARCHAR(255)  NULL,
    forwarder          VARCHAR(150)  NULL,
    ship_to_address    VARCHAR(255)  NULL,
    status             ENUM('draft','sent','partially_received','received','cancelled') NOT NULL DEFAULT 'draft',
    total_amount       DECIMAL(15,2) NOT NULL DEFAULT 0,
    created_by         INT           NOT NULL,
    is_deleted         TINYINT(1)    NOT NULL DEFAULT 0,
    created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
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
    date_received    DATETIME    NULL,
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
    date_delivered  DATETIME     NULL,
    received_by     VARCHAR(150) NULL,   -- client-side signatory (free text)
    date_accepted   DATETIME     NULL,
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
-- A branch IS a warehouse. warehouse_locations is that warehouse's own
-- floor plan: a small grid of named bins (grid_row/grid_col place it on the
-- Warehouse Map page). inventory_units/inventory_stock.location_id is where
-- a unit or a pooled bulk line currently sits — NULL means "in stock but not
-- yet put away." The free-text `location` column on inventory_units predates
-- this and is kept as a plain shelf/bin note; location_id is what the map
-- and the put-away/pick workflow actually use.

CREATE TABLE warehouse_locations (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    branch_id   INT          NOT NULL,
    code        VARCHAR(20)  NOT NULL,   -- e.g. "A-01"
    label       VARCHAR(100) NULL,       -- e.g. "Aisle A, Rack 1"
    grid_row    INT          NOT NULL DEFAULT 1,
    grid_col    INT          NOT NULL DEFAULT 1,
    capacity    INT          NULL,       -- max serialized units this bin holds; NULL = unlimited
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY loc_branch_code (branch_id, code),
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    INDEX idx_wl_branch (branch_id)
);

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
    date_received         DATETIME      NULL,
    date_out              DATETIME      NULL,
    location               VARCHAR(100)  NULL,   -- shelf/bin, free text (legacy note)
    location_id            INT          NULL,     -- structured bin on the Warehouse Map
    remarks               VARCHAR(255)  NULL,
    is_deleted            TINYINT(1)    NOT NULL DEFAULT 0,
    created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id),
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (source_rr_number) REFERENCES receiving_reports(rr_number),
    FOREIGN KEY (reserved_for_client_id) REFERENCES clients(id),
    FOREIGN KEY (location_id) REFERENCES warehouse_locations(id),
    INDEX idx_invu_branch (branch_id),
    INDEX idx_invu_item (item_id),
    INDEX idx_invu_serial (serial_number),
    INDEX idx_invu_status (status),
    INDEX idx_invu_location (location_id)
);

CREATE TABLE inventory_stock (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    item_id           INT NOT NULL,
    branch_id         INT NOT NULL,
    quantity_on_hand  DECIMAL(14,2) NOT NULL DEFAULT 0,
    reorder_level     DECIMAL(14,2) NOT NULL DEFAULT 0,
    location_id       INT NULL,   -- structured bin on the Warehouse Map (one home bin per pooled item/branch line)
    UNIQUE KEY item_branch (item_id, branch_id),
    FOREIGN KEY (item_id) REFERENCES items(id),
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (location_id) REFERENCES warehouse_locations(id)
);

-- Quick Scan: a serial can be scanned in before anyone has picked which item
-- it belongs to. It waits here until Segregate assigns it to an item, at
-- which point it becomes a normal row in inventory_units and is soft-deleted
-- here (see lib/quick_scan_inventory.php).
CREATE TABLE inventory_unassigned_units (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    branch_id      INT           NOT NULL,
    serial_number  VARCHAR(100)  NOT NULL,
    date_received  DATETIME      NULL,
    remarks        VARCHAR(255)  NULL,
    scanned_by     INT           NULL,
    is_deleted     TINYINT(1)    NOT NULL DEFAULT 0,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (scanned_by) REFERENCES users(id),
    INDEX idx_invunassigned_branch (branch_id),
    INDEX idx_invunassigned_serial (serial_number)
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
    walkin_name      VARCHAR(150) NULL,  -- walk-in buyer, only set when client_id is NULL
    walkin_address   VARCHAR(255) NULL,
    walkin_contact   VARCHAR(50)  NULL,
    po_number        VARCHAR(30) NULL,   -- fulfilling a specific government order
    issued_by        INT         NOT NULL,
    salesman_id      INT         NULL,   -- who sold this (mirrors Globalchips' SLS_SMFFK)
    date_created     DATETIME    NULL,
    total_amount     DECIMAL(15,2) NOT NULL DEFAULT 0,  -- net of discount_amount below
    discount_amount  DECIMAL(15,2) NOT NULL DEFAULT 0,  -- optional whole-sale discount; already prorated into issuance_items.total_price, so SUM(issuance_items.total_price) still equals total_amount
    is_deleted       TINYINT(1)  NOT NULL DEFAULT 0,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (client_id) REFERENCES clients(id),
    FOREIGN KEY (po_number) REFERENCES po_outgoing(po_number),
    FOREIGN KEY (issued_by) REFERENCES users(id),
    FOREIGN KEY (salesman_id) REFERENCES salesmen(id),
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
-- (already linked from po_internal), and Cost/Profit
-- Reports is read-only SQL over delivery_reports/po_outgoing (revenue) vs.
-- issuance_items -> inventory_units.unit_cost (matched cost).

-- ── 9. Job Order (Repair/Service) — first cut ────────────────────────────
-- Execom already runs computer repair tickets in real life (see
-- files/JOB ORDER.pdf from the live execom-online.com/ecss site); this
-- gives that workflow a home in NewEXECOM, modernized from Globalchips'
-- jobtrn/joblin pair, minus its payroll/GL-adjacent columns (out of scope).
-- Defined before Billing & Collections below, which bills a released job
-- order through a sales_invoices.jo_number FK.

CREATE TABLE job_orders (
    id                     INT AUTO_INCREMENT PRIMARY KEY,
    jo_number              VARCHAR(30)  NOT NULL UNIQUE,  -- doc_type JO
    branch_id              INT          NOT NULL,
    client_id              INT          NULL,   -- NULL = walk-in
    walkin_name            VARCHAR(150) NULL,
    walkin_contact         VARCHAR(50)  NULL,
    unit_description       VARCHAR(255) NOT NULL,
    serial_number          VARCHAR(100) NULL,
    problem_description    VARCHAR(255) NULL,
    accessories_received   VARCHAR(255) NULL,
    technician_id          INT          NULL,
    status                 ENUM('received','in_diagnosis','awaiting_parts','repaired','released','cancelled') NOT NULL DEFAULT 'received',
    is_under_warranty      TINYINT(1)   NOT NULL DEFAULT 0,
    labor_fee              DECIMAL(15,2) NOT NULL DEFAULT 0,  -- service charge, billed alongside parts
    date_received          DATETIME     NULL,
    date_released          DATETIME     NULL,
    remarks                VARCHAR(255) NULL,
    received_by            INT          NOT NULL,
    is_deleted             TINYINT(1)   NOT NULL DEFAULT 0,
    created_at             TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (client_id) REFERENCES clients(id),
    FOREIGN KEY (technician_id) REFERENCES users(id),
    FOREIGN KEY (received_by) REFERENCES users(id),
    INDEX idx_jo_branch (branch_id),
    INDEX idx_jo_status (status)
);

CREATE TABLE job_order_parts (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    jo_number          VARCHAR(30)   NOT NULL,
    inventory_unit_id  INT           NULL,   -- serialized part used
    item_id            INT           NULL,   -- bulk part used
    quantity           DECIMAL(11,2) NULL,   -- for bulk parts
    unit_cost          DECIMAL(15,2) NOT NULL DEFAULT 0,   -- cost basis, for margin reporting
    unit_price         DECIMAL(15,2) NOT NULL DEFAULT 0,   -- what the customer is charged
    is_deleted         TINYINT(1)    NOT NULL DEFAULT 0,
    FOREIGN KEY (jo_number) REFERENCES job_orders(jo_number),
    FOREIGN KEY (inventory_unit_id) REFERENCES inventory_units(id),
    FOREIGN KEY (item_id) REFERENCES items(id),
    INDEX idx_jop_jo (jo_number)
);

-- ── 10. Billing & Collections (AR) — Outgoing ────────────────────────────
-- Closes the gap the old Globalchips system covered and EXECOM didn't: an
-- issuance on credit terms needs a follow-up invoice balance, partial
-- payments, aging, and (LGU clients withhold tax by law) a net-of-tax
-- collection trail. Cash issuances settled on the spot never get a row here.

-- issuance_number / jo_number: an invoice is billed from EITHER a walk-in/
-- client issuance OR a completed Job Order's parts+labor — never both, but
-- both stay nullable FKs rather than a single polymorphic column so each
-- keeps its own real foreign key.
CREATE TABLE sales_invoices (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number   VARCHAR(30)   NOT NULL UNIQUE,   -- doc_type SI
    issuance_number  VARCHAR(30)   NULL,
    jo_number        VARCHAR(30)   NULL,
    client_id        INT           NULL,
    due_date         DATE          NULL,
    total_amount     DECIMAL(15,2) NOT NULL DEFAULT 0,
    amount_paid      DECIMAL(15,2) NOT NULL DEFAULT 0,
    balance          DECIMAL(15,2) NOT NULL DEFAULT 0,  -- kept in sync by the app on every applied collection
    status           ENUM('unpaid','partial','paid','void') NOT NULL DEFAULT 'unpaid',
    created_by       INT           NOT NULL,
    is_deleted       TINYINT(1)    NOT NULL DEFAULT 0,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (issuance_number) REFERENCES issuances(issuance_number),
    FOREIGN KEY (jo_number) REFERENCES job_orders(jo_number),
    FOREIGN KEY (client_id) REFERENCES clients(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_si_client (client_id),
    INDEX idx_si_status (status),
    INDEX idx_si_duedate (due_date),
    INDEX idx_si_jo (jo_number)
);

-- A customer payment (Official Receipt). One OR commonly settles several
-- invoices at once (an LGU check clearing a batch), hence the separate
-- applications table below rather than a single sales_invoice_id here.
CREATE TABLE collections (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    or_number               VARCHAR(30)   NOT NULL UNIQUE,  -- doc_type OR
    client_id               INT           NOT NULL,
    payment_date            DATE          NOT NULL,
    payment_method          ENUM('cash','check','bank_transfer') NOT NULL DEFAULT 'cash',
    gross_amount            DECIMAL(15,2) NOT NULL DEFAULT 0,
    withholding_tax_amount  DECIMAL(15,2) NOT NULL DEFAULT 0,  -- BIR Form 2307, common on LGU payments
    net_amount              DECIMAL(15,2) NOT NULL DEFAULT 0,  -- gross_amount - withholding_tax_amount
    remarks                 VARCHAR(255)  NULL,
    received_by             INT           NOT NULL,
    is_deleted              TINYINT(1)    NOT NULL DEFAULT 0,
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id),
    FOREIGN KEY (received_by) REFERENCES users(id),
    INDEX idx_col_client (client_id),
    INDEX idx_col_date (payment_date)
);

CREATE TABLE collection_applications (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    collection_id     INT           NOT NULL,
    sales_invoice_id  INT           NOT NULL,
    amount_applied    DECIMAL(15,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (collection_id) REFERENCES collections(id),
    FOREIGN KEY (sales_invoice_id) REFERENCES sales_invoices(id),
    INDEX idx_ca_collection (collection_id),
    INDEX idx_ca_invoice (sales_invoice_id)
);

-- Post-dated checks, either side: an LGU client's PDC held before deposit,
-- or one EXECOM issues to a supplier. collection_id/ppt fields link back
-- once the check actually clears and becomes a recorded collection.
-- Accounts Payable — the buy-side mirror of sales_invoices/collections
-- above. A receiving_report obligates payment to a supplier the same way an
-- issuance obligates a client to pay; mirrors Globalchips' PPAYTRN/ppaylin.
CREATE TABLE supplier_invoices (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number   VARCHAR(30)   NOT NULL UNIQUE,   -- doc_type PINV
    rr_number        VARCHAR(30)   NULL,
    supplier_id      INT           NOT NULL,
    due_date         DATE          NULL,
    total_amount     DECIMAL(15,2) NOT NULL DEFAULT 0,
    amount_paid      DECIMAL(15,2) NOT NULL DEFAULT 0,
    balance          DECIMAL(15,2) NOT NULL DEFAULT 0,  -- kept in sync by the app on every applied disbursement
    status           ENUM('unpaid','partial','paid','void') NOT NULL DEFAULT 'unpaid',
    created_by       INT           NOT NULL,
    is_deleted       TINYINT(1)    NOT NULL DEFAULT 0,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rr_number) REFERENCES receiving_reports(rr_number),
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_pinv_supplier (supplier_id),
    INDEX idx_pinv_status (status),
    INDEX idx_pinv_duedate (due_date),
    INDEX idx_pinv_rr (rr_number)
);

-- A payment EXECOM makes to a supplier (Disbursement Voucher). One DV can
-- settle several invoices at once, hence the separate applications table.
CREATE TABLE disbursements (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    dv_number      VARCHAR(30)   NOT NULL UNIQUE,  -- doc_type DV
    supplier_id    INT           NOT NULL,
    payment_date   DATE          NOT NULL,
    payment_method ENUM('cash','check','bank_transfer') NOT NULL DEFAULT 'cash',
    amount         DECIMAL(15,2) NOT NULL DEFAULT 0,
    remarks        VARCHAR(255)  NULL,
    paid_by        INT           NOT NULL,
    is_deleted     TINYINT(1)    NOT NULL DEFAULT 0,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    FOREIGN KEY (paid_by) REFERENCES users(id),
    INDEX idx_disb_supplier (supplier_id),
    INDEX idx_disb_date (payment_date)
);

CREATE TABLE disbursement_applications (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    disbursement_id      INT           NOT NULL,
    supplier_invoice_id  INT           NOT NULL,
    amount_applied       DECIMAL(15,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (disbursement_id) REFERENCES disbursements(id),
    FOREIGN KEY (supplier_invoice_id) REFERENCES supplier_invoices(id),
    INDEX idx_da_disbursement (disbursement_id),
    INDEX idx_da_invoice (supplier_invoice_id)
);

-- Post-dated checks, either side: an LGU client's PDC held before deposit,
-- or one EXECOM issues to a supplier. collection_id/disbursement_id link
-- back once the check actually clears and becomes a recorded payment.
CREATE TABLE post_dated_checks (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    pdc_number       VARCHAR(30)   NOT NULL UNIQUE,   -- doc_type PDC
    direction        ENUM('received','issued') NOT NULL,
    client_id        INT           NULL,   -- set when direction = received
    supplier_id      INT           NULL,   -- set when direction = issued
    check_number     VARCHAR(30)   NOT NULL,
    bank_name        VARCHAR(100)  NULL,
    check_date       DATE          NOT NULL,
    amount           DECIMAL(15,2) NOT NULL DEFAULT 0,
    status           ENUM('on_hand','deposited','cleared','bounced') NOT NULL DEFAULT 'on_hand',
    collection_id    INT           NULL,   -- linked once deposited/cleared as a collection (received side)
    disbursement_id  INT           NULL,   -- linked once cleared as a disbursement (issued side)
    remarks          VARCHAR(255)  NULL,
    created_by       INT           NOT NULL,
    is_deleted       TINYINT(1)    NOT NULL DEFAULT 0,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id),
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    FOREIGN KEY (collection_id) REFERENCES collections(id),
    FOREIGN KEY (disbursement_id) REFERENCES disbursements(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_pdc_status (status),
    INDEX idx_pdc_checkdate (check_date)
);

-- ── Seed data ─────────────────────────────────────────────────────────

INSERT INTO branches (code, name) VALUES
    ('MLB', 'Malaybalay City'),
    ('MRM', 'Maramag City'),
    ('DVO', 'Davao City'),
    ('CDO', 'Cagayan De Oro City'),
    ('VAL', 'Valencia City');

INSERT INTO ref_branches (code, name, is_all_branches, sort_order) VALUES
    ('ADMIN', 'ADMIN (Oversee all)', 1, 0),
    ('MLB', 'Malaybalay City', 0, 1),
    ('VAL', 'Valencia City', 0, 2),
    ('CDO', 'Cagayan De Oro City', 0, 3),
    ('MRM', 'Maramag City', 0, 4),
    ('DVO', 'Davao City', 0, 5);

INSERT INTO ref_roles (role_key, label, rank, sort_order) VALUES
    ('super_admin', 'Super Administrator', 3, 0),
    ('branch_admin', 'Branch Administrator', 2, 1),
    ('branch_user', 'Branch User', 1, 2);

INSERT INTO role_module_access (role, module_key, can_access) VALUES
    ('branch_user', 'settings', 0);

-- Default super_admin — username: admin / password: admin123
-- CHANGE THIS PASSWORD immediately after first login (via My Profile).
INSERT INTO users (username, password, full_name, first_name, last_name, role, branch_id) VALUES
('admin', '$2y$10$0w832hH3EFlb4KMRhAW6zOnCB9MVpvgCg8nP54Wkc/.mVdev4LG/u', 'System Administrator', 'System', 'Administrator', 'super_admin', NULL);

INSERT INTO departments (name) VALUES
    ('Sales'),
    ('Warehouse'),
    ('Technical/Service'),
    ('Admin');

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
