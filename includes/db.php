<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    global $config;

    $sqlitePath = ROOT_PATH . '/database/store.sqlite';
    $useSqlite = !empty($config['use_sqlite']) || !empty($_ENV['USE_SQLITE']);

    // Prefer MySQL; fall back to SQLite for local demo without MySQL
    if (!$useSqlite) {
        try {
            $db = $config['db'];
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $db['host'],
                $db['port'],
                $db['name'],
                $db['charset']
            );
            $pdo = new PDO($dsn, $db['user'], $db['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            ensure_users_auth_schema($pdo, 'mysql');
            ensure_wishlist_share_schema($pdo, 'mysql');
            ensure_email_marketing_schema($pdo, 'mysql');
            ensure_blog_schema($pdo, 'mysql');
            ensure_seo_schema($pdo, 'mysql');
            ensure_product_badge_schema($pdo, 'mysql');
            ensure_shipping_schema($pdo, 'mysql');
            seed_blog_posts($pdo);
            return $pdo;
        } catch (Throwable $e) {
            // fall through to SQLite
        }
    }

    $needInit = !file_exists($sqlitePath);
    $pdo = new PDO('sqlite:' . $sqlitePath, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');

    if ($needInit) {
        init_sqlite_schema($pdo);
        seed_sqlite($pdo);
    } else {
        ensure_users_auth_schema($pdo, 'sqlite');
    }
    ensure_wishlist_share_schema($pdo, 'sqlite');
    ensure_email_marketing_schema($pdo, 'sqlite');
    ensure_blog_schema($pdo, 'sqlite');
    ensure_seo_schema($pdo, 'sqlite');
    ensure_product_badge_schema($pdo, 'sqlite');
    ensure_shipping_schema($pdo, 'sqlite');
    seed_blog_posts($pdo);

    return $pdo;
}

/**
 * Allow nullable email + unique phone for phone-only accounts.
 */
function ensure_users_auth_schema(PDO $pdo, string $driver): void
{
    try {
        if ($driver === 'mysql') {
            $pdo->exec('ALTER TABLE users MODIFY email VARCHAR(190) NULL');
            try {
                $pdo->exec('ALTER TABLE users ADD UNIQUE INDEX users_phone_unique (phone)');
            } catch (Throwable $e) {
                // index may already exist
            }
            return;
        }

        // SQLite: rebuild once if email is still NOT NULL
        $cols = $pdo->query('PRAGMA table_info(users)')->fetchAll();
        $emailNotNull = false;
        foreach ($cols as $col) {
            if (($col['name'] ?? '') === 'email' && (int) ($col['notnull'] ?? 0) === 1) {
                $emailNotNull = true;
                break;
            }
        }
        if (!$emailNotNull) {
            $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS users_phone_unique ON users(phone)');
            return;
        }

        $pdo->exec('PRAGMA foreign_keys = OFF');
        $pdo->exec('BEGIN');
        $pdo->exec("
            CREATE TABLE users_auth_mig (
              id INTEGER PRIMARY KEY AUTOINCREMENT,
              name TEXT NOT NULL,
              email TEXT UNIQUE,
              password TEXT NOT NULL,
              phone TEXT UNIQUE,
              role TEXT NOT NULL DEFAULT 'customer',
              loyalty_points INTEGER NOT NULL DEFAULT 0,
              created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $pdo->exec("
            INSERT INTO users_auth_mig (id, name, email, password, phone, role, loyalty_points, created_at)
            SELECT id, name,
                   CASE WHEN email IS NULL OR TRIM(email) = '' THEN NULL ELSE email END,
                   password,
                   CASE WHEN phone IS NULL OR TRIM(phone) = '' THEN NULL ELSE phone END,
                   role, loyalty_points, created_at
            FROM users
        ");
        $pdo->exec('DROP TABLE users');
        $pdo->exec('ALTER TABLE users_auth_mig RENAME TO users');
        $pdo->exec('COMMIT');
        $pdo->exec('PRAGMA foreign_keys = ON');
    } catch (Throwable $e) {
        // Non-fatal: auth pages still work for email accounts
        if ($driver === 'sqlite') {
            try {
                $pdo->exec('PRAGMA foreign_keys = ON');
            } catch (Throwable $ignored) {
            }
        }
    }
}

/** Add product badge flags (is_new). */
function ensure_product_badge_schema(PDO $pdo, string $driver): void
{
    try {
        if ($driver === 'mysql') {
            $cols = $pdo->query("SHOW COLUMNS FROM products LIKE 'is_new'")->fetchAll();
            if (!$cols) {
                $pdo->exec('ALTER TABLE products ADD COLUMN is_new TINYINT(1) NOT NULL DEFAULT 0 AFTER on_sale');
            }
            return;
        }

        $cols = $pdo->query('PRAGMA table_info(products)')->fetchAll();
        $has = false;
        foreach ($cols as $col) {
            if (($col['name'] ?? '') === 'is_new') {
                $has = true;
                break;
            }
        }
        if (!$has) {
            $pdo->exec('ALTER TABLE products ADD COLUMN is_new INTEGER NOT NULL DEFAULT 0');
        }
    } catch (Throwable $e) {
        // Non-fatal
    }
}

/** Country shipping overrides + optional order country code. */
function ensure_shipping_schema(PDO $pdo, string $driver): void
{
    try {
        if ($driver === 'mysql') {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS shipping_country_rates (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    country_code CHAR(2) NOT NULL,
                    rate DECIMAL(10,2) NOT NULL DEFAULT 0,
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    label VARCHAR(190) NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY shipping_country_rates_code_unique (country_code),
                    KEY shipping_country_rates_active_idx (country_code, is_active)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
            $cols = $pdo->query("SHOW COLUMNS FROM orders LIKE 'shipping_country_code'")->fetchAll();
            if (!$cols) {
                $pdo->exec('ALTER TABLE orders ADD COLUMN shipping_country_code CHAR(2) NULL AFTER shipping_country');
            }
            return;
        }

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS shipping_country_rates (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                country_code TEXT NOT NULL UNIQUE,
                rate REAL NOT NULL DEFAULT 0,
                is_active INTEGER NOT NULL DEFAULT 1,
                label TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )"
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS shipping_country_rates_active_idx ON shipping_country_rates (country_code, is_active)');

        $cols = $pdo->query('PRAGMA table_info(orders)')->fetchAll();
        $has = false;
        foreach ($cols as $col) {
            if (($col['name'] ?? '') === 'shipping_country_code') {
                $has = true;
                break;
            }
        }
        if (!$has) {
            $pdo->exec('ALTER TABLE orders ADD COLUMN shipping_country_code TEXT');
        }
    } catch (Throwable $e) {
        // Non-fatal
    }
}

/** Add unique share token column for public wishlist links. */
function ensure_wishlist_share_schema(PDO $pdo, string $driver): void
{
    try {
        if ($driver === 'mysql') {
            $cols = $pdo->query("SHOW COLUMNS FROM users LIKE 'wishlist_share_token'")->fetchAll();
            if (!$cols) {
                $pdo->exec('ALTER TABLE users ADD COLUMN wishlist_share_token VARCHAR(64) NULL');
                try {
                    $pdo->exec('ALTER TABLE users ADD UNIQUE INDEX users_wishlist_share_token_unique (wishlist_share_token)');
                } catch (Throwable $e) {
                    // index may already exist
                }
            }
            return;
        }

        $cols = $pdo->query('PRAGMA table_info(users)')->fetchAll();
        $has = false;
        foreach ($cols as $col) {
            if (($col['name'] ?? '') === 'wishlist_share_token') {
                $has = true;
                break;
            }
        }
        if (!$has) {
            $pdo->exec('ALTER TABLE users ADD COLUMN wishlist_share_token TEXT');
        }
        $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS users_wishlist_share_token_unique ON users(wishlist_share_token)');
    } catch (Throwable $e) {
        // Non-fatal
    }
}

function init_sqlite_schema(PDO $pdo): void
{
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      name TEXT NOT NULL,
      email TEXT UNIQUE,
      password TEXT NOT NULL,
      phone TEXT UNIQUE,
      role TEXT NOT NULL DEFAULT 'customer',
      loyalty_points INTEGER NOT NULL DEFAULT 0,
      wishlist_share_token TEXT UNIQUE,
      created_at TEXT DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS categories (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      name TEXT NOT NULL,
      slug TEXT NOT NULL UNIQUE,
      description TEXT,
      sort_order INTEGER NOT NULL DEFAULT 0
    );
    CREATE TABLE IF NOT EXISTS products (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      category_id INTEGER,
      name TEXT NOT NULL,
      slug TEXT NOT NULL UNIQUE,
      short_description TEXT,
      description TEXT,
      base_price REAL NOT NULL DEFAULT 0,
      compare_at_price REAL,
      image TEXT,
      gallery TEXT,
      video TEXT,
      is_featured INTEGER NOT NULL DEFAULT 0,
      is_active INTEGER NOT NULL DEFAULT 1,
      on_sale INTEGER NOT NULL DEFAULT 0,
      is_new INTEGER NOT NULL DEFAULT 0,
      rating REAL NOT NULL DEFAULT 5,
      review_count INTEGER NOT NULL DEFAULT 0,
      created_at TEXT DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS product_variants (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      product_id INTEGER NOT NULL,
      sku TEXT,
      label TEXT NOT NULL,
      option_length TEXT,
      option_texture TEXT,
      option_density TEXT,
      price REAL NOT NULL,
      stock INTEGER NOT NULL DEFAULT 0,
      is_active INTEGER NOT NULL DEFAULT 1
    );
    CREATE TABLE IF NOT EXISTS carts (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      session_id TEXT NOT NULL UNIQUE,
      user_id INTEGER,
      currency TEXT NOT NULL DEFAULT 'GBP',
      created_at TEXT DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS cart_items (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      cart_id INTEGER NOT NULL,
      product_id INTEGER NOT NULL,
      variant_id INTEGER NOT NULL,
      quantity INTEGER NOT NULL DEFAULT 1,
      unit_price REAL NOT NULL,
      gift_recipient_name TEXT,
      gift_recipient_email TEXT,
      gift_sender_name TEXT,
      gift_message TEXT
    );
    CREATE TABLE IF NOT EXISTS orders (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      order_number TEXT NOT NULL UNIQUE,
      user_id INTEGER,
      email TEXT NOT NULL,
      phone TEXT,
      status TEXT NOT NULL DEFAULT 'pending',
      currency TEXT NOT NULL DEFAULT 'GBP',
      exchange_rate REAL NOT NULL DEFAULT 1,
      subtotal REAL NOT NULL DEFAULT 0,
      shipping REAL NOT NULL DEFAULT 0,
      discount REAL NOT NULL DEFAULT 0,
      total REAL NOT NULL DEFAULT 0,
      payment_method TEXT,
      payment_ref TEXT,
      shipping_name TEXT,
      shipping_address TEXT,
      shipping_city TEXT,
      shipping_country TEXT,
      shipping_postcode TEXT,
      shipping_carrier TEXT,
      tracking_number TEXT,
      gift_card_code TEXT,
      gift_card_amount REAL,
      notes TEXT,
      created_at TEXT DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS order_items (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      order_id INTEGER NOT NULL,
      product_id INTEGER,
      variant_id INTEGER,
      product_name TEXT NOT NULL,
      variant_label TEXT,
      quantity INTEGER NOT NULL DEFAULT 1,
      unit_price REAL NOT NULL,
      line_total REAL NOT NULL,
      gift_recipient_name TEXT,
      gift_recipient_email TEXT,
      gift_sender_name TEXT,
      gift_message TEXT,
      gift_amount REAL
    );
    CREATE TABLE IF NOT EXISTS payments (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      order_id INTEGER NOT NULL,
      provider TEXT NOT NULL,
      provider_ref TEXT,
      amount REAL NOT NULL,
      currency TEXT NOT NULL,
      status TEXT NOT NULL DEFAULT 'pending',
      raw_payload TEXT,
      created_at TEXT DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS gift_cards (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      code TEXT NOT NULL UNIQUE,
      initial_amount REAL NOT NULL DEFAULT 0,
      balance REAL NOT NULL DEFAULT 0,
      currency TEXT NOT NULL DEFAULT 'GBP',
      recipient_name TEXT,
      recipient_email TEXT,
      sender_name TEXT,
      message TEXT,
      purchaser_email TEXT,
      order_id INTEGER,
      status TEXT NOT NULL DEFAULT 'active',
      created_at TEXT DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS currency_rates (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      code TEXT NOT NULL UNIQUE,
      name TEXT NOT NULL,
      symbol TEXT NOT NULL,
      rate_from_gbp REAL NOT NULL DEFAULT 1
    );
    CREATE TABLE IF NOT EXISTS settings (
      setting_key TEXT PRIMARY KEY,
      setting_value TEXT
    );
    CREATE TABLE IF NOT EXISTS newsletter_subscribers (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      email TEXT NOT NULL UNIQUE,
      name TEXT,
      created_at TEXT DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS testimonials (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      author_name TEXT NOT NULL,
      quote TEXT NOT NULL,
      is_active INTEGER NOT NULL DEFAULT 1,
      sort_order INTEGER NOT NULL DEFAULT 0
    );
    CREATE TABLE IF NOT EXISTS wishlists (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      user_id INTEGER NOT NULL,
      product_id INTEGER NOT NULL,
      created_at TEXT DEFAULT CURRENT_TIMESTAMP,
      UNIQUE(user_id, product_id)
    );
    CREATE TABLE IF NOT EXISTS coupons (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      code TEXT NOT NULL UNIQUE,
      type TEXT NOT NULL DEFAULT 'percent',
      value REAL NOT NULL,
      min_order REAL,
      max_uses INTEGER,
      used_count INTEGER NOT NULL DEFAULT 0,
      expires_at TEXT,
      is_active INTEGER NOT NULL DEFAULT 1
    );
    CREATE TABLE IF NOT EXISTS blog_posts (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      title TEXT NOT NULL,
      slug TEXT NOT NULL UNIQUE,
      excerpt TEXT,
      body TEXT NOT NULL,
      image TEXT,
      related_product_ids TEXT,
      is_published INTEGER NOT NULL DEFAULT 0,
      published_at TEXT,
      created_at TEXT DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS reviews (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      product_id INTEGER NOT NULL,
      user_id INTEGER,
      author_name TEXT NOT NULL,
      rating INTEGER NOT NULL DEFAULT 5,
      title TEXT,
      body TEXT NOT NULL,
      is_approved INTEGER NOT NULL DEFAULT 0,
      created_at TEXT DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS subscribers (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      name TEXT,
      phone TEXT,
      email TEXT,
      source TEXT DEFAULT 'popup',
      created_at TEXT DEFAULT CURRENT_TIMESTAMP,
      UNIQUE(phone)
    );
    CREATE TABLE IF NOT EXISTS email_campaigns (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      subject TEXT NOT NULL,
      preview_text TEXT,
      template_type TEXT NOT NULL DEFAULT 'promo',
      headline TEXT,
      body_html TEXT NOT NULL,
      cta_label TEXT,
      cta_url TEXT,
      hero_image TEXT,
      coupon_code TEXT,
      audience_json TEXT,
      status TEXT NOT NULL DEFAULT 'draft',
      sent_count INTEGER NOT NULL DEFAULT 0,
      failed_count INTEGER NOT NULL DEFAULT 0,
      recipient_count INTEGER NOT NULL DEFAULT 0,
      created_by INTEGER,
      sent_at TEXT,
      created_at TEXT DEFAULT CURRENT_TIMESTAMP,
      updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS email_campaign_recipients (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      campaign_id INTEGER NOT NULL,
      email TEXT NOT NULL,
      name TEXT,
      source TEXT,
      order_number TEXT,
      status TEXT NOT NULL DEFAULT 'pending',
      error_message TEXT,
      sent_at TEXT,
      FOREIGN KEY (campaign_id) REFERENCES email_campaigns(id) ON DELETE CASCADE
    );
    CREATE TABLE IF NOT EXISTS email_assets (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      path TEXT NOT NULL,
      original_name TEXT,
      created_at TEXT DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS email_unsubscribes (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      email TEXT NOT NULL UNIQUE,
      reason TEXT,
      created_at TEXT DEFAULT CURRENT_TIMESTAMP
    );
    ");
}

/**
 * Email marketing tables (safe to run on every request).
 */
function ensure_email_marketing_schema(PDO $pdo, string $driver): void
{
    try {
        if ($driver === 'mysql') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS email_campaigns (
              id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              subject VARCHAR(255) NOT NULL,
              preview_text VARCHAR(255) NULL,
              template_type VARCHAR(40) NOT NULL DEFAULT 'promo',
              headline VARCHAR(255) NULL,
              body_html LONGTEXT NOT NULL,
              cta_label VARCHAR(120) NULL,
              cta_url VARCHAR(500) NULL,
              hero_image VARCHAR(255) NULL,
              coupon_code VARCHAR(40) NULL,
              audience_json TEXT NULL,
              status VARCHAR(20) NOT NULL DEFAULT 'draft',
              sent_count INT NOT NULL DEFAULT 0,
              failed_count INT NOT NULL DEFAULT 0,
              recipient_count INT NOT NULL DEFAULT 0,
              created_by INT UNSIGNED NULL,
              sent_at DATETIME NULL,
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->exec("CREATE TABLE IF NOT EXISTS email_campaign_recipients (
              id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              campaign_id INT UNSIGNED NOT NULL,
              email VARCHAR(190) NOT NULL,
              name VARCHAR(120) NULL,
              source VARCHAR(40) NULL,
              order_number VARCHAR(32) NULL,
              status VARCHAR(20) NOT NULL DEFAULT 'pending',
              error_message TEXT NULL,
              sent_at DATETIME NULL,
              KEY idx_email_recip_campaign (campaign_id),
              KEY idx_email_recip_email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->exec("CREATE TABLE IF NOT EXISTS email_assets (
              id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              path VARCHAR(255) NOT NULL,
              original_name VARCHAR(255) NULL,
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->exec("CREATE TABLE IF NOT EXISTS email_unsubscribes (
              id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              email VARCHAR(190) NOT NULL UNIQUE,
              reason VARCHAR(255) NULL,
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            return;
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS email_campaigns (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          subject TEXT NOT NULL,
          preview_text TEXT,
          template_type TEXT NOT NULL DEFAULT 'promo',
          headline TEXT,
          body_html TEXT NOT NULL,
          cta_label TEXT,
          cta_url TEXT,
          hero_image TEXT,
          coupon_code TEXT,
          audience_json TEXT,
          status TEXT NOT NULL DEFAULT 'draft',
          sent_count INTEGER NOT NULL DEFAULT 0,
          failed_count INTEGER NOT NULL DEFAULT 0,
          recipient_count INTEGER NOT NULL DEFAULT 0,
          created_by INTEGER,
          sent_at TEXT,
          created_at TEXT DEFAULT CURRENT_TIMESTAMP,
          updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS email_campaign_recipients (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          campaign_id INTEGER NOT NULL,
          email TEXT NOT NULL,
          name TEXT,
          source TEXT,
          order_number TEXT,
          status TEXT NOT NULL DEFAULT 'pending',
          error_message TEXT,
          sent_at TEXT
        )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS email_assets (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          path TEXT NOT NULL,
          original_name TEXT,
          created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS email_unsubscribes (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          email TEXT NOT NULL UNIQUE,
          reason TEXT,
          created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )");
    } catch (Throwable $e) {
        // Non-fatal: email pages will surface DB errors if tables missing
    }
}

function seed_sqlite(PDO $pdo): void
{
    $pdo->exec("INSERT INTO currency_rates (code, name, symbol, rate_from_gbp) VALUES
      ('GBP', 'Pound Sterling', '£', 1.0),
      ('USD', 'US Dollar', '$', 1.27),
      ('EUR', 'Euro', '€', 1.17),
      ('GHS', 'Ghana Cedi', 'GH₵', 16.5)");

    // password: Admin123!
    $hash = password_hash('Admin123!', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'admin')");
    $stmt->execute(['Store Admin', 'admin@byclaudiadarlene.com', $hash]);

    $cats = [
        ['Wigs', 'wigs', 'Ready-to-wear units for every texture.', 1],
        ['Bundles', 'bundles', 'Wefted bundles for volume and length.', 2],
        ['Crochet', 'crochet', 'Feather crochet collections.', 3],
        ['Color', 'color', 'Professional color add-ons.', 4],
    ];
    $cStmt = $pdo->prepare('INSERT INTO categories (name, slug, description, sort_order) VALUES (?, ?, ?, ?)');
    foreach ($cats as $c) {
        $cStmt->execute($c);
    }

    $settings = [
        ['promo_banner', 'Worldwide Shipping Available | UK/EU: Klarna & Clearpay | Use code SUMMER10 for 10% OFF'],
        ['hero_title', 'Luxury Textured Hair'],
        ['hero_subtitle', 'Designed to blend seamlessly with your natural hair.'],
        ['about_blurb', 'At By Claudia Darlene, we believe textured hair should be celebrated, not compromised. Our 100% virgin human hair extensions are carefully sourced and crafted to blend seamlessly with natural textures, from silky straight to 4C coils. Every collection is designed with quality, longevity, and effortless beauty in mind, so you can wear your hair with confidence every day.'],
        ['shipping_flat', '15.00'],
        ['contact_phone', '+44 7342 590296'],
        ['contact_email', 'info@byclaudiadarlene.com'],
    ];
    $sStmt = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)');
    foreach ($settings as $s) {
        $sStmt->execute($s);
    }

    $testimonials = [
        ['Yayra', 'The best hair I’ve ever purchased. Soft, full, and blends perfectly with my natural texture.', 1],
        ['Renee', 'Hair by Claudia Darlene gave me my confidence back. It’s not just about the hair — it’s about finally feeling seen.', 2],
        ['Nia J', 'From packaging to quality, everything felt luxurious. The curls bounced back after every wash.', 3],
    ];
    $tStmt = $pdo->prepare('INSERT INTO testimonials (author_name, quote, sort_order) VALUES (?, ?, ?)');
    foreach ($testimonials as $t) {
        $tStmt->execute($t);
    }

    $products = [
        [2, 'Afro Kinky Curly Wefted Bundles – 100g', 'afro-kinky-curly-wefted-bundles', 'True-to-texture wefts that blend with 4B–4C hair.', 'Premium ethically sourced Afro Kinky Curly wefted bundles.', 134, null, 'assets/images/products/wp/Afro-kinky-curly-bundles.jpg', 1, 0, 5, 4],
        [3, 'Exotic Afro Kinky Curly Feather Crochet', 'exotic-afro-kinky-curly-feather-crochet', 'Lightweight feather crochet for protective styles.', 'Exotic feather crochet in authentic 4B/4C texture.', 109, null, 'assets/images/products/wp/crochet2-scaled.jpg', 1, 0, 5, 0],
        [3, 'Kinky Straight Feather Crochet', 'kinky-straight-feather-crochet', 'Blowout texture crochet for sleek volume.', 'Kinky straight feather crochet with soft blowout texture.', 109, null, 'assets/images/products/wp/ks-scaled.jpg', 1, 0, 5, 0],
        [1, 'The Emefa Unit 200% Density', 'the-emefa-unit', '6×6 HD lace closure unit in S, M & L caps.', 'The Emefa Unit — 200% density with 6×6 HD lace closure.', 463, null, 'assets/images/products/wp/Afroskinky-Curly-U-Part-Wig.jpg', 1, 0, 5, 1],
        [2, 'Exclusive Bundle Deals', 'exclusive-bundle-deals', '7% to 20% off our most-loved textures.', 'Save on curated bundle deals across best-selling textures.', 363, 420, 'assets/images/products/wp/Cover-Afrokinky-coily-bundles.jpg', 1, 1, 5, 0],
        [2, 'Rich Auntie Kinky Straight Bundles', 'rich-auntie-kinky-straight-bundles', 'Silky kinky straight wefts with body and shine.', 'Rich Auntie kinky straight wefted bundles.', 134, null, 'assets/images/products/wp/ks2-scaled.jpg', 1, 0, 5, 0],
        [2, 'Afro-Kinky Coily Wefted Bundles', 'afro-kinky-coily-wefted-bundles', 'Coily texture that matches natural coils.', 'Afro-Kinky Coily wefts for authentic coil pattern.', 134, null, 'assets/images/products/wp/Afrokinky-coily-bundles.jpg', 1, 0, 5, 0],
        [2, 'Afro-Kinky Curly/Coily Clip-In Set', 'afro-kinky-clip-in-set', 'Clip-in set 160g–220g for instant volume.', 'Ready-to-wear clip-in set in Afro-Kinky texture.', 280, null, 'assets/images/products/wp/Afro-Kinky-Curly-Clip-ins.jpg', 1, 0, 5, 1],
        [1, 'Ohemaa Unit (Queen Unit)', 'ohemaa-queen-unit', '4B/4C, 200% density, 13×4 HD frontal.', 'The Queen Unit — three bundles + 13×4 HD lace frontal.', 590, null, 'assets/images/products/wp/ohemaahair.jpg', 1, 0, 5, 0],
        [1, 'The Hollywood Unit', 'the-hollywood-unit', '200% density | 6×6 HD lace | S, M & L.', 'Glamorous Hollywood Unit with 200% density.', 490, null, 'assets/images/products/wp/it-girl.jpg', 1, 0, 5, 0],
        [2, 'The Siren Curly Bundles 3a-3b', 'the-siren-curly-bundles', 'Soft 3a–3b curls with bounce and shine.', 'The Siren Curly Bundles with defined curl pattern.', 195, null, 'assets/images/products/wp/siren-1--scaled.jpg', 1, 0, 5, 0],
        [4, 'Professional Hair Color Add-On', 'professional-hair-color-add-on', 'Custom professional coloring for any texture.', 'Add professional color to your order.', 35, null, 'assets/images/products/wp/Claudia.jpg', 1, 0, 5, 0],
    ];

    $pStmt = $pdo->prepare('INSERT INTO products (category_id, name, slug, short_description, description, base_price, compare_at_price, image, is_featured, on_sale, rating, review_count) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
    $vStmt = $pdo->prepare('INSERT INTO product_variants (product_id, sku, label, option_length, price, stock) VALUES (?,?,?,?,?,?)');

    foreach ($products as $i => $p) {
        $pStmt->execute($p);
        $pid = (int) $pdo->lastInsertId();
        $lengths = [
            ['14 inches', '14"', 0, 25],
            ['16 inches', '16"', 30, 20],
            ['18 inches', '18"', 60, 18],
            ['20 inches', '20"', 90, 15],
            ['22 inches', '22"', 120, 12],
        ];
        foreach ($lengths as $li => $len) {
            $vStmt->execute([
                $pid,
                'SKU-' . ($i + 1) . '-' . (14 + $li * 2),
                $len[0],
                $len[1],
                $p[5] + $len[2],
                $len[3],
            ]);
        }
    }

    $pdo->exec("INSERT INTO coupons (code, type, value, min_order, is_active) VALUES ('SUMMER10', 'percent', 10, 0, 1)");

    seed_blog_posts($pdo);
}

/**
 * Ensure blog_posts has related_product_ids for shoppable journal posts.
 */
function ensure_blog_schema(PDO $pdo, string $driver): void
{
    try {
        if ($driver === 'mysql') {
            $cols = $pdo->query('SHOW COLUMNS FROM blog_posts')->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('related_product_ids', $cols, true)) {
                $pdo->exec('ALTER TABLE blog_posts ADD COLUMN related_product_ids TEXT NULL AFTER image');
            }
            return;
        }
        $cols = $pdo->query('PRAGMA table_info(blog_posts)')->fetchAll();
        $names = array_map(static fn($c) => (string) ($c['name'] ?? ''), $cols);
        if (!in_array('related_product_ids', $names, true)) {
            $pdo->exec('ALTER TABLE blog_posts ADD COLUMN related_product_ids TEXT');
        }
    } catch (Throwable $e) {
        // Non-fatal
    }
}

/**
 * Insert sample blog articles if none exist. Safe to call on every boot.
 */
function seed_blog_posts(PDO $pdo): void
{
    $count = (int) $pdo->query('SELECT COUNT(*) FROM blog_posts')->fetchColumn();
    if ($count > 0) {
        return;
    }

    $shop = 'index.php?page=shop';
    $p = static fn(string $slug): string => 'index.php?page=product&slug=' . rawurlencode($slug);

    $posts = [
        [
            'How to Wash Virgin Human Hair Extensions Without Damage',
            'how-to-wash-virgin-human-hair-extensions',
            'A gentle wash-day routine for virgin human hair extensions — sulfate-free cleansing, deep conditioning, and drying tips that keep bundles soft for longer.',
            '<p>Virgin human hair extensions do not get oil from your scalp the way natural hair does, so wash day is less about scrubbing and more about restoring moisture. Follow this routine and your <a href="' . $shop . '">By Claudia Darlene collection</a> will stay soft, shiny, and tangle-resistant.</p>'
            . '<h2>How often should you wash?</h2>'
            . '<p>Most wearers wash every <strong>7–14 days</strong>, or sooner if you use heavy products or sweat often. Over-washing strips softness; under-washing causes buildup and matting.</p>'
            . '<h2>Wash-day essentials</h2>'
            . '<ul><li>Sulfate-free shampoo</li><li>Moisturizing conditioner or deep mask</li><li>Wide-tooth comb</li><li>Microfiber towel or soft T-shirt</li><li>Lukewarm water (never hot)</li></ul>'
            . '<h2>Step-by-step wash</h2>'
            . '<ol><li><strong>Detangle first.</strong> Work in sections from ends upward before any water touches the hair.</li>'
            . '<li><strong>Wet with lukewarm water.</strong> Hot water lifts the cuticle and invites frizz.</li>'
            . '<li><strong>Shampoo downward.</strong> Stroke root to tip — never scrub in circles or bunch the hair.</li>'
            . '<li><strong>Rinse thoroughly.</strong> Residue is a leading cause of dull, sticky strands.</li>'
            . '<li><strong>Deep condition mid-lengths to ends</strong> for 15–30 minutes. Avoid soaking the weft seam if you want to protect stitches.</li>'
            . '<li><strong>Finish with cool water</strong> to help seal the cuticle.</li>'
            . '<li><strong>Pat dry</strong> (do not wring). Air-dry when possible, or diffuse on low heat.</li></ol>'
            . '<h2>Aftercare seal</h2>'
            . '<p>Once dry, add a lightweight leave-in or a drop of argan/jojoba oil on the ends only. Heavy oils at the weft attract buildup.</p>'
            . '<p>Ready for fresh texture? Explore our <a href="' . $p('afro-kinky-curly-wefted-bundles') . '">Afro Kinky Curly wefted bundles</a> and <a href="' . $p('afro-kinky-coily-wefted-bundles') . '">Afro-Kinky Coily bundles</a>.</p>',
            '2026-05-15 09:00:00',
        ],
        [
            'How to Keep Curly and Coily Hair Extensions Soft Between Washes',
            'keep-curly-coily-extensions-soft',
            'Daily moisture habits that stop frizz and tangles on kinky curly and coily extensions — without over-washing or product overload.',
            '<p>Textured extensions look most natural when they stay hydrated. Because coils have more bends, they lose moisture faster — which is why a light daily ritual matters more than constant washing.</p>'
            . '<h2>The soft-curl formula</h2>'
            . '<p><strong>Hydrate → define → protect.</strong> Keep it simple so curls stay bouncy instead of crunchy or greasy.</p>'
            . '<h2>Morning refresh (2–3 minutes)</h2>'
            . '<ol><li>Mist lightly with water or a water-based refresher.</li>'
            . '<li>Apply a pea-sized leave-in or curl cream in sections.</li>'
            . '<li>Scrunch upward to wake the pattern — fingers first, wide-tooth comb only if needed.</li></ol>'
            . '<h2>What to avoid</h2>'
            . '<ul><li>Alcohol-heavy gels and sprays that dry the cuticle</li>'
            . '<li>Layering too many creams (buildup = dull, sticky curls)</li>'
            . '<li>Brushing dry kinky hair from root to tip</li>'
            . '<li>Sleeping on unprotected cotton pillowcases</li></ul>'
            . '<h2>Heat, sparingly</h2>'
            . '<p>Limit flat irons and high-heat blow-drying. If you must restyle, use a heat protectant and keep tools on low. Air-drying or a cool diffuser preserves the coil memory in textures like our <a href="' . $p('the-siren-curly-bundles') . '">Siren Curly 3a–3b bundles</a> and <a href="' . $p('afro-kinky-curly-wefted-bundles') . '">Afro Kinky Curly wefts</a>.</p>'
            . '<p>Soft hair is consistent hair — a little moisture every day beats a heavy rescue wash later.</p>',
            '2026-05-28 09:00:00',
        ],
        [
            'Night Care for Hair Extensions: Bonnets, Braids, and Storage Tips',
            'night-care-hair-extensions-bonnet-storage',
            'How to keep hair extensions frizz-free overnight — satin protection, loose braids, and smart storage for clip-ins, units, and bundles.',
            '<p>Most extension damage happens while you sleep. Friction from cotton pillowcases creates frizz, tangles, and shedding — especially on textured virgin hair. A calm night routine protects your investment.</p>'
            . '<h2>Before bed checklist</h2>'
            . '<ol><li><strong>Make sure hair is dry.</strong> Sleeping on damp extensions is a fast path to matting.</li>'
            . '<li><strong>Detangle gently</strong> from ends up with fingers or a wide-tooth comb.</li>'
            . '<li><strong>Loosely braid, twist, or pineapple</strong> the hair to reduce overnight friction.</li>'
            . '<li><strong>Cover with a satin or silk bonnet</strong> — or sleep on a satin pillowcase.</li></ol>'
            . '<h2>Clip-ins and units</h2>'
            . '<p>Remove clip-ins nightly. Hang or store flat in a satin bag away from sunlight and humidity. Ready-to-wear pieces like the <a href="' . $p('the-emefa-unit') . '">Emefa Unit</a>, <a href="' . $p('ohemaa-queen-unit') . '">Ohemaa Queen Unit</a>, and <a href="' . $p('the-hollywood-unit') . '">Hollywood Unit</a> also last longer when brushed out and covered after wear.</p>'
            . '<h2>Between installs</h2>'
            . '<p>If you are storing bundles, keep them clean, fully dry, and sealed in a satin pouch. Never leave virgin hair in a steamy bathroom long-term.</p>'
            . '<p>Protect at night, and your daytime style stays fuller with less work.</p>',
            '2026-06-10 09:00:00',
        ],
        [
            'Choosing the Right Texture for Your Natural Hair',
            'choosing-the-right-texture',
            'From silky straight to 4C coils — how to match By Claudia Darlene virgin hair textures to your natural pattern for a seamless blend.',
            '<p>The right texture is the difference between extensions that melt into your hair and hair that never quite sits right. Start with your natural curl pattern — that is your match point.</p>'
            . '<h2>Quick texture map</h2>'
            . '<ul><li><strong>4B–4C / coily:</strong> <a href="' . $p('afro-kinky-coily-wefted-bundles') . '">Afro-Kinky Coily wefted bundles</a> and <a href="' . $p('afro-kinky-curly-wefted-bundles') . '">Afro Kinky Curly wefts</a></li>'
            . '<li><strong>Defined kinky curl:</strong> Afro Kinky Curly bundles or crochet options like <a href="' . $p('exotic-afro-kinky-curly-feather-crochet') . '">Exotic Afro Kinky Curly Feather Crochet</a></li>'
            . '<li><strong>3A–3B soft curl:</strong> <a href="' . $p('the-siren-curly-bundles') . '">Siren Curly Bundles</a></li>'
            . '<li><strong>Blown-out / sleek days:</strong> <a href="' . $p('rich-auntie-kinky-straight-bundles') . '">Rich Auntie Kinky Straight</a> or <a href="' . $p('kinky-straight-feather-crochet') . '">Kinky Straight Feather Crochet</a></li></ul>'
            . '<h2>Blend tip</h2>'
            . '<p>When unsure, choose a slightly fuller coil definition. A denser texture blends down more gracefully than a looser pattern tries to blend up.</p>'
            . '<h2>Think about how you style</h2>'
            . '<p>Wash-and-go lovers should match texture closely. If you straighten often, kinky-straight wefts give the most range without fighting your curl memory every morning.</p>'
            . '<p>Browse the full <a href="' . $shop . '">Our Collection</a> and filter by the texture that feels like you.</p>',
            '2026-06-20 09:00:00',
        ],
        [
            'Virgin Afro Kinky Bundles Explained: Curly, Coily, and Bundle Deals',
            'virgin-afro-kinky-bundles-guide',
            'SEO guide to virgin afro kinky curly and coily wefted bundles — who they suit, how they differ, and how to shop By Claudia Darlene bundles with confidence.',
            '<p>Afro kinky and coily textures are crafted to celebrate natural patterns — not flatten them. At By Claudia Darlene, our virgin human hair bundles are sourced and finished to blend with textured roots while staying soft enough for everyday wear.</p>'
            . '<h2>Curly vs coily wefts</h2>'
            . '<p><a href="' . $p('afro-kinky-curly-wefted-bundles') . '"><strong>Afro Kinky Curly Wefted Bundles (100g)</strong></a> deliver springy, true-to-texture volume that pairs beautifully with 4B–4C hair. <a href="' . $p('afro-kinky-coily-wefted-bundles') . '"><strong>Afro-Kinky Coily Wefted Bundles</strong></a> lean into tighter coil definition for a denser, more contracted silhouette.</p>'
            . '<h2>How much hair do you need?</h2>'
            . '<p>Most full installs use 2–3 bundles depending on length and desired density. For value, start with our <a href="' . $p('exclusive-bundle-deals') . '">Exclusive Bundle Deals</a> when you want a complete look without guessing quantities.</p>'
            . '<h2>Why virgin hair matters</h2>'
            . '<p>Virgin hair has an intact cuticle, so it holds moisture better, sheds less when cared for properly, and can be customized with our <a href="' . $p('professional-hair-color-add-on') . '">Professional Hair Color Add-On</a>.</p>'
            . '<h2>Install styles</h2>'
            . '<p>Wefts work for sew-ins and wig constructions. Prefer temporary glam? Pair texture research with our <a href="' . $p('afro-kinky-clip-in-set') . '">Afro-Kinky Clip-In Set</a> for damage-free volume.</p>'
            . '<p>Shop textured excellence in <a href="' . $shop . '">Our Collection</a> and wear your coils with confidence.</p>',
            '2026-07-02 09:00:00',
        ],
        [
            'Ready-to-Wear Units Guide: Emefa, Ohemaa, and Hollywood',
            'ready-to-wear-units-emefa-ohemaa-hollywood',
            'Compare By Claudia Darlene ready-to-wear units — density, vibe, and who each unit is for — so you can choose glueless-ready luxury with less guesswork.',
            '<p>Units are the shortcut to polished volume without a full sew-in appointment. Our ready-to-wear pieces are built for textured beauty — dense, natural-looking, and designed to feel like a finished look out of the box.</p>'
            . '<h2>The Emefa Unit — 200% density</h2>'
            . '<p>The <a href="' . $p('the-emefa-unit') . '">Emefa Unit</a> is for statement volume. At 200% density, it photographs rich and full — ideal when you want maximum body and a glam silhouette.</p>'
            . '<h2>Ohemaa (Queen) Unit</h2>'
            . '<p>The <a href="' . $p('ohemaa-queen-unit') . '">Ohemaa Unit</a> channels regal everyday luxury: balanced density, soft movement, and a crown-ready finish for work-to-weekend wear.</p>'
            . '<h2>The Hollywood Unit</h2>'
            . '<p>The <a href="' . $p('the-hollywood-unit') . '">Hollywood Unit</a> leans camera-ready and sleek-glam — choose it when you want polished edges and red-carpet softness.</p>'
            . '<h2>Care tips for units</h2>'
            . '<ul><li>Brush gently from ends before and after wear</li><li>Wash on a schedule similar to bundles (sulfate-free)</li><li>Store on a stand or in a satin bag</li><li>Customize tone with our color add-on before first wear if desired</li></ul>'
            . '<p>Explore all units inside <a href="' . $shop . '">Our Collection</a> and find the one that feels like your signature.</p>',
            '2026-07-12 09:00:00',
        ],
        [
            'Clip-Ins vs Wefts vs Crochet: Which Hair Install Is Right for You?',
            'clip-ins-vs-wefts-vs-crochet',
            'Compare clip-in sets, wefted bundles, and feather crochet hair — wear time, styling flexibility, and which By Claudia Darlene option fits your lifestyle.',
            '<p>Not every beauty routine needs the same install. The best choice depends on how often you switch styles, how much time you have, and how permanent you want the look to feel.</p>'
            . '<h2>Clip-ins — flexible and protective</h2>'
            . '<p><a href="' . $p('afro-kinky-clip-in-set') . '">Afro-Kinky Clip-In Sets</a> are ideal for weekends, events, and protective styling breaks. Remove them at night, store properly, and your natural hair gets rest between wears.</p>'
            . '<h2>Wefted bundles — classic longevity</h2>'
            . '<p>Wefts like <a href="' . $p('afro-kinky-curly-wefted-bundles') . '">Afro Kinky Curly</a> and <a href="' . $p('rich-auntie-kinky-straight-bundles') . '">Rich Auntie Kinky Straight</a> are the sew-in standard: customizable length, density, and parting with a stylist.</p>'
            . '<h2>Feather crochet — lightweight texture</h2>'
            . '<p>Crochet options such as <a href="' . $p('exotic-afro-kinky-curly-feather-crochet') . '">Exotic Afro Kinky Curly Feather Crochet</a> and <a href="' . $p('kinky-straight-feather-crochet') . '">Kinky Straight Feather Crochet</a> install faster and feel airy — great when you want texture without heavy bulk.</p>'
            . '<h2>Add color intentionally</h2>'
            . '<p>Whatever you choose, our <a href="' . $p('professional-hair-color-add-on') . '">Professional Hair Color Add-On</a> lets you customize shade before install for even, salon-ready tone.</p>'
            . '<p>Still deciding? Start in <a href="' . $shop . '">Our Collection</a> and filter by the install style that matches your week — not just your wish list.</p>',
            '2026-07-22 09:00:00',
        ],
    ];

    $stmt = $pdo->prepare(
        'INSERT INTO blog_posts (title, slug, excerpt, body, is_published, published_at) VALUES (?, ?, ?, ?, 1, ?)'
    );
    foreach ($posts as $post) {
        $stmt->execute($post);
    }
}
