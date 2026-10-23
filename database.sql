-- ============================================================================
-- BACHAT (बचत) — Har Society Ki Smart Bachat
-- Complete MySQL / MariaDB schema + seed data
-- ----------------------------------------------------------------------------
-- HOW TO IMPORT (phpMyAdmin):
--   1. Create an empty database (e.g. `bachat`) with utf8mb4_general_ci.
--   2. Select it, open the "Import" tab and upload this file.
--   3. Then edit app/config/config.php with your DB credentials.
--
-- NOTES:
--   * All money columns are DECIMAL — never floats.
--   * Preloaded with editable Indian household categories, products, variants, and pack sizes.
--   * Production credentials are documented securely in CREDENTIALS.md / credentials.txt.
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- Settings (platform key/value store)
-- ----------------------------------------------------------------------------
CREATE TABLE settings (
  setting_key   VARCHAR(80)  NOT NULL,
  setting_value TEXT         NULL,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------------------
-- Societies
-- ----------------------------------------------------------------------------
CREATE TABLE societies (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name                VARCHAR(150) NOT NULL,
  code                VARCHAR(30)  NOT NULL,
  slug                VARCHAR(160) NOT NULL,
  city                VARCHAR(80)  NULL,
  state               VARCHAR(80)  NULL,
  address             VARCHAR(255) NULL,
  logo                VARCHAR(255) NULL,
  banner              VARCHAR(255) NULL,
  primary_color       VARCHAR(9)   NOT NULL DEFAULT '#28A66A',
  default_language    ENUM('en','hi','mr') NOT NULL DEFAULT 'en',
  status              ENUM('active','suspended') NOT NULL DEFAULT 'active',
  upi_id              VARCHAR(80)  NULL,
  qr_image            VARCHAR(255) NULL,
  delivery_charge     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  pickup_enabled      TINYINT(1)   NOT NULL DEFAULT 1,
  delivery_enabled    TINYINT(1)   NOT NULL DEFAULT 1,
  payment_verifier    ENUM('society_admin','super_admin') NOT NULL DEFAULT 'society_admin',
  commission_type     ENUM('fixed','percent') NOT NULL DEFAULT 'percent',
  commission_rate     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  subscription_plan   ENUM('none','monthly','yearly') NOT NULL DEFAULT 'none',
  subscription_amount DECIMAL(10,2) NULL,
  landing_text        VARCHAR(400) NULL,
  contact_email       VARCHAR(150) NULL,
  contact_phone       VARCHAR(20)  NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_society_code (code),
  UNIQUE KEY uq_society_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------------------
-- Users (all roles)
-- ----------------------------------------------------------------------------
CREATE TABLE users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name          VARCHAR(120) NOT NULL,
  email         VARCHAR(150) NOT NULL,
  phone         VARCHAR(20)  NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('super_admin','society_admin','resident','vendor','delivery_staff') NOT NULL,
  society_id    INT UNSIGNED NULL,
  status        ENUM('active','pending','suspended') NOT NULL DEFAULT 'active',
  language      ENUM('en','hi','mr') NOT NULL DEFAULT 'en',
  avatar        VARCHAR(255) NULL,
  last_login_at DATETIME NULL,
  failed_logins INT NOT NULL DEFAULT 0,
  locked_until  DATETIME NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_role_society (role, society_id),
  CONSTRAINT fk_users_society FOREIGN KEY (society_id) REFERENCES societies (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------------------
-- Flats
-- ----------------------------------------------------------------------------
CREATE TABLE flats (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  society_id  INT UNSIGNED NOT NULL,
  block       VARCHAR(20)  NOT NULL DEFAULT '',
  flat_number VARCHAR(30)  NOT NULL,
  flat_type   VARCHAR(40)  NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_flat (society_id, block, flat_number),
  CONSTRAINT fk_flats_society FOREIGN KEY (society_id) REFERENCES societies (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------------------
-- Memberships (society join requests + approvals)
-- ----------------------------------------------------------------------------
CREATE TABLE memberships (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id      INT UNSIGNED NOT NULL,
  society_id   INT UNSIGNED NOT NULL,
  flat_id      INT UNSIGNED NULL,
  member_role  ENUM('owner','tenant') NOT NULL DEFAULT 'owner',
  status       ENUM('pending','active','rejected','removed') NOT NULL DEFAULT 'pending',
  requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  decided_by   INT UNSIGNED NULL,
  decided_at   DATETIME NULL,
  note         VARCHAR(255) NULL,
  PRIMARY KEY (id),
  KEY idx_memb_user (user_id),
  KEY idx_memb_society_status (society_id, status),
  CONSTRAINT fk_memb_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_memb_society FOREIGN KEY (society_id) REFERENCES societies (id) ON DELETE CASCADE,
  CONSTRAINT fk_memb_flat FOREIGN KEY (flat_id) REFERENCES flats (id) ON DELETE SET NULL,
  CONSTRAINT fk_memb_decider FOREIGN KEY (decided_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------------------
-- Saved addresses
-- ----------------------------------------------------------------------------
CREATE TABLE addresses (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NOT NULL,
  label      VARCHAR(60)  NOT NULL DEFAULT 'Home',
  line1      VARCHAR(255) NOT NULL,
  city       VARCHAR(80)  NULL,
  pincode    VARCHAR(10)  NULL,
  is_default TINYINT(1)   NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_addr_user (user_id),
  CONSTRAINT fk_addr_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------------------
-- Catalog: categories, brands, products, variants, society prices
-- ----------------------------------------------------------------------------
CREATE TABLE categories (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  parent_id  INT UNSIGNED NULL,
  name_en    VARCHAR(100) NOT NULL,
  name_hi    VARCHAR(100) NULL,
  name_mr    VARCHAR(100) NULL,
  icon       VARCHAR(40)  NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active  TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY idx_cat_parent (parent_id),
  CONSTRAINT fk_cat_parent FOREIGN KEY (parent_id) REFERENCES categories (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE brands (
  id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name  VARCHAR(100) NOT NULL,
  logo  VARCHAR(255) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_brand_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE products (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  category_id   INT UNSIGNED NOT NULL,
  name_en       VARCHAR(150) NOT NULL,
  name_hi       VARCHAR(150) NULL,
  name_mr       VARCHAR(150) NULL,
  slug          VARCHAR(180) NOT NULL,
  sku           VARCHAR(40)  NULL,
  description   TEXT NULL,
  image         VARCHAR(255) NULL,
  unit          VARCHAR(10)  NOT NULL DEFAULT 'pc',
  fulfillment   ENUM('central','vendor_direct') NOT NULL DEFAULT 'central',
  vendor_id     INT UNSIGNED NULL,
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_product_slug (slug),
  KEY idx_product_cat (category_id, is_active),
  CONSTRAINT fk_product_cat FOREIGN KEY (category_id) REFERENCES categories (id),
  CONSTRAINT fk_product_vendor FOREIGN KEY (vendor_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE product_variants (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id      INT UNSIGNED NOT NULL,
  brand_id        INT UNSIGNED NULL,
  variant_name    VARCHAR(100) NULL,
  pack_size       DECIMAL(10,3) NOT NULL DEFAULT 1,
  unit            ENUM('g','kg','ml','l','pc','pack','bottle','bag','box') NOT NULL DEFAULT 'pc',
  sku             VARCHAR(40)  NULL,
  reference_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  selling_price   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  stock_qty       DECIMAL(10,3) NOT NULL DEFAULT 0,
  min_qty         DECIMAL(10,3) NOT NULL DEFAULT 1,
  max_qty         DECIMAL(10,3) NULL,
  is_active       TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY idx_variant_product (product_id, is_active),
  KEY idx_variant_brand (brand_id),
  CONSTRAINT fk_variant_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
  CONSTRAINT fk_variant_brand FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE society_prices (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  variant_id    INT UNSIGNED NOT NULL,
  society_id    INT UNSIGNED NOT NULL,
  selling_price DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_soc_price (variant_id, society_id),
  CONSTRAINT fk_sp_variant FOREIGN KEY (variant_id) REFERENCES product_variants (id) ON DELETE CASCADE,
  CONSTRAINT fk_sp_society FOREIGN KEY (society_id) REFERENCES societies (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------------------
-- Shopping: favourites, lists, reminders, cart
-- ----------------------------------------------------------------------------
CREATE TABLE favourites (
  user_id    INT UNSIGNED NOT NULL,
  variant_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, variant_id),
  CONSTRAINT fk_fav_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_fav_variant FOREIGN KEY (variant_id) REFERENCES product_variants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE saved_lists (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NOT NULL,
  name       VARCHAR(100) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_list_user (user_id),
  CONSTRAINT fk_list_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE saved_list_items (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  list_id    INT UNSIGNED NOT NULL,
  variant_id INT UNSIGNED NOT NULL,
  qty        DECIMAL(10,3) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_list_item (list_id, variant_id),
  CONSTRAINT fk_li_list FOREIGN KEY (list_id) REFERENCES saved_lists (id) ON DELETE CASCADE,
  CONSTRAINT fk_li_variant FOREIGN KEY (variant_id) REFERENCES product_variants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE reminders (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NOT NULL,
  list_id    INT UNSIGNED NULL,
  title      VARCHAR(150) NOT NULL,
  frequency  ENUM('weekly','monthly') NOT NULL DEFAULT 'weekly',
  next_run   DATE NOT NULL,
  is_active  TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_rem_user (user_id),
  CONSTRAINT fk_rem_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_rem_list FOREIGN KEY (list_id) REFERENCES saved_lists (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE carts (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NOT NULL,
  society_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cart_user (user_id),
  CONSTRAINT fk_cart_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_cart_society FOREIGN KEY (society_id) REFERENCES societies (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE cart_items (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  cart_id    INT UNSIGNED NOT NULL,
  variant_id INT UNSIGNED NOT NULL,
  qty        DECIMAL(10,3) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cart_item (cart_id, variant_id),
  CONSTRAINT fk_ci_cart FOREIGN KEY (cart_id) REFERENCES carts (id) ON DELETE CASCADE,
  CONSTRAINT fk_ci_variant FOREIGN KEY (variant_id) REFERENCES product_variants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------------------
-- Coupons (created before orders — orders may hold a coupon FK)
-- ----------------------------------------------------------------------------
CREATE TABLE coupons (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code           VARCHAR(40)  NOT NULL,
  title          VARCHAR(150) NULL,
  type           ENUM('fixed','percent') NOT NULL DEFAULT 'fixed',
  value          DECIMAL(10,2) NOT NULL DEFAULT 0,
  min_cart_value DECIMAL(10,2) NOT NULL DEFAULT 0,
  max_discount   DECIMAL(10,2) NULL,
  usage_limit    INT NULL,
  used_count     INT NOT NULL DEFAULT 0,
  per_user_limit INT NOT NULL DEFAULT 1,
  valid_from     DATETIME NULL,
  valid_to       DATETIME NULL,
  society_id     INT UNSIGNED NULL,
  funding_source ENUM('platform','society','vendor') NOT NULL DEFAULT 'platform',
  stacking       TINYINT(1) NOT NULL DEFAULT 0,
  is_active      TINYINT(1) NOT NULL DEFAULT 1,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_coupon_code (code),
  KEY idx_coupon_society (society_id),
  CONSTRAINT fk_coupon_society FOREIGN KEY (society_id) REFERENCES societies (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------------------
-- Orders (immutable price snapshots in order_items)
-- ----------------------------------------------------------------------------
CREATE TABLE orders (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_no      VARCHAR(24)  NOT NULL,
  user_id       INT UNSIGNED NOT NULL,
  society_id    INT UNSIGNED NOT NULL,
  type          ENUM('regular','campaign','repeat') NOT NULL DEFAULT 'regular',
  status        ENUM('pending','confirmed','packed','ready','out_for_delivery','delivered','cancelled','failed') NOT NULL DEFAULT 'pending',
  subtotal      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  discount      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  delivery_fee  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  tax           DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  coupon_id     INT UNSIGNED NULL,
  address_text  VARCHAR(400) NULL,
  contact_name  VARCHAR(120) NULL,
  contact_phone VARCHAR(20)  NULL,
  fulfilment    ENUM('pickup','delivery') NOT NULL DEFAULT 'pickup',
  slot_date     DATE NULL,
  slot_label    VARCHAR(60)  NULL,
  cancel_reason VARCHAR(255) NULL,
  placed_at     DATETIME NULL,
  delivered_at  DATETIME NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_order_no (order_no),
  KEY idx_order_user (user_id),
  KEY idx_order_society (society_id, status),
  CONSTRAINT fk_order_user FOREIGN KEY (user_id) REFERENCES users (id),
  CONSTRAINT fk_order_society FOREIGN KEY (society_id) REFERENCES societies (id),
  CONSTRAINT fk_order_coupon FOREIGN KEY (coupon_id) REFERENCES coupons (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE order_items (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id     INT UNSIGNED NOT NULL,
  variant_id   INT UNSIGNED NULL,
  product_name VARCHAR(150) NOT NULL,
  brand_name   VARCHAR(100) NULL,
  pack_size    DECIMAL(10,3) NULL,
  unit         VARCHAR(10)  NULL,
  qty          DECIMAL(10,3) NOT NULL,
  unit_price   DECIMAL(10,2) NOT NULL,
  line_total   DECIMAL(12,2) NOT NULL,
  PRIMARY KEY (id),
  KEY idx_oi_order (order_id),
  CONSTRAINT fk_oi_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
  CONSTRAINT fk_oi_variant FOREIGN KEY (variant_id) REFERENCES product_variants (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE order_events (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id   INT UNSIGNED NOT NULL,
  status     VARCHAR(30)  NOT NULL,
  note       VARCHAR(255) NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_oe_order (order_id),
  CONSTRAINT fk_oe_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
  CONSTRAINT fk_oe_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE coupon_redemptions (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  coupon_id  INT UNSIGNED NOT NULL,
  order_id   INT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NOT NULL,
  amount     DECIMAL(10,2) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_cr_coupon (coupon_id),
  KEY idx_cr_user (user_id),
  CONSTRAINT fk_cr_coupon FOREIGN KEY (coupon_id) REFERENCES coupons (id) ON DELETE CASCADE,
  CONSTRAINT fk_cr_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
  CONSTRAINT fk_cr_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------------------
-- Bulk campaigns + aggregation + interests
-- ----------------------------------------------------------------------------
CREATE TABLE campaigns (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  society_id     INT UNSIGNED NULL,            -- NULL = platform-wide campaign
  title          VARCHAR(180) NOT NULL,
  description    TEXT NULL,
  type           ENUM('grocery','product') NOT NULL DEFAULT 'grocery',
  status         ENUM('draft','active','extended','closed','failed','cancelled') NOT NULL DEFAULT 'draft',
  starts_at      DATETIME NULL,
  deadline       DATETIME NULL,
  extension_count INT NOT NULL DEFAULT 0,
  delivery_cost  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  tax_percent    DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
  lead_time_days INT NOT NULL DEFAULT 7,
  created_by     INT UNSIGNED NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_campaign_status (status, deadline),
  KEY idx_campaign_society (society_id),
  CONSTRAINT fk_campaign_society FOREIGN KEY (society_id) REFERENCES societies (id) ON DELETE CASCADE,
  CONSTRAINT fk_campaign_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE campaign_items (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  campaign_id     INT UNSIGNED NOT NULL,
  variant_id      INT UNSIGNED NOT NULL,
  target_qty      DECIMAL(10,3) NOT NULL DEFAULT 0,
  min_qty         DECIMAL(10,3) NOT NULL DEFAULT 0,
  aggregated_qty  DECIMAL(10,3) NOT NULL DEFAULT 0,
  quoted_price    DECIMAL(10,2) NULL,
  confirmed_price DECIMAL(10,2) NULL,
  distributed_qty DECIMAL(10,3) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_ci_campaign (campaign_id),
  CONSTRAINT fk_ci_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns (id) ON DELETE CASCADE,
  CONSTRAINT fk_cpi_variant FOREIGN KEY (variant_id) REFERENCES product_variants (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE campaign_interests (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  campaign_id INT UNSIGNED NOT NULL,
  user_id     INT UNSIGNED NOT NULL,
  status      ENUM('interested','confirmed','cancelled') NOT NULL DEFAULT 'interested',
  note        VARCHAR(255) NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_camp_interest (campaign_id, user_id),
  CONSTRAINT fk_cint_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns (id) ON DELETE CASCADE,
  CONSTRAINT fk_cint_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE campaign_interest_items (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  interest_id      INT UNSIGNED NOT NULL,
  campaign_item_id INT UNSIGNED NOT NULL,
  qty              DECIMAL(10,3) NOT NULL DEFAULT 1,
  est_price        DECIMAL(10,2) NULL,
  confirmed_price  DECIMAL(10,2) NULL,
  PRIMARY KEY (id),
  KEY idx_cii_interest (interest_id),
  CONSTRAINT fk_cii_interest FOREIGN KEY (interest_id) REFERENCES campaign_interests (id) ON DELETE CASCADE,
  CONSTRAINT fk_cii_item FOREIGN KEY (campaign_item_id) REFERENCES campaign_items (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------------------
-- Vendor quotations + purchase orders
-- ----------------------------------------------------------------------------
CREATE TABLE quotations (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  campaign_id   INT UNSIGNED NOT NULL,
  vendor_id     INT UNSIGNED NOT NULL,
  unit_price    DECIMAL(10,2) NOT NULL,
  min_qty       DECIMAL(10,3) NOT NULL DEFAULT 0,
  delivery_cost DECIMAL(10,2) NOT NULL DEFAULT 0,
  tax_percent   DECIMAL(5,2)  NOT NULL DEFAULT 0,
  lead_time_days INT NOT NULL DEFAULT 7,
  notes         VARCHAR(400) NULL,
  status        ENUM('submitted','selected','rejected') NOT NULL DEFAULT 'submitted',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_quot_campaign (campaign_id),
  KEY idx_quot_vendor (vendor_id),
  CONSTRAINT fk_quot_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns (id) ON DELETE CASCADE,
  CONSTRAINT fk_quot_vendor FOREIGN KEY (vendor_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE purchase_orders (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  po_no       VARCHAR(24) NOT NULL,
  campaign_id INT UNSIGNED NULL,
  vendor_id   INT UNSIGNED NOT NULL,
  society_id  INT UNSIGNED NULL,
  total       DECIMAL(12,2) NOT NULL DEFAULT 0,
  status      ENUM('draft','sent','accepted','partially_received','received','cancelled') NOT NULL DEFAULT 'draft',
  expected_at DATE NULL,
  created_by  INT UNSIGNED NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_po_no (po_no),
  KEY idx_po_vendor (vendor_id),
  CONSTRAINT fk_po_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns (id) ON DELETE SET NULL,
  CONSTRAINT fk_po_vendor FOREIGN KEY (vendor_id) REFERENCES users (id),
  CONSTRAINT fk_po_society FOREIGN KEY (society_id) REFERENCES societies (id) ON DELETE SET NULL,
  CONSTRAINT fk_po_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE purchase_order_items (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  po_id        INT UNSIGNED NOT NULL,
  variant_id   INT UNSIGNED NOT NULL,
  qty          DECIMAL(10,3) NOT NULL,
  received_qty DECIMAL(10,3) NOT NULL DEFAULT 0,
  unit_price   DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (id),
  KEY idx_poi_po (po_id),
  CONSTRAINT fk_poi_po FOREIGN KEY (po_id) REFERENCES purchase_orders (id) ON DELETE CASCADE,
  CONSTRAINT fk_poi_variant FOREIGN KEY (variant_id) REFERENCES product_variants (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------------------
-- Inventory (central stock) + movements
-- ----------------------------------------------------------------------------
CREATE TABLE inventory (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  variant_id INT UNSIGNED NOT NULL,
  received   DECIMAL(10,3) NOT NULL DEFAULT 0,
  reserved   DECIMAL(10,3) NOT NULL DEFAULT 0,
  dispatched DECIMAL(10,3) NOT NULL DEFAULT 0,
  returned   DECIMAL(10,3) NOT NULL DEFAULT 0,
  damaged    DECIMAL(10,3) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_inv_variant (variant_id),
  CONSTRAINT fk_inv_variant FOREIGN KEY (variant_id) REFERENCES product_variants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE stock_movements (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  variant_id INT UNSIGNED NOT NULL,
  type       ENUM('in','out','reserve','release','dispatch','return','damage','adjust') NOT NULL,
  qty        DECIMAL(10,3) NOT NULL,
  ref_type   VARCHAR(30)  NULL,
  ref_id     INT UNSIGNED NULL,
  note       VARCHAR(255) NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_sm_variant (variant_id),
  CONSTRAINT fk_sm_variant FOREIGN KEY (variant_id) REFERENCES product_variants (id) ON DELETE CASCADE,
  CONSTRAINT fk_sm_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------------------
-- Home services + group campaigns + bookings
-- ----------------------------------------------------------------------------
CREATE TABLE services (
  id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  category             VARCHAR(60)  NOT NULL DEFAULT 'General',
  name_en              VARCHAR(150) NOT NULL,
  name_hi              VARCHAR(150) NULL,
  name_mr              VARCHAR(150) NULL,
  description          TEXT NULL,
  base_price           DECIMAL(10,2) NOT NULL DEFAULT 0,
  duration_mins        INT NOT NULL DEFAULT 60,
  vendor_id            INT UNSIGNED NULL,
  area                 VARCHAR(120) NULL,
  image                VARCHAR(255) NULL,
  cancellation_policy  VARCHAR(400) NULL,
  is_active            TINYINT(1) NOT NULL DEFAULT 1,
  created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_service_active (is_active),
  CONSTRAINT fk_service_vendor FOREIGN KEY (vendor_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE service_campaigns (
  id                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  society_id             INT UNSIGNED NOT NULL,
  service_id             INT UNSIGNED NOT NULL,
  title                  VARCHAR(180) NOT NULL,
  description            TEXT NULL,
  deadline               DATETIME NULL,
  min_participants       INT NOT NULL DEFAULT 5,
  group_discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
  estimated_price        DECIMAL(10,2) NULL,
  final_price            DECIMAL(10,2) NULL,
  slot_date              DATE NULL,
  slot_time              TIME NULL,
  vendor_id              INT UNSIGNED NULL,
  status                 ENUM('open','pricing','confirm_open','closed','cancelled') NOT NULL DEFAULT 'open',
  created_by             INT UNSIGNED NULL,
  created_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_svc_campaign (society_id, status),
  CONSTRAINT fk_svc_society FOREIGN KEY (society_id) REFERENCES societies (id) ON DELETE CASCADE,
  CONSTRAINT fk_svc_service FOREIGN KEY (service_id) REFERENCES services (id),
  CONSTRAINT fk_svc_vendor FOREIGN KEY (vendor_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_svc_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE service_interests (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  campaign_id     INT UNSIGNED NOT NULL,
  user_id         INT UNSIGNED NOT NULL,
  status          ENUM('interested','confirmed','cancelled') NOT NULL DEFAULT 'interested',
  confirmed_price DECIMAL(10,2) NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_svc_interest (campaign_id, user_id),
  CONSTRAINT fk_si_campaign FOREIGN KEY (campaign_id) REFERENCES service_campaigns (id) ON DELETE CASCADE,
  CONSTRAINT fk_si_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE bookings (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  booking_no          VARCHAR(24) NOT NULL,
  user_id             INT UNSIGNED NOT NULL,
  society_id          INT UNSIGNED NOT NULL,
  service_id          INT UNSIGNED NOT NULL,
  service_campaign_id INT UNSIGNED NULL,
  vendor_id           INT UNSIGNED NULL,
  slot_at             DATETIME NOT NULL,
  duration_mins       INT NOT NULL DEFAULT 60,
  status              ENUM('pending','confirmed','assigned','in_progress','completed','cancelled','rescheduled') NOT NULL DEFAULT 'pending',
  price               DECIMAL(10,2) NOT NULL DEFAULT 0,
  address_text        VARCHAR(400) NULL,
  contact_phone       VARCHAR(20)  NULL,
  notes               VARCHAR(400) NULL,
  otp_code            VARCHAR(6)   NULL,
  proof_image         VARCHAR(255) NULL,
  cancel_reason       VARCHAR(255) NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_booking_no (booking_no),
  KEY idx_booking_user (user_id),
  KEY idx_booking_society (society_id, status),
  KEY idx_booking_vendor (vendor_id),
  CONSTRAINT fk_booking_user FOREIGN KEY (user_id) REFERENCES users (id),
  CONSTRAINT fk_booking_society FOREIGN KEY (society_id) REFERENCES societies (id),
  CONSTRAINT fk_booking_service FOREIGN KEY (service_id) REFERENCES services (id),
  CONSTRAINT fk_booking_campaign FOREIGN KEY (service_campaign_id) REFERENCES service_campaigns (id) ON DELETE SET NULL,
  CONSTRAINT fk_booking_vendor FOREIGN KEY (vendor_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------------------
-- Delivery tasks
-- ----------------------------------------------------------------------------
CREATE TABLE delivery_tasks (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id    INT UNSIGNED NULL,
  booking_id  INT UNSIGNED NULL,
  society_id  INT UNSIGNED NOT NULL,
  assigned_to INT UNSIGNED NOT NULL,
  status      ENUM('assigned','picked','out_for_delivery','delivered','failed') NOT NULL DEFAULT 'assigned',
  otp         VARCHAR(6)  NULL,
  proof_image VARCHAR(255) NULL,
  fail_reason VARCHAR(255) NULL,
  attempts    INT NOT NULL DEFAULT 0,
  delivered_at DATETIME NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_dt_staff (assigned_to, status),
  KEY idx_dt_society (society_id),
  CONSTRAINT fk_dt_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
  CONSTRAINT fk_dt_booking FOREIGN KEY (booking_id) REFERENCES bookings (id) ON DELETE CASCADE,
  CONSTRAINT fk_dt_society FOREIGN KEY (society_id) REFERENCES societies (id),
  CONSTRAINT fk_dt_staff FOREIGN KEY (assigned_to) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------------------
-- Payments + refunds
-- ----------------------------------------------------------------------------
CREATE TABLE payments (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id     INT UNSIGNED NULL,
  booking_id   INT UNSIGNED NULL,
  society_id   INT UNSIGNED NULL,
  user_id      INT UNSIGNED NOT NULL,
  amount       DECIMAL(12,2) NOT NULL,
  method       ENUM('upi','cod','gateway') NOT NULL DEFAULT 'upi',
  txn_ref      VARCHAR(80) NULL,
  utr          VARCHAR(80) NULL,
  proof_image  VARCHAR(255) NULL,
  status       ENUM('pending','submitted','verified','failed','refunded') NOT NULL DEFAULT 'pending',
  verified_by  INT UNSIGNED NULL,
  verified_at  DATETIME NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pay_order (order_id),
  KEY idx_pay_booking (booking_id),
  KEY idx_pay_status (status),
  CONSTRAINT fk_pay_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
  CONSTRAINT fk_pay_booking FOREIGN KEY (booking_id) REFERENCES bookings (id) ON DELETE CASCADE,
  CONSTRAINT fk_pay_society FOREIGN KEY (society_id) REFERENCES societies (id) ON DELETE SET NULL,
  CONSTRAINT fk_pay_user FOREIGN KEY (user_id) REFERENCES users (id),
  CONSTRAINT fk_pay_verifier FOREIGN KEY (verified_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE refunds (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id    INT UNSIGNED NULL,
  payment_id  INT UNSIGNED NULL,
  booking_id  INT UNSIGNED NULL,
  amount      DECIMAL(12,2) NOT NULL,
  reason      VARCHAR(255) NULL,
  status      ENUM('requested','approved','processed','rejected') NOT NULL DEFAULT 'requested',
  processed_by INT UNSIGNED NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ref_order (order_id),
  CONSTRAINT fk_ref_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE SET NULL,
  CONSTRAINT fk_ref_payment FOREIGN KEY (payment_id) REFERENCES payments (id) ON DELETE SET NULL,
  CONSTRAINT fk_ref_booking FOREIGN KEY (booking_id) REFERENCES bookings (id) ON DELETE SET NULL,
  CONSTRAINT fk_ref_user FOREIGN KEY (processed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------------------
-- SaaS: subscriptions + commissions
-- ----------------------------------------------------------------------------
CREATE TABLE subscriptions (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  society_id INT UNSIGNED NOT NULL,
  plan       ENUM('monthly','yearly') NOT NULL DEFAULT 'monthly',
  amount     DECIMAL(10,2) NOT NULL DEFAULT 0,
  status     ENUM('pending_payment','active','expired','suspended') NOT NULL DEFAULT 'pending_payment',
  starts_at  DATE NULL,
  ends_at    DATE NULL,
  paid_at    DATETIME NULL,
  note       VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_sub_society (society_id, status),
  CONSTRAINT fk_sub_society FOREIGN KEY (society_id) REFERENCES societies (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE commissions (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id   INT UNSIGNED NULL,
  booking_id INT UNSIGNED NULL,
  society_id INT UNSIGNED NOT NULL,
  vendor_id  INT UNSIGNED NULL,
  type       ENUM('fixed','percent') NOT NULL DEFAULT 'percent',
  rate       DECIMAL(10,2) NOT NULL DEFAULT 0,
  amount     DECIMAL(12,2) NOT NULL DEFAULT 0,
  status     ENUM('due','recorded') NOT NULL DEFAULT 'due',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_comm_society (society_id),
  CONSTRAINT fk_comm_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE SET NULL,
  CONSTRAINT fk_comm_booking FOREIGN KEY (booking_id) REFERENCES bookings (id) ON DELETE SET NULL,
  CONSTRAINT fk_comm_society FOREIGN KEY (society_id) REFERENCES societies (id),
  CONSTRAINT fk_comm_vendor FOREIGN KEY (vendor_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------------------
-- Reviews (verified purchase gating happens in code)
-- ----------------------------------------------------------------------------
CREATE TABLE reviews (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NOT NULL,
  society_id  INT UNSIGNED NULL,
  target_type ENUM('product','vendor','service','delivery') NOT NULL,
  target_id   INT UNSIGNED NOT NULL,
  order_id    INT UNSIGNED NULL,
  booking_id  INT UNSIGNED NULL,
  rating      TINYINT NOT NULL DEFAULT 5,
  title       VARCHAR(150) NULL,
  body        TEXT NULL,
  photo       VARCHAR(255) NULL,
  status      ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  reply       TEXT NULL,
  replied_at  DATETIME NULL,
  report_count INT NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_rev_target (target_type, target_id, status),
  KEY idx_rev_user (user_id),
  CONSTRAINT fk_rev_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_rev_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE SET NULL,
  CONSTRAINT fk_rev_booking FOREIGN KEY (booking_id) REFERENCES bookings (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------------------
-- Support tickets
-- ----------------------------------------------------------------------------
CREATE TABLE support_tickets (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_no       VARCHAR(24) NOT NULL,
  user_id         INT UNSIGNED NOT NULL,
  society_id      INT UNSIGNED NULL,
  subject         VARCHAR(200) NOT NULL,
  category        VARCHAR(60)  NOT NULL DEFAULT 'General',
  priority        ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  status          ENUM('open','in_progress','waiting','resolved','closed','reopened') NOT NULL DEFAULT 'open',
  order_id        INT UNSIGNED NULL,
  booking_id      INT UNSIGNED NULL,
  assigned_to     INT UNSIGNED NULL,
  sla_due_at      DATETIME NULL,
  feedback_rating TINYINT NULL,
  closed_at       DATETIME NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ticket_no (ticket_no),
  KEY idx_ticket_user (user_id),
  KEY idx_ticket_society (society_id, status),
  CONSTRAINT fk_ticket_user FOREIGN KEY (user_id) REFERENCES users (id),
  CONSTRAINT fk_ticket_society FOREIGN KEY (society_id) REFERENCES societies (id) ON DELETE SET NULL,
  CONSTRAINT fk_ticket_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE SET NULL,
  CONSTRAINT fk_ticket_booking FOREIGN KEY (booking_id) REFERENCES bookings (id) ON DELETE SET NULL,
  CONSTRAINT fk_ticket_assignee FOREIGN KEY (assigned_to) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE ticket_messages (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_id  INT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NOT NULL,
  message    TEXT NOT NULL,
  attachment VARCHAR(255) NULL,
  is_staff   TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tm_ticket (ticket_id),
  CONSTRAINT fk_tm_ticket FOREIGN KEY (ticket_id) REFERENCES support_tickets (id) ON DELETE CASCADE,
  CONSTRAINT fk_tm_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------------------
-- Announcements
-- ----------------------------------------------------------------------------
CREATE TABLE announcements (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  society_id INT UNSIGNED NULL,
  title      VARCHAR(200) NOT NULL,
  body       TEXT NOT NULL,
  image      VARCHAR(255) NULL,
  attachment VARCHAR(255) NULL,
  audience   ENUM('all','residents','admins') NOT NULL DEFAULT 'all',
  is_pinned  TINYINT(1) NOT NULL DEFAULT 0,
  publish_at DATETIME NULL,
  expires_at DATETIME NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ann_society (society_id),
  CONSTRAINT fk_ann_society FOREIGN KEY (society_id) REFERENCES societies (id) ON DELETE CASCADE,
  CONSTRAINT fk_ann_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE announcement_reads (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  announcement_id INT UNSIGNED NOT NULL,
  user_id        INT UNSIGNED NOT NULL,
  read_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ann_read (announcement_id, user_id),
  CONSTRAINT fk_ar_ann FOREIGN KEY (announcement_id) REFERENCES announcements (id) ON DELETE CASCADE,
  CONSTRAINT fk_ar_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------------------
-- Notifications
-- ----------------------------------------------------------------------------
CREATE TABLE notifications (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NOT NULL,
  type       VARCHAR(40)  NOT NULL DEFAULT 'general',
  title      VARCHAR(180) NOT NULL,
  body       VARCHAR(500) NULL,
  link       VARCHAR(255) NULL,
  is_read    TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notif_user (user_id, is_read),
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------------------
-- Demand voting
-- ----------------------------------------------------------------------------
CREATE TABLE demand_votes (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  society_id  INT UNSIGNED NULL,
  user_id     INT UNSIGNED NOT NULL,
  item_type   ENUM('product','service') NOT NULL DEFAULT 'product',
  title       VARCHAR(180) NOT NULL,
  details     VARCHAR(600) NULL,
  category_id INT UNSIGNED NULL,
  status      ENUM('open','validated','converted','rejected') NOT NULL DEFAULT 'open',
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_dv_society (society_id, status),
  CONSTRAINT fk_dv_society FOREIGN KEY (society_id) REFERENCES societies (id) ON DELETE CASCADE,
  CONSTRAINT fk_dv_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_dv_cat FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE demand_vote_interests (
  id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  vote_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_dvi (vote_id, user_id),
  CONSTRAINT fk_dvi_vote FOREIGN KEY (vote_id) REFERENCES demand_votes (id) ON DELETE CASCADE,
  CONSTRAINT fk_dvi_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------------------
-- Security: audit logs + login attempts
-- ----------------------------------------------------------------------------
CREATE TABLE audit_logs (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NULL,
  action      VARCHAR(60)  NOT NULL,
  entity_type VARCHAR(40)  NULL,
  entity_id   INT UNSIGNED NULL,
  details     TEXT NULL,
  ip          VARCHAR(45)  NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_user (user_id),
  KEY idx_audit_action (action),
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE login_attempts (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email      VARCHAR(150) NULL,
  ip         VARCHAR(45)  NULL,
  success    TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_la_email (email),
  KEY idx_la_ip (ip)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- SEED DATA (all sample data — edit freely from the admin panels)
-- ============================================================================

INSERT INTO settings (setting_key, setting_value) VALUES
('support_email', 'support@bachat.test'),
('support_phone', '+91 90000 00000'),
('sample_prices_note', 'All seeded prices are SAMPLE prices for demonstration — not live market rates.'),
('default_commission_percent', '2.50'),
('whatsapp_enabled', '0'),
('sms_enabled', '0'),
('browser_push_enabled', '0');

INSERT INTO societies (id, name, code, slug, city, state, address, primary_color, default_language, status, upi_id, delivery_charge, pickup_enabled, delivery_enabled, payment_verifier, commission_type, commission_rate, subscription_plan, subscription_amount, landing_text, contact_email, contact_phone) VALUES
(1, 'Green Meadow Residency', 'GMRES', 'green-meadow-residency', 'Pune', 'Maharashtra', 'Baner Road, Pune 411045', '#28A66A', 'en', 'active', 'greenmeadow@upi', 20.00, 1, 1, 'society_admin', 'percent', 2.50, 'monthly', 499.00, 'A green, gated community of 240 families. Bulk-buy your monthly groceries together and save every month.', 'office@greenmeadow.test', '+91 90000 11111'),
(2, 'Sunrise Heights', 'SUNH', 'sunrise-heights', 'Nashik', 'Maharashtra', 'Gangapur Road, Nashik 422013', '#F4B942', 'hi', 'active', 'sunriseh@upi', 25.00, 1, 1, 'society_admin', 'percent', 3.00, 'yearly', 4999.00, 'आपकी सोसाइटी, आपकी बचत — मिलकर खरीदें, स्मार्ट बचत करें।', 'office@sunriseheights.test', '+91 90000 22222');

INSERT INTO users (id, name, email, phone, password_hash, role, society_id, status, language) VALUES
(1, 'Platform Owner', 'admin@bachat.com', '+91 90000 00001', '$2y$12$VEUqW9w52hxWK7pseSh1DOEwGmLPES2xXDDLY2sZJLzUrRYckyrya', 'super_admin', NULL, 'active', 'en'),
(2, 'Anita Deshmukh', 'society@bachat.com', '+91 90000 00002', '$2y$12$VEUqW9w52hxWK7pseSh1DOEwGmLPES2xXDDLY2sZJLzUrRYckyrya', 'society_admin', 1, 'active', 'en'),
(3, 'Rakesh Verma', 'society2@bachat.com', '+91 90000 00003', '$2y$12$VEUqW9w52hxWK7pseSh1DOEwGmLPES2xXDDLY2sZJLzUrRYckyrya', 'society_admin', 2, 'active', 'hi'),
(4, 'Priya Sharma', 'customer@bachat.com', '+91 90000 00004', '$2y$12$VEUqW9w52hxWK7pseSh1DOEwGmLPES2xXDDLY2sZJLzUrRYckyrya', 'resident', NULL, 'active', 'en'),
(5, 'Rahul Patil', 'customer2@bachat.com', '+91 90000 00005', '$2y$12$VEUqW9w52hxWK7pseSh1DOEwGmLPES2xXDDLY2sZJLzUrRYckyrya', 'resident', NULL, 'active', 'mr'),
(6, 'FreshFarms Wholesale', 'vendor@bachat.com', '+91 90000 00006', '$2y$12$VEUqW9w52hxWK7pseSh1DOEwGmLPES2xXDDLY2sZJLzUrRYckyrya', 'vendor', NULL, 'active', 'en'),
(7, 'SwiftServe Facilities', 'vendor2@bachat.com', '+91 90000 00007', '$2y$12$VEUqW9w52hxWK7pseSh1DOEwGmLPES2xXDDLY2sZJLzUrRYckyrya', 'vendor', NULL, 'active', 'en'),
(8, 'Vijay Kale', 'delivery@bachat.com', '+91 90000 00008', '$2y$12$VEUqW9w52hxWK7pseSh1DOEwGmLPES2xXDDLY2sZJLzUrRYckyrya', 'delivery_staff', 1, 'active', 'mr'),
(9, 'Meena Jadhav', 'delivery2@bachat.com', '+91 90000 00009', '$2y$12$VEUqW9w52hxWK7pseSh1DOEwGmLPES2xXDDLY2sZJLzUrRYckyrya', 'delivery_staff', 2, 'active', 'hi');

INSERT INTO flats (id, society_id, block, flat_number, flat_type) VALUES
(1, 1, 'A', '101', '3 BHK'), (2, 1, 'A', '102', '2 BHK'), (3, 1, 'B', '201', '3 BHK'),
(4, 2, 'C', '501', '2 BHK'), (5, 2, 'C', '502', '3 BHK');

INSERT INTO memberships (id, user_id, society_id, flat_id, member_role, status, decided_by, decided_at) VALUES
(1, 4, 1, 1, 'owner', 'active', 2, NOW()),
(2, 5, 2, 4, 'tenant', 'pending', NULL, NULL);

INSERT INTO addresses (id, user_id, label, line1, city, pincode, is_default) VALUES
(1, 4, 'Home', 'Flat A-101, Green Meadow Residency, Baner Road', 'Pune', '411045', 1);

INSERT INTO `categories` (`id`, `parent_id`, `name_en`, `name_hi`, `name_mr`, `icon`, `sort_order`, `is_active`) VALUES
(1, NULL, 'Groceries & Staples', 'किराना और स्टेपल्स', 'किराणा व स्टेपल्स', 'shopping-basket', 1, 1),
(2, NULL, 'Household Cleaning', 'घर की सफाई', 'घराची स्वच्छता', 'spray-can', 2, 1),
(3, NULL, 'Personal & Baby Care', 'पर्सनल और बेबी केयर', 'वैयक्तिक काळजी', 'heart', 3, 1),
(4, NULL, 'Dairy, Breakfast & Beverages', 'डेयरी, नाश्ता और पेय', 'दुग्धजन्य व चहा', 'coffee', 4, 1),
(5, NULL, 'Home Essentials & Stationery', 'गृह उपयोगी व स्टेशनरी', 'गृहोपयोगी व स्टेशनरी', 'pencil', 5, 1),
(10, 1, 'Rice & Grains', 'चावल और अनाज', 'तांदूळ व धान्य', 'wheat', 1, 1),
(11, 1, 'Atta & Flours', 'आटा और बेसन', 'पीठ व रवा', 'wheat', 2, 1),
(12, 1, 'Dal & Pulses', 'दालें और दलहन', 'डाळी व कडधान्ये', 'bean', 3, 1),
(13, 1, 'Cooking Oil & Ghee', 'तेल और घी', 'तेल व तूप', 'droplets', 4, 1),
(14, 1, 'Sugar, Jaggery & Salt', 'चीनी, गुड़ और नमक', 'साखर, गूळ व मीठ', 'candy', 5, 1),
(15, 1, 'Spices & Masalas', 'मसाले', 'मसाले', 'flame', 6, 1),
(20, 2, 'Detergents & Laundry', 'डिटर्जेंट और कपड़े धोना', 'डिटर्जंट व कपडे धुणे', 'shirt', 1, 1),
(21, 2, 'Dishwashing', 'बर्तन साफ करना', 'भांडी धुणे', 'utensils', 2, 1),
(22, 2, 'Floor & Bathroom Cleaners', 'फ्लोर व टॉयलेट क्लीनर', 'फ्लोर व स्नानगृह क्लीनर', 'brush', 3, 1),
(23, 2, 'Garbage Bags & Tools', 'कचरा बैग और औज़ार', 'कचरा पिशवी व अवजारे', 'trash-2', 4, 1),
(30, 3, 'Bathing Soaps & Handwash', 'नहाने का साबुन', 'अंघोळीचा साबण', 'sparkles', 1, 1),
(31, 3, 'Oral Care', 'टूथपेस्ट और ब्रश', 'दातांची काळजी', 'smile', 2, 1),
(32, 3, 'Hair & Body Care', 'बाल और त्वचा देखभाल', 'केस व त्वचा काळजी', 'heart', 3, 1),
(40, 4, 'Tea & Coffee', 'चाय और कॉफी', 'चहा व कॉफी', 'coffee', 1, 1),
(41, 4, 'Dairy Essentials', 'दूध, दही और पनीर', 'दुग्ध उत्पादने', 'milk', 2, 1),
(42, 4, 'Snacks & Dry Fruits', 'स्नैक्स और सूखे मेवे', 'स्नॅक्स व सुकामेवा', 'cookie', 3, 1);

INSERT INTO `brands` (`id`, `name`, `logo`) VALUES
(1, 'Basmati', NULL),
(2, 'Kolam', NULL),
(3, 'Sona Masoori', NULL),
(4, 'Brown Rice', NULL),
(5, 'Santoor', NULL),
(6, 'Dettol', NULL),
(7, 'Dove', NULL),
(8, 'Pears', NULL),
(9, 'Aashirvaad', NULL),
(10, 'Fortune', NULL),
(11, 'Pillsbury', NULL),
(12, 'Tata Sampann', NULL),
(13, 'Organic Tattva', NULL),
(14, 'Tata Salt', NULL),
(15, 'Madhur', NULL),
(16, 'Dhampure', NULL),
(17, 'Amul', NULL),
(18, 'Mother Dairy', NULL),
(19, 'Gowardhan', NULL),
(20, 'Brooke Bond Red Label', NULL),
(21, 'Tata Tea Gold', NULL),
(22, 'Wagh Bakri', NULL),
(23, 'Everest', NULL),
(24, 'MDH', NULL),
(25, 'Catch', NULL),
(26, 'Surf Excel', NULL),
(27, 'Ariel', NULL),
(28, 'Tide', NULL),
(29, 'Vim', NULL),
(30, 'Pril', NULL),
(31, 'Lizol', NULL),
(32, 'Harpic', NULL),
(33, 'Colgate', NULL),
(34, 'Sensodyne', NULL),
(35, 'Parle', NULL),
(36, 'CleanZone', NULL);

INSERT INTO `products` (`id`, `category_id`, `name_en`, `name_hi`, `name_mr`, `slug`, `sku`, `description`, `image`, `unit`, `fulfillment`, `vendor_id`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 10, 'Rice', 'चावल', 'तांदूळ', 'rice', 'BCH-RICE-GEN', 'Premium Indian household rice varieties. Select your favourite variant (Basmati, Kolam, Sona Masoori, Brown Rice), choose your required pack size, and get wholesale cooperative society savings.', 'uploads/products/rice.jpg', 'kg', 'central', NULL, 1, '2026-10-23 23:00:37', '2026-10-23 23:00:37'),
(2, 30, 'Bath Soap', 'नहाने का साबुन', 'अंघोळीचा साबण', 'bath-soap', 'BCH-SOAP-GEN', 'Top Indian bathing soap brands offering refreshing fragrance, skin nourishment, and antibacterial germ protection. Choose brand, pack size, and quantity.', 'uploads/products/soap.jpg', 'pc', 'central', NULL, 1, '2026-10-23 23:00:37', '2026-10-23 23:00:37'),
(3, 11, 'Whole Wheat Atta', 'गेहूं का आटा', 'गहू पिठी', 'whole-wheat-atta', 'BCH-ATTA-GEN', '100% pure traditional chakki fresh whole wheat flour packed with natural bran and nutrients. Delivers soft, fluffy rotis every time.', 'uploads/products/atta.jpg', 'kg', 'central', NULL, 1, '2026-10-23 23:00:37', '2026-10-23 23:00:37'),
(4, 12, 'Toor Dal (Arhar Dal)', 'तूर दाल', 'तूर डाळ', 'toor-dal', 'BCH-TDAL-GEN', 'Unpolished, clean farm-picked toor dal rich in natural dietary protein and essential amino acids. Cooks fast with rich aroma.', 'uploads/products/toordal.jpg', 'kg', 'central', NULL, 1, '2026-10-23 23:00:37', '2026-10-23 23:00:37'),
(5, 13, 'Cooking Oil', 'कुकिंग ऑयल', 'खाद्य तेल', 'cooking-oil', 'BCH-OIL-GEN', 'Healthy refined and kachi ghani edible oils essential for daily cooking. Fortified with vitamins A and D.', 'uploads/products/oil.jpg', 'l', 'central', NULL, 1, '2026-10-23 23:00:37', '2026-10-23 23:00:37'),
(6, 13, 'Pure Desi Cow Ghee', 'शुद्ध देसी गाय का घी', 'शुद्ध देशी गायीचे तूप', 'desi-cow-ghee', 'BCH-GHEE-GEN', 'Traditional golden cow ghee with rich granular danedar texture and authentic aroma. Made from churned cream.', 'uploads/products/ghee.jpg', 'l', 'central', NULL, 1, '2026-10-23 23:00:37', '2026-10-23 23:00:37'),
(7, 14, 'Sugar & Jaggery', 'चीनी और गुड़', 'साखर व गूळ', 'sugar-jaggery', 'BCH-SUG-GEN', 'Sparkling sulphur-free refined crystal sugar and organic natural jaggery powder for your sweets and beverages.', 'uploads/products/sugar.jpg', 'kg', 'central', NULL, 1, '2026-10-23 23:00:37', '2026-10-23 23:00:37'),
(8, 14, 'Iodized Salt', 'आयोडीन युक्त नमक', 'आयोडाइज्ड मीठ', 'iodized-salt', 'BCH-SALT-GEN', 'Vacuum evaporated pure iodized salt for family iodine nutrition and everyday cooking.', 'uploads/products/salt.jpg', 'kg', 'central', NULL, 1, '2026-10-23 23:00:37', '2026-10-23 23:00:37'),
(9, 40, 'Tea (Chai)', 'चाय पत्ती', 'चहा पावडर', 'tea-chai', 'BCH-TEA-GEN', 'Selected garden-fresh CTC and orthodox leaf tea blends delivering golden brew, rich color, and refreshing flavor.', 'uploads/products/tea.jpg', 'g', 'central', NULL, 1, '2026-10-23 23:00:37', '2026-10-23 23:00:37'),
(10, 15, 'Pure Spices & Masala', 'शुद्ध मसाले', 'मसाले', 'spices-masala', 'BCH-SPICE-GEN', 'Aromatic, pure unadulterated ground Indian spices — turmeric (haldi), chilli (mirch), coriander (dhania).', 'uploads/products/spices.jpg', 'g', 'central', NULL, 1, '2026-10-23 23:00:37', '2026-10-23 23:00:37'),
(11, 20, 'Detergent Powder', 'डिटर्जेंट पाउडर', 'डिटर्जंट पावडर', 'detergent-powder', 'BCH-DETG-GEN', 'Deep cleaning active enzyme detergent powder for effortless tough stain removal in bucket and washing machines.', 'uploads/products/detergent.jpg', 'kg', 'central', NULL, 1, '2026-10-23 23:00:37', '2026-10-23 23:00:37'),
(12, 21, 'Dishwash Bar & Gel', 'डिशवॉश बार और जेल', 'भांडी साबण व जेल', 'dishwash-bar-gel', 'BCH-DISH-GEN', 'Grease-cutting concentrated dishwash with lemon extracts for sparkling clean utensils and gentle hands.', 'uploads/products/dishwash.jpg', 'pc', 'central', NULL, 1, '2026-10-23 23:00:37', '2026-10-23 23:00:37'),
(13, 22, 'Floor & Surface Cleaner', 'फ्लोर और सतह क्लीनर', 'फ्लोर क्लीनर', 'floor-cleaner', 'BCH-FLR-GEN', 'Hospital-grade disinfectant surface cleaner that kills 99.9% germs, removes stubborn grime, and leaves citrus freshness.', 'uploads/products/cleaner.jpg', 'ml', 'central', NULL, 1, '2026-10-23 23:00:37', '2026-10-23 23:00:37'),
(14, 31, 'Toothpaste', 'टूथपेस्ट', 'टूथपेस्ट', 'toothpaste', 'BCH-TP-GEN', 'Fluoride and herbal cavity protection toothpaste for strong enamel, fresh breath, and healthy gums.', 'uploads/products/toothpaste.svg', 'g', 'central', NULL, 1, '2026-10-23 23:00:37', '2026-10-23 23:00:37'),
(15, 23, 'Garbage Bags (30 pcs Roll)', 'कचरा बैग (30 पीस)', 'कचरा पिशवी (30 पीस)', 'garbage-bags-roll', 'BCH-GB-GEN', 'Heavy-duty leak-proof oxo-biodegradable garbage disposal bags with tie-string for standard household bins.', 'uploads/products/garbage_bags.svg', 'bag', 'central', NULL, 1, '2026-10-23 23:00:37', '2026-10-23 23:00:37');

INSERT INTO `product_variants` (`id`, `product_id`, `brand_id`, `variant_name`, `pack_size`, `unit`, `sku`, `reference_price`, `selling_price`, `stock_qty`, `min_qty`, `max_qty`, `is_active`) VALUES
(1, 1, 1, 'Basmati', 0.500, 'kg', 'RICE-BAS-500G', 60.00, 50.00, 200.000, 1.000, 20.000, 1),
(2, 1, 1, 'Basmati', 1.000, 'kg', 'RICE-BAS-1KG', 120.00, 100.00, 300.000, 1.000, 25.000, 1),
(3, 1, 1, 'Basmati', 2.000, 'kg', 'RICE-BAS-2KG', 240.00, 200.00, 150.000, 1.000, 15.000, 1),
(4, 1, 1, 'Basmati', 5.000, 'kg', 'RICE-BAS-5KG', 590.00, 500.00, 80.000, 1.000, 10.000, 1),
(5, 1, 2, 'Kolam', 0.500, 'kg', 'RICE-KOL-500G', 70.00, 60.00, 180.000, 1.000, 20.000, 1),
(6, 1, 2, 'Kolam', 1.000, 'kg', 'RICE-KOL-1KG', 140.00, 120.00, 250.000, 1.000, 25.000, 1),
(7, 1, 2, 'Kolam', 2.000, 'kg', 'RICE-KOL-2KG', 275.00, 240.00, 120.000, 1.000, 15.000, 1),
(8, 1, 2, 'Kolam', 5.000, 'kg', 'RICE-KOL-5KG', 680.00, 600.00, 60.000, 1.000, 10.000, 1),
(9, 1, 3, 'Sona Masoori', 1.000, 'kg', 'RICE-SON-1KG', 85.00, 70.00, 300.000, 1.000, 30.000, 1),
(10, 1, 3, 'Sona Masoori', 2.000, 'kg', 'RICE-SON-2KG', 165.00, 140.00, 150.000, 1.000, 20.000, 1),
(11, 1, 3, 'Sona Masoori', 5.000, 'kg', 'RICE-SON-5KG', 410.00, 350.00, 100.000, 1.000, 15.000, 1),
(12, 1, 3, 'Sona Masoori', 10.000, 'kg', 'RICE-SON-10KG', 800.00, 690.00, 50.000, 1.000, 8.000, 1),
(13, 1, 4, 'Brown Rice', 0.500, 'kg', 'RICE-BRN-500G', 90.00, 75.00, 100.000, 1.000, 15.000, 1),
(14, 1, 4, 'Brown Rice', 1.000, 'kg', 'RICE-BRN-1KG', 165.00, 140.00, 120.000, 1.000, 20.000, 1),
(15, 1, 4, 'Brown Rice', 2.000, 'kg', 'RICE-BRN-2KG', 320.00, 275.00, 60.000, 1.000, 10.000, 1),
(16, 1, 4, 'Brown Rice', 5.000, 'kg', 'RICE-BRN-5KG', 790.00, 680.00, 30.000, 1.000, 5.000, 1),
(17, 2, 5, 'Santoor', 100.000, 'g', 'SOAP-SNT-100G', 40.00, 35.00, 300.000, 1.000, 24.000, 1),
(18, 2, 5, 'Santoor', 125.000, 'g', 'SOAP-SNT-125G', 48.00, 42.00, 200.000, 1.000, 24.000, 1),
(19, 2, 5, 'Santoor', 4.000, 'pack', 'SOAP-SNT-4PK', 160.00, 132.00, 100.000, 1.000, 12.000, 1),
(20, 2, 6, 'Dettol', 75.000, 'g', 'SOAP-DTL-75G', 42.00, 36.00, 250.000, 1.000, 24.000, 1),
(21, 2, 6, 'Dettol', 125.000, 'g', 'SOAP-DTL-125G', 65.00, 56.00, 200.000, 1.000, 24.000, 1),
(22, 2, 6, 'Dettol', 3.000, 'pack', 'SOAP-DTL-3PK', 195.00, 160.00, 120.000, 1.000, 12.000, 1),
(23, 2, 7, 'Dove', 100.000, 'g', 'SOAP-DOV-100G', 75.00, 62.00, 200.000, 1.000, 24.000, 1),
(24, 2, 7, 'Dove', 125.000, 'g', 'SOAP-DOV-125G', 90.00, 76.00, 150.000, 1.000, 24.000, 1),
(25, 2, 7, 'Dove', 3.000, 'pack', 'SOAP-DOV-3PK', 225.00, 178.00, 80.000, 1.000, 12.000, 1),
(26, 2, 8, 'Pears', 125.000, 'g', 'SOAP-PRS-125G', 88.00, 75.00, 150.000, 1.000, 24.000, 1),
(27, 2, 8, 'Pears', 3.000, 'pack', 'SOAP-PRS-3PK', 264.00, 215.00, 70.000, 1.000, 12.000, 1),
(28, 3, 9, 'Aashirvaad Shudh Chakki', 1.000, 'kg', 'ATTA-ASH-1KG', 58.00, 48.00, 250.000, 1.000, 20.000, 1),
(29, 3, 9, 'Aashirvaad Shudh Chakki', 5.000, 'kg', 'ATTA-ASH-5KG', 275.00, 235.00, 200.000, 1.000, 10.000, 1),
(30, 3, 9, 'Aashirvaad Shudh Chakki', 10.000, 'kg', 'ATTA-ASH-10KG', 530.00, 455.00, 120.000, 1.000, 6.000, 1),
(31, 3, 10, 'Fortune Chakki Fresh', 1.000, 'kg', 'ATTA-FTN-1KG', 52.00, 44.00, 200.000, 1.000, 20.000, 1),
(32, 3, 10, 'Fortune Chakki Fresh', 5.000, 'kg', 'ATTA-FTN-5KG', 255.00, 215.00, 150.000, 1.000, 10.000, 1),
(33, 3, 10, 'Fortune Chakki Fresh', 10.000, 'kg', 'ATTA-FTN-10KG', 495.00, 420.00, 100.000, 1.000, 6.000, 1),
(34, 3, 11, 'Pillsbury Chakki Fresh', 5.000, 'kg', 'ATTA-PLB-5KG', 265.00, 225.00, 120.000, 1.000, 10.000, 1),
(35, 3, 11, 'Pillsbury Chakki Fresh', 10.000, 'kg', 'ATTA-PLB-10KG', 515.00, 440.00, 80.000, 1.000, 6.000, 1),
(36, 4, 12, 'Tata Sampann Unpolished', 0.500, 'kg', 'TDAL-TTS-500G', 105.00, 88.00, 250.000, 1.000, 20.000, 1),
(37, 4, 12, 'Tata Sampann Unpolished', 1.000, 'kg', 'TDAL-TTS-1KG', 195.00, 168.00, 300.000, 1.000, 20.000, 1),
(38, 4, 12, 'Tata Sampann Unpolished', 2.000, 'kg', 'TDAL-TTS-2KG', 385.00, 330.00, 120.000, 1.000, 10.000, 1),
(39, 4, 12, 'Tata Sampann Unpolished', 5.000, 'kg', 'TDAL-TTS-5KG', 950.00, 810.00, 60.000, 1.000, 6.000, 1),
(40, 4, 13, 'Organic Tattva', 0.500, 'kg', 'TDAL-OT-500G', 130.00, 110.00, 100.000, 1.000, 15.000, 1),
(41, 4, 13, 'Organic Tattva', 1.000, 'kg', 'TDAL-OT-1KG', 245.00, 210.00, 120.000, 1.000, 15.000, 1),
(42, 5, 10, 'Fortune Sunlite Sunflower', 1.000, 'l', 'OIL-SUN-1L', 155.00, 135.00, 250.000, 1.000, 24.000, 1),
(43, 5, 10, 'Fortune Sunlite Sunflower', 5.000, 'l', 'OIL-SUN-5L', 760.00, 650.00, 80.000, 1.000, 6.000, 1),
(44, 5, 10, 'Fortune Kachi Ghani Mustard', 1.000, 'l', 'OIL-MST-1L', 170.00, 145.00, 200.000, 1.000, 24.000, 1),
(45, 5, 10, 'Fortune Kachi Ghani Mustard', 5.000, 'l', 'OIL-MST-5L', 840.00, 710.00, 60.000, 1.000, 6.000, 1),
(46, 6, 17, 'Amul Cow Ghee', 500.000, 'ml', 'GHEE-AML-500M', 340.00, 310.00, 150.000, 1.000, 12.000, 1),
(47, 6, 17, 'Amul Cow Ghee', 1.000, 'l', 'GHEE-AML-1L', 675.00, 615.00, 120.000, 1.000, 10.000, 1),
(48, 6, 17, 'Amul Cow Ghee', 5.000, 'l', 'GHEE-AML-5L', 3350.00, 3000.00, 30.000, 1.000, 4.000, 1),
(49, 6, 19, 'Gowardhan Pure Ghee', 500.000, 'ml', 'GHEE-GOW-500M', 350.00, 315.00, 100.000, 1.000, 12.000, 1),
(50, 6, 19, 'Gowardhan Pure Ghee', 1.000, 'l', 'GHEE-GOW-1L', 650.00, 590.00, 80.000, 1.000, 10.000, 1),
(51, 7, 15, 'Madhur Pure Sugar', 1.000, 'kg', 'SUG-MDH-1KG', 52.00, 44.00, 300.000, 1.000, 25.000, 1),
(52, 7, 15, 'Madhur Pure Sugar', 5.000, 'kg', 'SUG-MDH-5KG', 255.00, 215.00, 100.000, 1.000, 10.000, 1),
(53, 7, 16, 'Dhampure Organic Jaggery', 0.500, 'kg', 'JAG-DHM-500G', 65.00, 55.00, 150.000, 1.000, 15.000, 1),
(54, 7, 16, 'Dhampure Organic Jaggery', 1.000, 'kg', 'JAG-DHM-1KG', 125.00, 105.00, 120.000, 1.000, 15.000, 1),
(55, 8, 14, 'Tata Salt (Desh Ka Namak)', 1.000, 'kg', 'SALT-TAT-1KG', 28.00, 25.00, 400.000, 1.000, 30.000, 1),
(56, 9, 20, 'Brooke Bond Red Label', 250.000, 'g', 'TEA-RL-250G', 145.00, 125.00, 200.000, 1.000, 20.000, 1),
(57, 9, 20, 'Brooke Bond Red Label', 500.000, 'g', 'TEA-RL-500G', 280.00, 240.00, 180.000, 1.000, 15.000, 1),
(58, 9, 20, 'Brooke Bond Red Label', 1.000, 'kg', 'TEA-RL-1KG', 540.00, 465.00, 80.000, 1.000, 10.000, 1),
(59, 9, 21, 'Tata Tea Gold', 250.000, 'g', 'TEA-TTG-250G', 165.00, 140.00, 150.000, 1.000, 20.000, 1),
(60, 9, 21, 'Tata Tea Gold', 500.000, 'g', 'TEA-TTG-500G', 315.00, 270.00, 120.000, 1.000, 15.000, 1),
(61, 9, 21, 'Tata Tea Gold', 1.000, 'kg', 'TEA-TTG-1KG', 610.00, 520.00, 60.000, 1.000, 10.000, 1),
(62, 10, 23, 'Everest Turmeric Powder', 100.000, 'g', 'SPC-EV-HLD-100', 38.00, 32.00, 250.000, 1.000, 20.000, 1),
(63, 10, 23, 'Everest Turmeric Powder', 200.000, 'g', 'SPC-EV-HLD-200', 72.00, 60.00, 180.000, 1.000, 15.000, 1),
(64, 10, 23, 'Everest Turmeric Powder', 500.000, 'g', 'SPC-EV-HLD-500', 175.00, 145.00, 90.000, 1.000, 10.000, 1),
(65, 10, 24, 'MDH Deggi Mirch Powder', 100.000, 'g', 'SPC-MDH-MRC-100', 98.00, 85.00, 200.000, 1.000, 20.000, 1),
(66, 10, 24, 'MDH Deggi Mirch Powder', 500.000, 'g', 'SPC-MDH-MRC-500', 450.00, 390.00, 80.000, 1.000, 10.000, 1),
(67, 11, 26, 'Surf Excel Easy Wash', 1.000, 'kg', 'DET-SX-1KG', 155.00, 135.00, 200.000, 1.000, 15.000, 1),
(68, 11, 26, 'Surf Excel Easy Wash', 3.000, 'kg', 'DET-SX-3KG', 450.00, 385.00, 100.000, 1.000, 10.000, 1),
(69, 11, 26, 'Surf Excel Easy Wash', 5.000, 'kg', 'DET-SX-5KG', 740.00, 620.00, 60.000, 1.000, 6.000, 1),
(70, 11, 27, 'Ariel Complete', 1.000, 'kg', 'DET-AR-1KG', 180.00, 155.00, 150.000, 1.000, 15.000, 1),
(71, 11, 27, 'Ariel Complete', 4.000, 'kg', 'DET-AR-4KG', 690.00, 580.00, 50.000, 1.000, 6.000, 1),
(72, 12, 29, 'Vim Lemon Dishwash Bar', 200.000, 'g', 'DSH-VIM-200G', 30.00, 26.00, 300.000, 1.000, 24.000, 1),
(73, 12, 29, 'Vim Lemon Dishwash Bar', 3.000, 'pack', 'DSH-VIM-3PK', 90.00, 75.00, 150.000, 1.000, 12.000, 1),
(74, 12, 29, 'Vim Lemon Liquid Gel', 500.000, 'ml', 'DSH-VIM-500M', 125.00, 105.00, 180.000, 1.000, 15.000, 1),
(75, 12, 29, 'Vim Lemon Liquid Gel', 2.000, 'l', 'DSH-VIM-2L', 430.00, 360.00, 50.000, 1.000, 6.000, 1),
(76, 13, 31, 'Lizol Citrus Floor Cleaner', 500.000, 'ml', 'FLR-LZL-500M', 125.00, 105.00, 180.000, 1.000, 15.000, 1),
(77, 13, 31, 'Lizol Citrus Floor Cleaner', 1.000, 'l', 'FLR-LZL-1L', 235.00, 195.00, 120.000, 1.000, 10.000, 1),
(78, 13, 31, 'Lizol Citrus Floor Cleaner', 2.000, 'l', 'FLR-LZL-2L', 440.00, 365.00, 50.000, 1.000, 6.000, 1),
(79, 14, 33, 'Colgate Strong Teeth', 150.000, 'g', 'TP-CG-150G', 85.00, 72.00, 200.000, 1.000, 20.000, 1),
(80, 14, 33, 'Colgate Strong Teeth', 300.000, 'g', 'TP-CG-300G', 165.00, 138.00, 120.000, 1.000, 15.000, 1),
(81, 14, 34, 'Sensodyne Fresh Gel', 75.000, 'g', 'TP-SNS-75G', 155.00, 135.00, 120.000, 1.000, 15.000, 1),
(82, 14, 34, 'Sensodyne Fresh Gel', 150.000, 'g', 'TP-SNS-150G', 290.00, 255.00, 80.000, 1.000, 10.000, 1),
(83, 15, 36, 'CleanZone Oxo-Degradable Roll', 1.000, 'bag', 'GB-CZ-M30', 75.00, 60.00, 250.000, 1.000, 15.000, 1);

INSERT INTO `society_prices` (`id`, `variant_id`, `society_id`, `selling_price`) VALUES
(1, 2, 1, 95.00),
(2, 4, 1, 480.00),
(3, 6, 1, 115.00),
(4, 8, 1, 580.00),
(5, 9, 1, 68.00),
(6, 23, 1, 59.00),
(7, 29, 1, 229.00),
(8, 42, 1, 130.00),
(9, 55, 1, 24.00),
(10, 2, 2, 98.00),
(11, 6, 2, 118.00),
(12, 29, 2, 232.00),
(13, 42, 2, 132.00);

INSERT INTO services (id, category, name_en, name_hi, name_mr, description, base_price, duration_mins, vendor_id, area, cancellation_policy) VALUES
(1, 'AC & Appliances', 'AC Service & Gas Refill', 'AC सर्विस और गैस रिफिल', 'AC सर्व्हिस व गॅस रिफिल', 'Deep-clean of filters, coil check and gas top-up by certified technician.', 599.00, 90, 7, 'Pune', 'Free cancellation up to 6 hours before the slot.'),
(2, 'Plumbing', 'Plumber Visit', 'प्लंबर विज़िट', 'प्लंबर भेट', 'Leak fixing, tap/mixer replacement, blockage clearing. Spare parts at actuals.', 249.00, 60, 7, 'Pune', 'Free cancellation up to 3 hours before the slot.'),
(3, 'Electrical', 'Electrician Visit', 'इलेक्ट्रिशियन विज़िट', 'इलेक्ट्रिशियन भेट', 'Switch, socket, fan, light repairs and installation checks.', 249.00, 60, 7, 'Pune', 'Free cancellation up to 3 hours before the slot.'),
(4, 'Cleaning', 'Deep Home Cleaning (2BHK)', 'डीप होम क्लीनिंग (2BHK)', 'डीप होम क्लिनिंग (2BHK)', 'Full flat deep cleaning — kitchen, bathrooms, floors, windows. Team of 3.', 1999.00, 240, 7, 'Pune', 'Free cancellation up to 24 hours before the slot.'),
(5, 'Pest Control', 'Pest Control (Full Flat)', 'पेस्ट कंट्रोल', 'कीटक नियंत्रण', 'Odourless, child-safe pest control for cockroaches and ants. 90-day warranty.', 1499.00, 120, 7, 'Pune', 'Free cancellation up to 12 hours before the slot.'),
(6, 'Cleaning', 'Sofa & Carpet Shampooing', 'सोफा और कालीन शैम्पू', 'सोफा व कार्पेट शॅम्पू', 'Machine shampooing of sofa set and carpets.', 899.00, 120, 7, 'Pune', 'Free cancellation up to 12 hours before the slot.'),
(7, 'Vehicle Care', 'Car Wash (Doorstep)', 'कार वॉश (डोरस्टेप)', 'कार वॉश (दारासमोर)', 'Exterior foam wash + interior vacuum at your parking spot.', 349.00, 45, 7, 'Pune', 'Free cancellation up to 2 hours before the slot.'),
(8, 'Daily Essentials', 'Water Can Delivery (20L)', 'पानी की कैन डिलीवरी (20L)', 'पाणी कॅन वितरण (20L)', 'Sealed 20L water cans delivered to your flat. Price per can.', 40.00, 15, 7, 'Pune', 'Cancel anytime before the morning delivery round.');

INSERT INTO coupons (id, code, title, type, value, min_cart_value, max_discount, per_user_limit, valid_from, valid_to, society_id, funding_source, is_active) VALUES
(1, 'FIRST50', 'Flat ₹50 off on your first Bachat order', 'fixed', 50.00, 500.00, NULL, 1, NOW(), DATE_ADD(NOW(), INTERVAL 60 DAY), NULL, 'platform', 1),
(2, 'BULK10', '10% off on bulk carts above ₹1,500', 'percent', 10.00, 1500.00, 300.00, 5, NOW(), DATE_ADD(NOW(), INTERVAL 90 DAY), NULL, 'platform', 1),
(3, 'GM100', '₹100 off — Green Meadow exclusive', 'fixed', 100.00, 800.00, NULL, 3, NOW(), DATE_ADD(NOW(), INTERVAL 45 DAY), 1, 'society', 1);

INSERT INTO announcements (id, society_id, title, body, audience, is_pinned, publish_at, created_by) VALUES
(1, 1, 'Monthly bulk grocery campaign starts Monday', 'Dear residents, our monthly bulk grocery campaign opens Monday. Rice, atta, oil and dal at wholesale prices. Final prices will be confirmed after vendor quotation comparison — nothing is charged until you explicitly confirm your order.', 'residents', 1, NOW(), 2),
(2, 1, 'Deep cleaning group deal — 20% off', 'We are collecting interest for a society-wide deep cleaning weekend. If at least 10 flats join, everyone gets a 20% group discount. Register your interest from the Services page.', 'residents', 0, NOW(), 2);

INSERT INTO demand_votes (id, society_id, user_id, item_type, title, details, category_id, status) VALUES
(1, 1, 4, 'product', 'Organic jaggery blocks', 'Looking for chemical-free organic jaggery in 1kg blocks for the whole society.', 6, 'open'),
(2, 1, 4, 'service', 'Weekend yoga classes in clubhouse', 'A certified instructor twice a week; group pricing if 15+ residents join.', NULL, 'open');

INSERT INTO subscriptions (id, society_id, plan, amount, status, starts_at, ends_at, paid_at) VALUES
(1, 1, 'monthly', 499.00, 'active', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 MONTH), NOW()),
(2, 2, 'yearly', 4999.00, 'pending_payment', NULL, NULL, NULL);

-- Opening stock for a few central variants (sample)
INSERT INTO inventory (variant_id, received, reserved, dispatched, returned, damaged) VALUES
(1, 500, 0, 0, 0, 0), (7, 300, 0, 0, 0, 2), (11, 300, 0, 0, 0, 0),
(13, 600, 0, 0, 0, 0), (17, 400, 0, 0, 0, 0), (29, 300, 0, 0, 0, 0);

INSERT INTO notifications (user_id, type, title, body, link) VALUES
(4, 'welcome', 'Welcome to Bachat!', 'Your Green Meadow membership is active. Start with this month''s bulk campaign.', '/campaigns'),
(5, 'membership', 'Membership pending approval', 'Your Sunrise Heights membership request is with the society admin.', '/join/pending');
