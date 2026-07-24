<?php
declare(strict_types=1);

$pageTitle = 'Returns Policy – Hair by Claudia Darlene';
$pageDescription = 'Returns policy for custom-made hair extensions and wigs from Hair by Claudia Darlene.';
$canonical = url('returns');
$policyHeading = 'Returns Policy';
$policyIntro = 'At Hair by Claudia Darlene, we pride ourselves on providing high-quality, custom-made real hair extensions and wigs tailored to meet your specific needs. Due to the bespoke nature of our products, we kindly ask that you review our Returns Policy carefully before placing an order.';

$policyBody = <<<'HTML'
  <section>
    <h2>Custom Orders</h2>
    <p>All of our products are custom made to order, meaning each item is uniquely crafted based on your selected specifications — including length, color, texture, and style. Because of this, we do not offer refunds or exchanges once an order has been shipped.</p>
  </section>

  <section>
    <h2>Faulty or Defective Items</h2>
    <p>In the rare event that you receive a product that is faulty or does not meet the specifications of your order, please contact us within <strong>5 business days</strong> of receiving your item. We will assess the issue and, if confirmed, offer a replacement or refund.</p>
    <h3>To initiate a claim, email us at:</h3>
    <p><a href="mailto:info@byclaudiadarlene.com">info@byclaudiadarlene.com</a></p>
    <p>Include the following:</p>
    <ul>
      <li>Your order number</li>
      <li>A detailed description of the issue</li>
      <li>Clear photos or video evidence of the fault</li>
    </ul>
    <p>Once your claim is reviewed and approved, we will provide return instructions. All returns must be in unworn, unaltered, and original condition, including packaging.</p>
  </section>

  <section>
    <h2>Important Notes</h2>
    <ul>
      <li>Due to hygiene standards, we cannot accept returns for items that have been worn, styled, or tampered with.</li>
      <li>Refunds or replacements are only issued for verified faults in craftsmanship or material — not for incorrect orders or a change of mind.</li>
    </ul>
  </section>

  <section>
    <p>We appreciate your understanding and are committed to ensuring your experience with Hair by Claudia Darlene is exceptional.</p>
  </section>
HTML;

require ROOT_PATH . '/includes/partials/policy-layout.php';
