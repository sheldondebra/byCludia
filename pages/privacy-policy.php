<?php
declare(strict_types=1);

$pageTitle = 'Privacy Policy – Hair by Claudia Darlene';
$pageDescription = 'How Hair by Claudia Darlene collects, uses, and protects your personal information.';
$canonical = url('privacy');
$policyHeading = 'Privacy Policy';
$policyIntro = 'This privacy policy outlines how Hair by Claudia Darlene uses and protects any information that you provide when using this website.';

$policyBody = <<<'HTML'
  <section>
    <p>Hair by Claudia Darlene is committed to ensuring your privacy is protected. Should we ask you to provide certain information by which you can be identified, it will only be used in accordance with this statement.</p>
    <p>We may update this policy periodically. Please check this page occasionally to ensure that you are happy with any changes.</p>
    <p><strong>Effective Date:</strong> 05/06/2025</p>
  </section>

  <section>
    <h2>What We Collect</h2>
    <p>We may collect the following information:</p>
    <ul>
      <li>Name and job title</li>
      <li>Contact information including email address</li>
      <li>Demographic information such as postcode, preferences, and interests</li>
      <li>Other information relevant to customer surveys and/or promotional offers</li>
    </ul>
  </section>

  <section>
    <h2>What We Do with the Information</h2>
    <p>We require this information to understand your needs and provide a better service, specifically for:</p>
    <ul>
      <li>Internal record keeping</li>
      <li>Improving our products and services</li>
      <li>Sending promotional emails about new products, special offers, or other updates</li>
      <li>Contacting you for market research via email, phone, fax, or mail</li>
      <li>Customizing the website according to your interests</li>
    </ul>
  </section>

  <section>
    <h2>Security</h2>
    <p>We are committed to ensuring your information is secure. To prevent unauthorized access or disclosure, we implement suitable physical, electronic, and managerial procedures to safeguard and secure information collected online.</p>
  </section>

  <section>
    <h2>How We Use Cookies</h2>
    <p>A cookie is a small file placed on your device’s hard drive with your permission. It helps analyze web traffic or remembers your preferences.</p>
    <p>We use traffic log cookies to identify which pages are being visited. This helps us analyze data to improve the site and tailor it to customer needs. All cookie data is used for statistical analysis and is then removed from the system.</p>
    <p>Cookies:</p>
    <ul>
      <li>Help us improve your browsing experience</li>
      <li>Do not give us access to your computer or personal data beyond what you voluntarily share</li>
    </ul>
    <p>You can choose to accept or decline cookies. Most browsers accept them automatically, but you can adjust your browser settings to decline if preferred — this may limit site functionality.</p>
  </section>

  <section>
    <h2>Links to Other Websites</h2>
    <p>Our website may contain links to external sites. Once you leave our website, we no longer have control over the privacy or security of your information. Please read the privacy policy of any external site you visit.</p>
  </section>

  <section>
    <h2>Controlling Your Personal Information</h2>
    <ul>
      <li>Opt-out of direct marketing by checking the relevant box on website forms</li>
      <li>Change your preferences at any time by emailing us at <a href="mailto:info@byclaudiadarlene.com">info@byclaudiadarlene.com</a></li>
      <li>We do not sell, distribute, or lease your personal information to third parties without your permission or unless required by law</li>
      <li>We may use your information to send you promotional material from third parties if you opt-in</li>
    </ul>
    <p>You may request a copy of your personal data under the Data Protection Act 1998 (a small fee may apply). If you believe any information we hold about you is incorrect or incomplete, please contact us at <a href="mailto:info@byclaudiadarlene.com">info@byclaudiadarlene.com</a> and we will promptly correct it.</p>
  </section>
HTML;

require ROOT_PATH . '/includes/partials/policy-layout.php';
