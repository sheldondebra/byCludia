<?php
declare(strict_types=1);

$pageTitle = 'Terms and Conditions – Hair by Claudia Darlene';
$pageDescription = 'Terms and conditions for using the Hair by Claudia Darlene website and services.';
$canonical = url('terms');
$policyHeading = 'Terms and Conditions';
$policyIntro = 'Welcome to Hair by Claudia Darlene. These terms and conditions outline the rules and regulations for the use of our website and services. By accessing or using this website, you agree to comply with and be bound by these terms. If you do not agree with any part of these terms, please do not use our website.';

$returnsUrl = e(url('index.php?page=returns-policy'));
$shippingUrl = e(url('index.php?page=shipping-policy'));
$privacyUrl = e(url('index.php?page=privacy-policy'));

$policyBody = <<<HTML
  <section>
    <h2>1. Use of the Website</h2>
    <p>You agree to use this website for lawful purposes only. You must not misuse the site, engage in unlawful activities, or attempt to breach its security features.</p>
  </section>

  <section>
    <h2>2. Product Information</h2>
    <p>We aim to display our products and descriptions as accurately as possible. However, we cannot guarantee that colors, textures, or features will be exactly as seen on your screen. All items are custom-made and may vary slightly due to natural hair variations.</p>
  </section>

  <section>
    <h2>3. Orders &amp; Payments</h2>
    <p>All orders are subject to acceptance and availability. Once you place an order, you will receive an order confirmation. Payment must be made in full before your order is processed.</p>
    <p>We accept secure payments via trusted providers. We reserve the right to cancel or refuse any order if fraud or misuse is suspected.</p>
  </section>

  <section>
    <h2>4. Custom-Made Products</h2>
    <p>All of our wigs and extensions are made to order. As such, we do not offer refunds or exchanges for incorrect ordering or a change of mind. Please review our <a href="{$returnsUrl}">Returns Policy</a> for details on faulty item replacements.</p>
  </section>

  <section>
    <h2>5. Shipping &amp; Delivery</h2>
    <p>We ship worldwide via DHL Express. Shipping times and processing periods may vary by region and product type. Customers are responsible for any customs duties or local taxes in their country. Please refer to our <a href="{$shippingUrl}">Shipping Policy</a> for more.</p>
  </section>

  <section>
    <h2>6. Intellectual Property</h2>
    <p>All content on this website — including text, images, logos, product designs, and trademarks — is the property of Hair by Claudia Darlene and may not be used or reproduced without our express permission.</p>
  </section>

  <section>
    <h2>7. Privacy</h2>
    <p>We take your privacy seriously. Please refer to our <a href="{$privacyUrl}">Privacy Policy</a> to understand how we collect, use, and protect your personal data.</p>
  </section>


  <section>
    <h2>8. Limitation of Liability</h2>
    <p>We are not liable for any direct, indirect, incidental, or consequential damages arising from your use of this website or purchase of products. All products are used at the customer’s own risk. Always consult a professional stylist before coloring or chemically altering your hair.</p>
  </section>

  <section>
    <h2>9. User Accounts</h2>
    <p>If you create an account on our site, you are responsible for maintaining its confidentiality and all activities under your login. We reserve the right to suspend or terminate accounts that violate these terms.</p>
  </section>

  <section>
    <h2>10. Promotions &amp; Discounts</h2>
    <p>Any promotional offers, sales, or discount codes are subject to availability and specific terms. We reserve the right to withdraw or change promotions at any time without prior notice.</p>
  </section>

  <section>
    <h2>11. Governing Law</h2>
    <p>These terms are governed by and construed in accordance with the laws of the United Kingdom. Any disputes arising from these terms shall be subject to the exclusive jurisdiction of the UK courts.</p>
  </section>

  <section>
    <h2>12. Updates to Terms</h2>
    <p>We may revise these Terms and Conditions from time to time. Any changes will be posted on this page, and your continued use of the site implies your acceptance of these updates.</p>
  </section>

  <section>
    <h2>Contact Us</h2>
    <p>For any questions or concerns regarding these terms, please contact: <a href="mailto:info@byclaudiadarlene.com">info@byclaudiadarlene.com</a></p>
  </section>
HTML;

require ROOT_PATH . '/includes/partials/policy-layout.php';
