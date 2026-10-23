<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Database;
use PDO;
use Throwable;

/**
 * Bulk Grocery Upload Service.
 * Allows Super Admin and Vendors to upload grocery catalogs in bulk via CSV / TSV / Excel-exported files.
 * Handles auto-creation of missing categories and brands, smart unit normalization,
 * product deduplication, variant pricing, and society overrides.
 */
final class BulkGroceryService
{
    /** Supported unit enum in product_variants table */
    public const VALID_UNITS = ['g', 'kg', 'ml', 'l', 'pc', 'pack', 'bottle', 'bag', 'box'];

    /**
     * Download a clean CSV sample template with UTF-8 BOM for Microsoft Excel compatibility.
     */
    public static function downloadSampleTemplate(): void
    {
        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="bachat_bulk_grocery_template.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'wb');
        // UTF-8 BOM so Excel displays Devanagari (Hindi/Marathi) correctly
        fwrite($out, "\xEF\xBB\xBF");

        // Headers
        fputcsv($out, [
            'Category',
            'Product Name (EN)',
            'Product Name (HI)',
            'Product Name (MR)',
            'Brand',
            'Pack Size',
            'Unit',
            'Reference Price (MRP)',
            'Selling Price (Bachat)',
            'Stock Qty',
            'SKU',
            'Description',
            'Fulfillment',
            'Society Code'
        ]);

        // Rich sample grocery rows
        $samples = [
            [
                'Atta & Flours',
                'Aashirvaad Shudh Chakki Atta',
                'आशीर्वाद शुद्ध चक्की आटा',
                'आशीर्वाद शुद्ध चक्की पीठ',
                'Aashirvaad',
                '5',
                'kg',
                '260.00',
                '215.00',
                '100',
                'BCH-ATTA-05K',
                '100% whole wheat chakki atta with 0% maida',
                'central',
                ''
            ],
            [
                'Atta & Flours',
                'Aashirvaad Shudh Chakki Atta',
                'आशीर्वाद शुद्ध चक्की आटा',
                'आशीर्वाद शुद्ध चक्की पीठ',
                'Aashirvaad',
                '10',
                'kg',
                '495.00',
                '420.00',
                '50',
                'BCH-ATTA-10K',
                '100% whole wheat chakki atta with 0% maida',
                'central',
                ''
            ],
            [
                'Cooking Oil',
                'Fortune Sunlite Refined Sunflower Oil',
                'फॉर्च्यून सनलाइट रिफाइंड तेल',
                'फॉर्च्यून सनलाइट तेल',
                'Fortune',
                '1',
                'l',
                '165.00',
                '135.00',
                '120',
                'BCH-OIL-01L',
                'Light and healthy refined sunflower cooking oil',
                'central',
                ''
            ],
            [
                'Rice',
                'India Gate Feast Rozzana Basmati Rice',
                'इंडिया गेट बासमती चावल',
                'इंडिया गेट बासमती तांदूळ',
                'India Gate',
                '5',
                'kg',
                '425.00',
                '340.00',
                '80',
                'BCH-RICE-05K',
                'Aromatic long grain basmati rice for daily meals',
                'central',
                ''
            ],
            [
                'Dal & Pulses',
                'Tata Sampann Unpolished Toor Dal',
                'टाटा सम्पन्न तूर दाल',
                'टाटा सम्पन्न तूर डाळ',
                'Tata Sampann',
                '1',
                'kg',
                '195.00',
                '162.00',
                '90',
                'BCH-DAL-01K',
                'Unpolished toor dal rich in natural protein',
                'central',
                ''
            ],
            [
                'Dairy & Breakfast',
                'Amul Taaza Homogenised Toned Milk',
                'अमूल ताज़ा दूध',
                'अमूल ताझा दूध',
                'Amul',
                '1',
                'l',
                '74.00',
                '68.00',
                '150',
                'BCH-MLK-01L',
                'Long life UHT toned milk pouch',
                'central',
                ''
            ],
            [
                'Sugar & Jaggery',
                'Madhur Pure & Hygienic Refined Sugar',
                'मधुर रिफाइंड चीनी',
                'मधुर शुद्ध साखर',
                'Madhur',
                '1',
                'kg',
                '60.00',
                '48.00',
                '200',
                'BCH-SGR-01K',
                'Sulphur-free sparkling white refined sugar',
                'central',
                ''
            ],
            [
                'Snacks & Dry Fruits',
                'Tata Tea Gold Leaf Black Tea',
                'टाटा टी गोल्ड चायपत्ती',
                'टाटा टी गोल्ड चहा',
                'Tata Tea',
                '500',
                'g',
                '320.00',
                '265.00',
                '60',
                'BCH-TEA-500G',
                'Gently rolled long tea leaves with 15% long leaves',
                'central',
                ''
            ],
            [
                'Household Cleaning',
                'Dettol Antiseptic Liquid',
                'डेटॉल एंटीसेप्टिक लिक्विड',
                'डेटॉल अँटीसेप्टिक लिक्विड',
                'Dettol',
                '500',
                'ml',
                '215.00',
                '185.00',
                '40',
                'BCH-DET-500M',
                'First aid and personal hygiene antiseptic disinfectant',
                'central',
                ''
            ]
        ];

        foreach ($samples as $s) {
            fputcsv($out, $s);
        }

        fclose($out);
        exit;
    }

    /**
     * Parse and import grocery items from CSV file.
     *
     * @param string   $filePath         Path to uploaded CSV file
     * @param int|null $defaultSocietyId Optional society ID to apply price override
     * @param bool     $updateExisting   Whether to update prices and info if product/variant already exists
     * @param int|null $vendorId         Optional vendor ID if uploaded by a vendor
     * @return array
     */
    public static function processUpload(
        string $filePath,
        ?int $defaultSocietyId = null,
        bool $updateExisting = true,
        ?int $vendorId = null
    ): array {
        $stats = [
            'total_rows'         => 0,
            'products_created'   => 0,
            'products_updated'   => 0,
            'variants_created'   => 0,
            'variants_updated'   => 0,
            'categories_created' => 0,
            'brands_created'     => 0,
            'prices_set'         => 0,
            'skipped_rows'       => 0,
            'errors'             => [],
            'warnings'           => []
        ];

        if (!file_exists($filePath) || !is_readable($filePath)) {
            $stats['errors'][] = ['row' => 0, 'product' => 'File', 'error' => 'Uploaded file is not readable.'];
            return $stats;
        }

        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            $stats['errors'][] = ['row' => 0, 'product' => 'File', 'error' => 'Unable to open uploaded file.'];
            return $stats;
        }

        // Detect separator (comma or semicolon)
        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);
            $stats['errors'][] = ['row' => 0, 'product' => 'File', 'error' => 'File is empty.'];
            return $stats;
        }

        // Remove UTF-8 BOM if present
        $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine);
        $sep = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        // Parse header
        $headers = str_getcsv($firstLine, $sep);
        $headerMap = self::mapHeaders($headers);

        if (!isset($headerMap['product_name']) && !isset($headerMap['product_name_en'])) {
            fclose($handle);
            $stats['errors'][] = [
                'row' => 1,
                'product' => 'Header',
                'error' => 'CSV must contain a "Product Name" or "Product Name (EN)" column header.'
            ];
            return $stats;
        }

        // Preload caches to avoid repeated database lookups
        $categoryCache = self::loadCategoryCache();
        $brandCache    = self::loadBrandCache();
        $societyCache  = self::loadSocietyCache();

        $rowNumber = 1;
        $pdo = Database::pdo();

        while (($data = fgetcsv($handle, 4096, $sep)) !== false) {
            $rowNumber++;

            // Skip empty rows
            if (empty(array_filter($data, static fn($v) => trim((string)$v) !== ''))) {
                continue;
            }

            $stats['total_rows']++;
            $row = self::extractRowData($data, $headerMap);

            $nameEn = trim((string) ($row['product_name_en'] ?? $row['product_name'] ?? ''));
            if ($nameEn === '') {
                $stats['skipped_rows']++;
                $stats['errors'][] = [
                    'row' => $rowNumber,
                    'product' => 'Row #' . $rowNumber,
                    'error' => 'Missing product name.'
                ];
                continue;
            }

            $packSize = (float) ($row['pack_size'] ?? 1);
            if ($packSize <= 0) {
                $packSize = 1.0;
            }

            $rawUnit = (string) ($row['unit'] ?? 'pc');
            $unit = self::normalizeUnit($rawUnit);

            $sellPrice = (float) ($row['selling_price'] ?? 0);
            if ($sellPrice < 0) {
                $stats['skipped_rows']++;
                $stats['errors'][] = [
                    'row' => $rowNumber,
                    'product' => $nameEn,
                    'error' => 'Selling price cannot be negative.'
                ];
                continue;
            }

            $refPrice = (float) ($row['reference_price'] ?? 0);
            if ($refPrice <= 0) {
                $refPrice = $sellPrice > 0 ? round($sellPrice * 1.15, 2) : 0.0;
            }

            $stockQty = isset($row['stock_qty']) && is_numeric($row['stock_qty']) ? (float) $row['stock_qty'] : 100.0;
            $sku = trim((string) ($row['sku'] ?? ''));
            $desc = trim((string) ($row['description'] ?? ''));
            $nameHi = trim((string) ($row['product_name_hi'] ?? ''));
            $nameMr = trim((string) ($row['product_name_mr'] ?? ''));
            $fulfillment = (strtolower(trim((string) ($row['fulfillment'] ?? ''))) === 'vendor_direct') ? 'vendor_direct' : 'central';

            try {
                $pdo->beginTransaction();

                // 1. Resolve Category
                $catName = trim((string) ($row['category'] ?? 'Groceries & Staples'));
                if ($catName === '') {
                    $catName = 'Groceries & Staples';
                }
                $catLower = strtolower($catName);
                if (isset($categoryCache[$catLower])) {
                    $categoryId = $categoryCache[$catLower];
                } else {
                    // Create new category
                    Database::run(
                        'INSERT INTO categories (name_en, parent_id, sort_order, is_active) VALUES (?, NULL, 99, 1)',
                        [$catName]
                    );
                    $categoryId = (int) $pdo->lastInsertId();
                    $categoryCache[$catLower] = $categoryId;
                    $stats['categories_created']++;
                }

                // 2. Resolve Brand
                $brandName = trim((string) ($row['brand'] ?? ''));
                $brandId = null;
                if ($brandName !== '' && !in_array(strtolower($brandName), ['n/a', 'none', '-', 'local'], true)) {
                    $brandLower = strtolower($brandName);
                    if (isset($brandCache[$brandLower])) {
                        $brandId = $brandCache[$brandLower];
                    } else {
                        // Create brand
                        Database::run('INSERT INTO brands (name) VALUES (?)', [$brandName]);
                        $brandId = (int) $pdo->lastInsertId();
                        $brandCache[$brandLower] = $brandId;
                        $stats['brands_created']++;
                    }
                }

                // 3. Resolve Product
                $existingProduct = null;
                if ($sku !== '') {
                    $existingProduct = Database::fetch('SELECT id, category_id, name_en, slug FROM products WHERE sku = ? LIMIT 1', [$sku]);
                }
                if (!$existingProduct) {
                    $existingProduct = Database::fetch('SELECT id, category_id, name_en, slug FROM products WHERE LOWER(name_en) = ? LIMIT 1', [strtolower($nameEn)]);
                }

                if ($existingProduct) {
                    $productId = (int) $existingProduct['id'];
                    if ($updateExisting) {
                        Database::run(
                            'UPDATE products SET
                                name_hi = COALESCE(NULLIF(?, ""), name_hi),
                                name_mr = COALESCE(NULLIF(?, ""), name_mr),
                                description = COALESCE(NULLIF(?, ""), description),
                                category_id = ?,
                                is_active = 1,
                                updated_at = NOW()
                             WHERE id = ?',
                            [$nameHi, $nameMr, $desc, $categoryId, $productId]
                        );
                        $stats['products_updated']++;
                    }
                } else {
                    $slug = self::generateUniqueSlug($nameEn);
                    Database::run(
                        'INSERT INTO products (category_id, name_en, name_hi, name_mr, slug, sku, description, unit, fulfillment, vendor_id, is_active, created_at, updated_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())',
                        [
                            $categoryId,
                            $nameEn,
                            $nameHi ?: null,
                            $nameMr ?: null,
                            $slug,
                            $sku ?: null,
                            $desc ?: null,
                            $unit,
                            $fulfillment,
                            $vendorId
                        ]
                    );
                    $productId = (int) $pdo->lastInsertId();
                    $stats['products_created']++;
                }

                // 4. Resolve Product Variant
                $existingVariant = null;
                if ($brandId !== null) {
                    $existingVariant = Database::fetch(
                        'SELECT id FROM product_variants WHERE product_id = ? AND pack_size = ? AND unit = ? AND brand_id = ? LIMIT 1',
                        [$productId, $packSize, $unit, $brandId]
                    );
                } else {
                    $existingVariant = Database::fetch(
                        'SELECT id FROM product_variants WHERE product_id = ? AND pack_size = ? AND unit = ? AND brand_id IS NULL LIMIT 1',
                        [$productId, $packSize, $unit]
                    );
                }

                $variantId = null;
                if ($existingVariant) {
                    $variantId = (int) $existingVariant['id'];
                    if ($updateExisting) {
                        Database::run(
                            'UPDATE product_variants SET
                                reference_price = ?,
                                selling_price = ?,
                                stock_qty = ?,
                                sku = COALESCE(NULLIF(?, ""), sku),
                                is_active = 1
                             WHERE id = ?',
                            [$refPrice, $sellPrice, $stockQty, $sku, $variantId]
                        );
                        $stats['variants_updated']++;
                    }
                } else {
                    Database::run(
                        'INSERT INTO product_variants (product_id, brand_id, pack_size, unit, sku, reference_price, selling_price, stock_qty, min_qty, max_qty, is_active)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1.0, NULL, 1)',
                        [
                            $productId,
                            $brandId,
                            $packSize,
                            $unit,
                            $sku ?: null,
                            $refPrice,
                            $sellPrice,
                            $stockQty
                        ]
                    );
                    $variantId = (int) $pdo->lastInsertId();
                    $stats['variants_created']++;
                }

                // 5. Society Price Override (if specified in column or default selected)
                $targetSocietyId = $defaultSocietyId;
                $rowSocietyCode = trim((string) ($row['society_code'] ?? $row['society'] ?? ''));
                if ($rowSocietyCode !== '') {
                    $sLower = strtolower($rowSocietyCode);
                    if (isset($societyCache[$sLower])) {
                        $targetSocietyId = $societyCache[$sLower];
                    }
                }

                if ($targetSocietyId && $variantId && $sellPrice > 0) {
                    Database::run(
                        'INSERT INTO society_prices (variant_id, society_id, selling_price) VALUES (?, ?, ?)
                         ON DUPLICATE KEY UPDATE selling_price = VALUES(selling_price)',
                        [$variantId, $targetSocietyId, $sellPrice]
                    );
                    $stats['prices_set']++;
                }

                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $stats['skipped_rows']++;
                $stats['errors'][] = [
                    'row' => $rowNumber,
                    'product' => $nameEn,
                    'error' => $e->getMessage()
                ];
            }
        }

        fclose($handle);

        Audit::log('catalog.bulk_grocery_upload', 'catalog', 0, [
            'total_rows'       => $stats['total_rows'],
            'products_created' => $stats['products_created'],
            'products_updated' => $stats['products_updated'],
            'variants_created' => $stats['variants_created'],
            'errors_count'     => count($stats['errors']),
        ]);

        return $stats;
    }

    /**
     * Map arbitrary header labels to normalized keys.
     */
    private static function mapHeaders(array $headers): array
    {
        $map = [];
        foreach ($headers as $index => $col) {
            $c = strtolower(trim((string) $col));
            $c = str_replace(['_', '-', '.', '(', ')'], ' ', $c);
            $c = preg_replace('/\s+/', ' ', $c);

            if (str_contains($c, 'category')) {
                $map['category'] = $index;
            } elseif (str_contains($c, 'product') && (str_contains($c, 'hi') || str_contains($c, 'hindi'))) {
                $map['product_name_hi'] = $index;
            } elseif (str_contains($c, 'product') && (str_contains($c, 'mr') || str_contains($c, 'marathi'))) {
                $map['product_name_mr'] = $index;
            } elseif (str_contains($c, 'product') && str_contains($c, 'en')) {
                $map['product_name_en'] = $index;
            } elseif (str_contains($c, 'product') || str_contains($c, 'item name') || $c === 'name') {
                $map['product_name'] = $index;
            } elseif (str_contains($c, 'brand')) {
                $map['brand'] = $index;
            } elseif (str_contains($c, 'pack') || str_contains($c, 'size') || str_contains($c, 'weight') || str_contains($c, 'qty')) {
                if (!isset($map['pack_size'])) {
                    $map['pack_size'] = $index;
                }
            } elseif (str_contains($c, 'unit')) {
                $map['unit'] = $index;
            } elseif (str_contains($c, 'mrp') || str_contains($c, 'reference') || str_contains($c, 'market price')) {
                $map['reference_price'] = $index;
            } elseif (str_contains($c, 'selling') || str_contains($c, 'bachat') || str_contains($c, 'our price') || $c === 'price') {
                $map['selling_price'] = $index;
            } elseif (str_contains($c, 'stock') || str_contains($c, 'inventory')) {
                $map['stock_qty'] = $index;
            } elseif (str_contains($c, 'sku') || str_contains($c, 'code') && !str_contains($c, 'society')) {
                $map['sku'] = $index;
            } elseif (str_contains($c, 'desc')) {
                $map['description'] = $index;
            } elseif (str_contains($c, 'fulfill')) {
                $map['fulfillment'] = $index;
            } elseif (str_contains($c, 'society')) {
                $map['society_code'] = $index;
            }
        }
        return $map;
    }

    /**
     * Extract normalized associative data from row array using header map.
     */
    private static function extractRowData(array $row, array $map): array
    {
        $out = [];
        foreach ($map as $key => $idx) {
            $out[$key] = isset($row[$idx]) ? trim((string) $row[$idx]) : '';
        }
        return $out;
    }

    /**
     * Normalize real-world unit strings to database ENUM.
     */
    public static function normalizeUnit(string $unit): string
    {
        $u = strtolower(trim($unit));
        $u = preg_replace('/[^a-z]/', '', $u);

        return match ($u) {
            'kg', 'kgs', 'kilogram', 'kilograms', 'kilo'       => 'kg',
            'g', 'gm', 'gms', 'gram', 'grams'                  => 'g',
            'l', 'lt', 'ltr', 'ltrs', 'litre', 'litres', 'liter' => 'l',
            'ml', 'mls', 'millilitre', 'milliliter'            => 'ml',
            'pack', 'packet', 'packets', 'pkt', 'pkts'         => 'pack',
            'bottle', 'bottles', 'btl'                         => 'bottle',
            'bag', 'bags'                                      => 'bag',
            'box', 'boxes'                                     => 'box',
            default                                            => 'pc',
        };
    }

    /**
     * Generate unique product slug.
     */
    private static function generateUniqueSlug(string $name): string
    {
        $base = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-');
        if ($base === '') {
            $base = 'grocery-item';
        }
        $slug = $base;
        $i = 2;
        while (true) {
            $exists = Database::fetch('SELECT id FROM products WHERE slug = ? LIMIT 1', [$slug]);
            if (!$exists) {
                return $slug;
            }
            $slug = $base . '-' . $i;
            $i++;
        }
    }

    /**
     * Preload category name-to-id mapping.
     */
    private static function loadCategoryCache(): array
    {
        $cats = Database::fetchAll('SELECT id, name_en FROM categories');
        $map = [];
        foreach ($cats as $c) {
            $map[strtolower(trim($c['name_en']))] = (int) $c['id'];
        }
        return $map;
    }

    /**
     * Preload brand name-to-id mapping.
     */
    private static function loadBrandCache(): array
    {
        $brands = Database::fetchAll('SELECT id, name FROM brands');
        $map = [];
        foreach ($brands as $b) {
            $map[strtolower(trim($b['name']))] = (int) $b['id'];
        }
        return $map;
    }

    /**
     * Preload society code/slug/name to id mapping.
     */
    private static function loadSocietyCache(): array
    {
        $societies = Database::fetchAll('SELECT id, code, slug, name FROM societies');
        $map = [];
        foreach ($societies as $s) {
            $map[strtolower(trim($s['code']))] = (int) $s['id'];
            $map[strtolower(trim($s['slug']))] = (int) $s['id'];
            $map[strtolower(trim($s['name']))] = (int) $s['id'];
        }
        return $map;
    }

    /* ============================== BULK IMAGE UPLOAD ============================== */

    /**
     * Process bulk product image uploads.
     * Images are matched to products by filename (sans extension) against:
     *   - product ID  (e.g. "42.jpg" → product with id=42)
     *   - product slug (e.g. "aashirvaad-shudh-chakki-atta-1729602345.jpg")
     *   - product name_en (case-insensitive fuzzy, e.g. "Aashirvaad Shudh Chakki Atta.png")
     *
     * @param array  $files   $_FILES['images'] array (multiple)
     * @param int|null $vendorId   Restrict to vendor's products only (null = all)
     * @return array{matched: int, skipped: int, errors: string[]}
     */
    public static function processBulkImages(array $files, ?int $vendorId = null): array
    {
        $stats = ['matched' => 0, 'skipped' => 0, 'errors' => []];

        if (empty($files['name']) || !is_array($files['name'])) {
            $stats['errors'][] = 'No images were uploaded.';
            return $stats;
        }

        $allowedMime = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];

        // Preload products for matching
        $where = $vendorId !== null ? ' WHERE vendor_id = ' . $vendorId : '';
        $products = Database::fetchAll("SELECT id, slug, name_en, image FROM products{$where}");

        // Build lookup maps
        $byId   = [];
        $bySlug = [];
        $byName = [];
        foreach ($products as $p) {
            $byId[(int) $p['id']] = $p;
            $bySlug[strtolower(trim($p['slug']))] = $p;
            $byName[strtolower(trim($p['name_en']))] = $p;
        }

        $uploadDir = BACHAT_ROOT . '/uploads/products';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
            $stats['errors'][] = 'Cannot create uploads/products directory.';
            return $stats;
        }

        $fileCount = count($files['name']);
        for ($i = 0; $i < $fileCount; $i++) {
            $origName = (string) ($files['name'][$i] ?? '');
            $tmpFile  = (string) ($files['tmp_name'][$i] ?? '');
            $error    = (int) ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);
            $size     = (int) ($files['size'][$i] ?? 0);

            if ($error !== UPLOAD_ERR_OK || $size <= 0) {
                $stats['errors'][] = "'{$origName}' — upload error (code {$error}).";
                $stats['skipped']++;
                continue;
            }

            // Validate MIME
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime  = (string) $finfo->file($tmpFile);
            if (!isset($allowedMime[$mime])) {
                $stats['errors'][] = "'{$origName}' — unsupported format ({$mime}).";
                $stats['skipped']++;
                continue;
            }
            $ext = $allowedMime[$mime];

            // Max 5 MB per image
            if ($size > 5 * 1024 * 1024) {
                $stats['errors'][] = "'{$origName}' — file too large (max 5 MB).";
                $stats['skipped']++;
                continue;
            }

            // Determine match key from filename (without extension)
            $baseName = pathinfo($origName, PATHINFO_FILENAME);
            $matchKey = strtolower(trim($baseName));

            $matched = null;

            // Match by product ID
            if (ctype_digit($matchKey) && isset($byId[(int) $matchKey])) {
                $matched = $byId[(int) $matchKey];
            }
            // Match by slug
            if (!$matched && isset($bySlug[$matchKey])) {
                $matched = $bySlug[$matchKey];
            }
            // Match by product name (exact, case-insensitive)
            if (!$matched && isset($byName[$matchKey])) {
                $matched = $byName[$matchKey];
            }
            // Fuzzy: normalize dashes/underscores to spaces
            if (!$matched) {
                $normalized = str_replace(['-', '_'], ' ', $matchKey);
                $normalized = preg_replace('/\s+/', ' ', $normalized);
                if (isset($byName[$normalized])) {
                    $matched = $byName[$normalized];
                }
            }

            if (!$matched) {
                $stats['errors'][] = "'{$origName}' — no matching product found.";
                $stats['skipped']++;
                continue;
            }

            // Move and store
            $newName = bin2hex(random_bytes(16)) . '.' . $ext;
            $dest    = $uploadDir . '/' . $newName;
            if (!move_uploaded_file($tmpFile, $dest)) {
                $stats['errors'][] = "'{$origName}' — failed to save file.";
                $stats['skipped']++;
                continue;
            }
            @chmod($dest, 0644);

            $relPath = 'uploads/products/' . $newName;

            // Delete old image file if it exists
            if (!empty($matched['image'])) {
                $oldPath = BACHAT_ROOT . '/' . $matched['image'];
                if (is_file($oldPath)) {
                    @unlink($oldPath);
                }
            }

            // Update database
            Database::run('UPDATE products SET image = ?, updated_at = NOW() WHERE id = ?', [
                $relPath,
                (int) $matched['id'],
            ]);

            $stats['matched']++;
        }

        return $stats;
    }
}

