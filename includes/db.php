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
    }

    return $pdo;
}

function init_sqlite_schema(PDO $pdo): void
{
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      name TEXT NOT NULL,
      email TEXT NOT NULL UNIQUE,
      password TEXT NOT NULL,
      phone TEXT,
      role TEXT NOT NULL DEFAULT 'customer',
      loyalty_points INTEGER NOT NULL DEFAULT 0,
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
    ");
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
        ['hero_title', 'The Color Edit'],
        ['hero_subtitle', 'Reflect your inner beauty through everyday color.'],
        ['about_blurb', 'We ensure our hair products are ethically sourced. We create high-quality hair that sets trends. We provide premium hair for the beauty industry.'],
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
        [2, 'Afro Kinky Curly Wefted Bundles – 100g', 'afro-kinky-curly-wefted-bundles', 'True-to-texture wefts that blend with 4B–4C hair.', 'Premium ethically sourced Afro Kinky Curly wefted bundles.', 134, null, 'assets/images/products/p1.svg', 1, 0, 5, 4],
        [3, 'Exotic Afro Kinky Curly Feather Crochet', 'exotic-afro-kinky-curly-feather-crochet', 'Lightweight feather crochet for protective styles.', 'Exotic feather crochet in authentic 4B/4C texture.', 109, null, 'assets/images/products/p2.svg', 1, 0, 5, 0],
        [3, 'Kinky Straight Feather Crochet', 'kinky-straight-feather-crochet', 'Blowout texture crochet for sleek volume.', 'Kinky straight feather crochet with soft blowout texture.', 109, null, 'assets/images/products/p3.svg', 1, 0, 5, 0],
        [1, 'The Emefa Unit 200% Density', 'the-emefa-unit', '6×6 HD lace closure unit in S, M & L caps.', 'The Emefa Unit — 200% density with 6×6 HD lace closure.', 463, null, 'assets/images/products/p4.svg', 1, 0, 5, 1],
        [2, 'Exclusive Bundle Deals', 'exclusive-bundle-deals', '7% to 20% off our most-loved textures.', 'Save on curated bundle deals across best-selling textures.', 363, 420, 'assets/images/products/p5.svg', 1, 1, 5, 0],
        [2, 'Rich Auntie Kinky Straight Bundles', 'rich-auntie-kinky-straight-bundles', 'Silky kinky straight wefts with body and shine.', 'Rich Auntie kinky straight wefted bundles.', 134, null, 'assets/images/products/p6.svg', 1, 0, 5, 0],
        [2, 'Afro-Kinky Coily Wefted Bundles', 'afro-kinky-coily-wefted-bundles', 'Coily texture that matches natural coils.', 'Afro-Kinky Coily wefts for authentic coil pattern.', 134, null, 'assets/images/products/p7.svg', 1, 0, 5, 0],
        [2, 'Afro-Kinky Curly/Coily Clip-In Set', 'afro-kinky-clip-in-set', 'Clip-in set 160g–220g for instant volume.', 'Ready-to-wear clip-in set in Afro-Kinky texture.', 280, null, 'assets/images/products/p8.svg', 1, 0, 5, 1],
        [1, 'Ohemaa Unit (Queen Unit)', 'ohemaa-queen-unit', '4B/4C, 200% density, 13×4 HD frontal.', 'The Queen Unit — three bundles + 13×4 HD lace frontal.', 590, null, 'assets/images/products/p9.svg', 1, 0, 5, 0],
        [1, 'The Hollywood Unit', 'the-hollywood-unit', '200% density | 6×6 HD lace | S, M & L.', 'Glamorous Hollywood Unit with 200% density.', 490, null, 'assets/images/products/p10.svg', 1, 0, 5, 0],
        [2, 'The Siren Curly Bundles 3a-3b', 'the-siren-curly-bundles', 'Soft 3a–3b curls with bounce and shine.', 'The Siren Curly Bundles with defined curl pattern.', 195, null, 'assets/images/products/p11.svg', 1, 0, 5, 0],
        [4, 'Professional Hair Color Add-On', 'professional-hair-color-add-on', 'Custom professional coloring for any texture.', 'Add professional color to your order.', 35, null, 'assets/images/products/p12.svg', 1, 0, 5, 0],
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
 * Insert sample blog articles if none exist. Safe to call on every boot.
 */
function seed_blog_posts(PDO $pdo): void
{
    $count = (int) $pdo->query('SELECT COUNT(*) FROM blog_posts')->fetchColumn();
    if ($count > 0) {
        return;
    }

    $posts = [
        [
            'Choosing the Right Texture for Your Natural Hair',
            'choosing-the-right-texture',
            'From 3a curls to 4c coils — how to match extensions to your natural pattern for a seamless blend.',
            '<p>Finding the right texture is the difference between hair that blends effortlessly and hair that never quite sits right. Start by identifying your natural curl pattern — this is your anchor.</p>'
            . '<p>Our <strong>Afro Kinky Curly</strong> and <strong>Coily</strong> textures are designed to melt into 4b–4c hair, while the <strong>Siren Curly</strong> range suits softer 3a–3b patterns. When in doubt, size up slightly in coil definition; a fuller texture blends down more gracefully than a looser one blends up.</p>'
            . '<p>Consider your styling routine too. If you wear wash-and-go looks, match texture precisely. If you blow out or straighten often, a versatile kinky-straight weft gives you the most range.</p>',
            '2026-06-20 09:00:00',
        ],
        [
            'How to Care for Your Kinky Curly Bundles',
            'caring-for-kinky-curly-bundles',
            'A simple weekly ritual to keep curls soft, defined, and shedding-free wash after wash.',
            '<p>Great hair is maintained, not just installed. Treat your bundles like your own hair and they will reward you with months of wear.</p>'
            . '<p><strong>Cleanse gently.</strong> Use a sulfate-free shampoo every 1–2 weeks, working from root to tip in the direction of the hair to avoid tangling. <strong>Condition deeply.</strong> Follow with a hydrating mask, then detangle with your fingers and a wide-tooth comb.</p>'
            . '<p><strong>Refresh daily.</strong> A light water-and-leave-in mist revives curls each morning. At night, protect with a satin bonnet to prevent friction and dryness. Avoid heavy oils at the root — they weigh curls down and attract buildup.</p>',
            '2026-06-27 09:00:00',
        ],
        [
            'The Color Edit: Custom Coloring for Every Story',
            'the-color-edit-custom-coloring',
            'Why we color before you install — and how to choose a shade that reflects your inner beauty.',
            '<p>Color is personal. Our professional color add-on lets you customise any texture before it ever reaches your head, so the tone is even, rich, and ready to wear.</p>'
            . '<p>We recommend choosing a shade one level warmer than your goal — hair reads slightly cooler under natural light. For dimensional looks, ask about subtle money-piece framing around the face; it brightens instantly without a full color commitment.</p>'
            . '<p>Every color order is conditioned and sealed to protect the cuticle, so your investment stays soft and vibrant far longer.</p>',
            '2026-07-04 09:00:00',
        ],
    ];

    $stmt = $pdo->prepare(
        'INSERT INTO blog_posts (title, slug, excerpt, body, is_published, published_at) VALUES (?, ?, ?, ?, 1, ?)'
    );
    foreach ($posts as $p) {
        $stmt->execute($p);
    }
}
