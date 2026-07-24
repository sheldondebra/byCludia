<?php
declare(strict_types=1);

$pageTitle = 'Shipping Policy – Hair by Claudia Darlene';
$pageDescription = 'Worldwide DHL Express shipping policy for Hair by Claudia Darlene custom hair orders.';
$canonical = url('shipping');
$policyHeading = 'Shipping Policy';
$policyIntro = 'At Hair by Claudia Darlene, we are committed to delivering your order with speed, care, and style — no matter where you are in the world.';

$policyBody = <<<'HTML'
  <section>
    <h2>Global Shipping</h2>
    <p>We proudly ship worldwide via <strong>DHL Express</strong>, ensuring fast and secure delivery to our customers across the globe. All orders are packaged in our signature premium boxes to guarantee that your hair arrives in perfect condition.</p>
  </section>

  <section>
    <h2>Delivery &amp; Processing Time</h2>
    <p>Each item is custom-made to order, which means we require a short processing period before dispatch.</p>
    <ul>
      <li><strong>Processing time:</strong> 3–14 business days (depending on the product type)</li>
      <li><strong>Shipping time:</strong> Express delivery timelines vary by location but typically range between 2–7 business days after dispatch</li>
    </ul>
    <p>You’ll receive a tracking number once your order has been shipped, allowing you to monitor your package every step of the way.</p>
  </section>

  <section>
    <h2>What’s Included in Every Shipment</h2>
    <ul>
      <li>Express, door-to-door delivery</li>
      <li>Real-time tracking updates</li>
      <li>Protective and luxurious packaging</li>
      <li>A care guide for maintaining your extensions or wigs</li>
    </ul>
  </section>

  <section>
    <h2>Shipping Confirmation</h2>
    <p>Once your order has been fulfilled and dispatched, we will send a confirmation email with tracking details. Please ensure your shipping address is accurate at checkout to avoid delays or issues.</p>
  </section>

  <section>
    <h2>Important Notes</h2>
    <ul>
      <li>We do not ship to P.O. Boxes.</li>
      <li>Shipping times may vary during high-volume periods or due to customs processing in your region.</li>
      <li>Any customs duties or taxes imposed by your country are the responsibility of the customer.</li>
    </ul>
  </section>

  <section>
    <h2>Need help?</h2>
    <p>For shipping inquiries or special delivery requests, feel free to contact our support team at <a href="mailto:info@byclaudiadarlene.com">info@byclaudiadarlene.com</a>.</p>
  </section>
HTML;

require ROOT_PATH . '/includes/partials/policy-layout.php';
