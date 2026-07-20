-- Seed data for Hair by Claudia Darlene
INSERT INTO currency_rates (code, name, symbol, rate_from_gbp) VALUES
('GBP', 'Pound Sterling', '£', 1.000000),
('USD', 'US Dollar', '$', 1.270000),
('EUR', 'Euro', '€', 1.170000),
('GHS', 'Ghana Cedi', 'GH₵', 16.500000)
ON DUPLICATE KEY UPDATE rate_from_gbp = VALUES(rate_from_gbp);

-- Admin password: Admin123!
INSERT INTO users (name, email, password, role) VALUES
('Store Admin', 'admin@byclaudiadarlene.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin')
ON DUPLICATE KEY UPDATE email = email;

INSERT INTO categories (name, slug, description, sort_order) VALUES
('Wigs', 'wigs', 'Ready-to-wear units for every texture.', 1),
('Bundles', 'bundles', 'Wefted bundles for volume and length.', 2),
('Crochet', 'crochet', 'Feather crochet collections.', 3),
('Color', 'color', 'Professional color add-ons.', 4)
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO settings (setting_key, setting_value) VALUES
('promo_banner', 'Worldwide Shipping Available | UK/EU: Klarna & Clearpay | Use code SUMMER10 for 10% OFF'),
('hero_title', 'The Color Edit'),
('hero_subtitle', 'Reflect your inner beauty through everyday color.'),
('about_blurb', 'We ensure our hair products are ethically sourced. We create high-quality hair that sets trends. We provide premium hair for the beauty industry.'),
('shipping_flat', '15.00'),
('contact_phone', '+44 7342 590296'),
('contact_email', 'info@byclaudiadarlene.com')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

INSERT INTO testimonials (author_name, quote, sort_order) VALUES
('Yayra', 'The best hair I’ve ever purchased. Soft, full, and blends perfectly with my natural texture. I get compliments every time I wear it.', 1),
('Renee', 'Hair by Claudia Darlene gave me my confidence back. It’s not just about the hair — it’s about finally feeling seen.', 2),
('Nia J', 'From packaging to quality, everything felt luxurious. The curls bounced back after every wash — I’m officially obsessed.', 3);

INSERT INTO products (category_id, name, slug, short_description, description, base_price, compare_at_price, image, is_featured, on_sale, rating, review_count) VALUES
(2, 'Afro Kinky Curly Wefted Bundles – 100g', 'afro-kinky-curly-wefted-bundles', 'True-to-texture wefts that blend with 4B–4C hair.', 'Premium ethically sourced Afro Kinky Curly wefted bundles. Soft, full, and designed to blend seamlessly with natural textures.', 134.00, NULL, 'assets/images/products/bundle-curly.jpg', 1, 0, 5.00, 4),
(3, 'Exotic Afro Kinky Curly Feather Crochet', 'exotic-afro-kinky-curly-feather-crochet', 'Lightweight feather crochet for protective styles.', 'Exotic feather crochet in authentic 4B/4C texture. Lightweight, versatile, and easy to install.', 109.00, NULL, 'assets/images/products/crochet-curly.jpg', 1, 0, 5.00, 0),
(3, 'Kinky Straight Feather Crochet', 'kinky-straight-feather-crochet', 'Blowout texture crochet for sleek volume.', 'Kinky straight feather crochet with soft blowout texture. Perfect for polished protective looks.', 109.00, NULL, 'assets/images/products/crochet-straight.jpg', 1, 0, 5.00, 0),
(1, 'The Emefa Unit 200% Density', 'the-emefa-unit', '6×6 HD lace closure unit in S, M & L caps.', 'The Emefa Unit — 200% density with 6×6 HD lace closure. Available in S, M & L caps for a custom fit.', 463.00, NULL, 'assets/images/products/emefa-unit.jpg', 1, 0, 5.00, 1),
(2, 'Exclusive Bundle Deals', 'exclusive-bundle-deals', '7% to 20% off our most-loved textures.', 'Save on curated bundle deals across our best-selling textures. Limited-time savings on signature hair.', 363.00, 420.00, 'assets/images/products/bundle-deal.jpg', 1, 1, 5.00, 0),
(2, 'Rich Auntie Kinky Straight Wefted Bundles', 'rich-auntie-kinky-straight-bundles', 'Silky kinky straight wefts with body and shine.', 'Rich Auntie kinky straight wefted bundles — soft, polished, and full of movement.', 134.00, NULL, 'assets/images/products/bundle-straight.jpg', 1, 0, 5.00, 0),
(2, 'Afro-Kinky Coily Wefted Bundles – 100g', 'afro-kinky-coily-wefted-bundles', 'Coily texture that matches natural coils.', 'Afro-Kinky Coily wefts crafted for authentic coil pattern and lasting softness.', 134.00, NULL, 'assets/images/products/bundle-coily.jpg', 1, 0, 5.00, 0),
(2, 'Afro-Kinky Curly/Coily Clip-In Set', 'afro-kinky-clip-in-set', 'Clip-in set 160g–220g for instant volume.', 'Ready-to-wear clip-in set in Afro-Kinky Curly/Coily texture. Instant length and volume without commitment.', 280.00, NULL, 'assets/images/products/clip-ins.jpg', 1, 0, 5.00, 1),
(1, 'Ohemaa Unit (Queen Unit)', 'ohemaa-queen-unit', '4B/4C, 200% density, 13×4 HD frontal.', 'The Queen Unit — 4B/4C hair, 200% density, three bundles + 13×4 HD lace frontal.', 590.00, NULL, 'assets/images/products/ohemaa-unit.jpg', 1, 0, 5.00, 0),
(1, 'The Hollywood Unit', 'the-hollywood-unit', '200% density | 6×6 HD lace | S, M & L.', 'Glamorous Hollywood Unit with 200% density and 6×6 HD lace closure. Cap sizes S, M & L.', 490.00, NULL, 'assets/images/products/hollywood-unit.jpg', 1, 0, 5.00, 0),
(2, 'The Siren Curly Bundles 3a-3b', 'the-siren-curly-bundles', 'Soft 3a–3b curls with bounce and shine.', 'The Siren Curly Bundles — defined 3a–3b curls that hold shape wash after wash.', 195.00, NULL, 'assets/images/products/siren-curly.jpg', 1, 0, 5.00, 0),
(4, 'Professional Hair Color Add-On', 'professional-hair-color-add-on', 'Custom professional coloring for any texture.', 'Add professional color to your order. Custom shades for every texture story.', 35.00, NULL, 'assets/images/products/color-addon.jpg', 1, 0, 5.00, 0);

-- Blog articles
INSERT INTO blog_posts (title, slug, excerpt, body, is_published, published_at) VALUES
('Choosing the Right Texture for Your Natural Hair', 'choosing-the-right-texture', 'From 3a curls to 4c coils — how to match extensions to your natural pattern for a seamless blend.', '<p>Finding the right texture is the difference between hair that blends effortlessly and hair that never quite sits right. Start by identifying your natural curl pattern — this is your anchor.</p><p>Our <strong>Afro Kinky Curly</strong> and <strong>Coily</strong> textures are designed to melt into 4b–4c hair, while the <strong>Siren Curly</strong> range suits softer 3a–3b patterns. When in doubt, size up slightly in coil definition; a fuller texture blends down more gracefully than a looser one blends up.</p><p>Consider your styling routine too. If you wear wash-and-go looks, match texture precisely. If you blow out or straighten often, a versatile kinky-straight weft gives you the most range.</p>', 1, '2026-06-20 09:00:00'),
('How to Care for Your Kinky Curly Bundles', 'caring-for-kinky-curly-bundles', 'A simple weekly ritual to keep curls soft, defined, and shedding-free wash after wash.', '<p>Great hair is maintained, not just installed. Treat your bundles like your own hair and they will reward you with months of wear.</p><p><strong>Cleanse gently.</strong> Use a sulfate-free shampoo every 1–2 weeks, working from root to tip in the direction of the hair to avoid tangling. <strong>Condition deeply.</strong> Follow with a hydrating mask, then detangle with your fingers and a wide-tooth comb.</p><p><strong>Refresh daily.</strong> A light water-and-leave-in mist revives curls each morning. At night, protect with a satin bonnet to prevent friction and dryness. Avoid heavy oils at the root — they weigh curls down and attract buildup.</p>', 1, '2026-06-27 09:00:00'),
('The Color Edit: Custom Coloring for Every Story', 'the-color-edit-custom-coloring', 'Why we color before you install — and how to choose a shade that reflects your inner beauty.', '<p>Color is personal. Our professional color add-on lets you customise any texture before it ever reaches your head, so the tone is even, rich, and ready to wear.</p><p>We recommend choosing a shade one level warmer than your goal — hair reads slightly cooler under natural light. For dimensional looks, ask about subtle money-piece framing around the face; it brightens instantly without a full color commitment.</p><p>Every color order is conditioned and sealed to protect the cuticle, so your investment stays soft and vibrant far longer.</p>', 1, '2026-07-04 09:00:00');

-- Variants (length options) for each product
INSERT INTO product_variants (product_id, sku, label, option_length, price, stock)
SELECT p.id, CONCAT(UPPER(LEFT(p.slug, 8)), '-14'), '14 inches', '14"', p.base_price, 25 FROM products p
UNION ALL
SELECT p.id, CONCAT(UPPER(LEFT(p.slug, 8)), '-16'), '16 inches', '16"', p.base_price + 30, 20 FROM products p
UNION ALL
SELECT p.id, CONCAT(UPPER(LEFT(p.slug, 8)), '-18'), '18 inches', '18"', p.base_price + 60, 18 FROM products p
UNION ALL
SELECT p.id, CONCAT(UPPER(LEFT(p.slug, 8)), '-20'), '20 inches', '20"', p.base_price + 90, 15 FROM products p
UNION ALL
SELECT p.id, CONCAT(UPPER(LEFT(p.slug, 8)), '-22'), '22 inches', '22"', p.base_price + 120, 12 FROM products p;
