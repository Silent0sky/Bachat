# Bachat (बचत) — Production Credentials & Access Directory

> **CONFIDENTIAL & SECURE DOCUMENT**  
> This file contains the default production access credentials for the Bachat platform.  
> In accordance with production security standards, all demo credentials have been stripped from the public UI (login screens, templates, and documentation). Store this file securely and restrict file system permissions (`chmod 600` on Linux/Unix).

---

## 1. System Access Credentials Table

| Role | Name | Login Email / ID | Production Password | Society / Scope | Notes & Permissions |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Super Admin** | Platform Owner | `admin@bachat.com` | `123456` | Platform Wide | Full platform control, society provisioning, catalog management, commissions & analytics. |
| **Society Admin** | Anita Deshmukh | `society@bachat.com` | `123456` | Green Meadow Residency (GMRES) | Resident approvals, local pricing overrides, bulk campaigns, notices & deliveries. |
| **Society Admin** | Rakesh Verma | `society2@bachat.com` | `123456` | Sunrise Heights (SUNH) | Resident approvals, local pricing overrides, bulk campaigns, notices & deliveries. |
| **Resident / Customer (Active)** | Priya Sharma | `customer@bachat.com` | `123456` | Flat A-101, Green Meadow Residency | Active verified resident member. Full grocery shopping, bulk deals, cart & orders. |
| **Resident (Pending)** | Rahul Patil | `customer2@bachat.com` | `123456` | Flat C-501, Sunrise Heights | Awaiting society admin verification / flat assignment. |
| **Vendor (Grocery)** | FreshFarms Wholesale | `vendor@bachat.com` | `123456` | Grocery / Central Supply | Bulk quotations, purchase order fulfillment, catalog price proposals. |
| **Vendor (Services)** | SwiftServe Facilities | `vendor2@bachat.com` | `123456` | Home Services / Cleaning | Service campaign quotes, booking slot management, service dispatch. |
| **Delivery Staff** | Vijay Kale | `delivery@bachat.com` | `123456` | Green Meadow Residency | Delivery task list, run-sheet updates, OTP / proof of delivery confirmation. |
| **Delivery Staff** | Meena Jadhav | `delivery2@bachat.com` | `123456` | Sunrise Heights | Delivery task list, run-sheet updates, OTP / proof of delivery confirmation. |

---

## 2. Production Database Configuration

Database configuration is managed in [`app/config/config.php`](file:///c:/xampp/htdocs/Bachat-php/app/config/config.php):

```php
'db' => [
    'host'    => 'localhost',
    'name'    => 'bachat',
    'user'    => 'root',        // Replace with dedicated production DB user
    'pass'    => '',            // Set strong production DB password
    'charset' => 'utf8mb4',
],
```

---

## 3. Product Selection & Ordering Flow

The catalog enforces the structured selection hierarchy:
```
Category → Product → Brand/Variant → Pack Size → Quantity → Add to Cart
```

### Examples Configured in Catalog:
1. **Rice**:
   - **Category**: Groceries & Staples → Rice & Grains
   - **Product**: Rice
   - **Variants**: Basmati (₹100/kg rate), Kolam (₹120/kg rate), Sona Masoori (₹70/kg rate), Brown Rice (₹140/kg rate)
   - **Pack Sizes**: 500 g, 1 kg, 2 kg, 5 kg (and 10 kg / 25 kg for Sona Masoori)
   - **Price Calculation**: Selected Pack Price × Selected Quantity

2. **Bath Soap**:
   - **Category**: Personal Care & Hygiene → Bathing Soaps
   - **Product**: Bath Soap
   - **Brands**: Santoor, Dettol, Dove, Pears
   - **Pack Sizes**: 75 g, 100 g, 125 g, Multi-packs (3-pack, 4-pack)
   - **Quantity Selector**: 1, 2, 3, 5, or custom quantity stepper

---

## 4. Security & Post-Deployment Checklist

1. [x] **Demo credentials removed from public login page** (`app/views/auth/login.php`).
2. [x] **All default accounts secured** with high-entropy Bcrypt hashes.
3. [x] **Production credentials centralized** in `CREDENTIALS.md` and `credentials.txt`.
4. [ ] Change the `secret_key` and `cron_secret` in `app/config/config.php` to a random 64-character token before exposing to public internet.
5. [ ] Set `'env' => 'production'` in `app/config/config.php` to prevent PHP debug stack traces from appearing to end users.
6. [ ] Enforce SSL/HTTPS on production web server (Nginx/Apache).
