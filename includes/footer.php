  <?php $p = $_SERVER['SCRIPT_NAME'] ?? ''; $showFooter = (substr($p, -10) === '/index.php' && strpos($p, '/admin/') === false); ?>
  <?php if ($showFooter): ?>
  <footer class="site-footer">
      <div class="container footer-grid">
        <details class="f-acc" open>
          <summary class="caps">About Us</summary>
          <div class="content">
            <p style="margin:0">At FpB Network and Power Solutions Services, we specialize in providing high-quality Automatic Voltage Regulators (AVRs), Uninterruptible Power Supply (UPS) systems, Solar Power solutions, and advanced LED lighting technologies that enhance your everyday operations.</p>
          </div>
        </details>
        <details class="f-acc" open>
          <summary class="caps">Links</summary>
          <div class="content">
            <ul class="list">
              <li><a href="#" id="open-terms" role="button">Fnpss Terms &amp; Conditions</a></li>
            </ul>
          </div>
        </details>
        <details class="f-acc support" open>
          <summary class="caps">Support</summary>
          <div class="content">
            <ul class="list">
              <li><span>Shipping &amp; Delivery</span></li>
              <li><span>Warranty Only</span></li>
              <li><span>Privacy Policy</span></li>
            </ul>
          </div>
        </details>
        <details class="f-acc" open>
          <summary class="caps">Services</summary>
          <div class="content">
            <ul class="list" style="list-style:disc;padding-left:18px">
              <li>Elevator and Escalator Parts &amp; Services</li>
              <li>Repair &amp; Servicing</li>
              <li>Design &amp; Estimation</li>
              <li>After Sales Services</li>
            </ul>
          </div>
        </details>
        <details class="f-acc" open>
          <summary class="caps">Contact</summary>
          <div class="content">
            <ul class="list">
              <li><strong>Address:</strong> <a href="https://www.google.com/maps/dir//14.1966131,121.1242696/@14.2049282,121.0931057,14z?entry=ttu&g_ep=EgoyMDI1MDkyMy4wIKXMDSoASAFQAw%3D%3D" target="_blank" rel="noopener">Block 23 Lot 7 Laguna Buenavista Executive Homes, Barandal, Calamba, Philippines</a></li>
              <li><strong>Email:</strong> admin@fnpss.com</li>
              <li><strong>Facebook:</strong> <a href="https://www.facebook.com/profile.php?id=100064105382601" target="_blank" rel="noopener">Fpb Network and Power Solutions Services</a></li>
              <li><strong>Phone:</strong> 0917 836 5017</li>
            </ul>
          </div>
        </details>
      </div>
    <div class="container" style="padding-top:8px;border-top:1px solid rgba(255,255,255,.25);margin-top:12px;text-align:center">
      <p style="margin:8px 0">&copy; <?= date('Y'); ?> AVR Shop. All rights reserved.</p>
    </div>
  </footer>
  <!-- Terms & Conditions Modal (simple, responsive) -->
  <div id="terms-modal" aria-hidden="true" style="display:none;position:fixed;inset:0;z-index:1000;background:rgba(0,0,0,.5);backdrop-filter:saturate(120%) blur(2px);">
    <div role="dialog" aria-modal="true" aria-labelledby="terms-title" style="max-width:min(920px,96vw);max-height:92vh;overflow:auto;margin:4vh auto;background:#ffffff;color:#1f2937;border-radius:12px;box-shadow:0 20px 60px rgba(0,0,0,.25);border:1px solid #cfe0f5">
      <div style="position:sticky;top:0;background:#0b4f8f;color:#fff;padding:12px 16px;border-radius:12px 12px 0 0;display:flex;justify-content:space-between;align-items:center;gap:10px">
        <strong id="terms-title" style="font-size:16px">FNPSS Terms and Conditions</strong>
        <button type="button" id="terms-close" aria-label="Close" style="background:#0a3f72;color:#fff;border:1px solid rgba(255,255,255,.25);padding:6px 10px;border-radius:8px;cursor:pointer">Close</button>
      </div>
      <div style="padding:14px 16px 18px;">
        <p style="margin:0 0 8px">FNPSS (Fpb Network and Power Solutions Services) offers high-quality products like AVRs, UPS, Solar solutions, and LED lighting, plus professional services such as repair, installation, and after-sales support.</p>
        <ol style="margin:0;padding-left:18px;line-height:1.75">
          <li><strong>Account and Password</strong>
            <ul style="margin:6px 0 10px 16px;list-style:disc">
              <li>You create your password once during registration. For your security, it cannot be changed later.</li>
              <li>Keep your login details confidential and use your own account only.</li>
            </ul>
          </li>
          <li><strong>How to Order</strong>
            <ul style="margin:6px 0 10px 16px;list-style:disc">
              <li>Browse products, open a product page, and click “Add to Cart” or “Order Now”.</li>
              <li>Open Cart and proceed to Checkout to confirm delivery details.</li>
              <li>The earliest possible delivery date is shown on the Checkout page and depends on your address and current schedule.</li>
            </ul>
          </li>
          <li><strong>Chatbot and Contacting the Seller</strong>
            <ul style="margin:6px 0 10px 16px;list-style:disc">
              <li>Chatbot: Click the chat button to open the assistant. Type <code>@seller</code> followed by your message to contact the seller.</li>
              <li>Other channels: Email admin@fnpss.com or message our Facebook page “Fpb Network and Power Solutions Services”. You can also call 0917 836 5017.</li>
            </ul>
          </li>
          <li><strong>Payment</strong>
            <ul style="margin:6px 0 10px 16px;list-style:disc">
              <li>Default payment method is Cash on Delivery (COD). For other payment arrangements, contact us first.</li>
            </ul>
          </li>
          <li><strong>Coupons and Discounts</strong>
            <ul style="margin:6px 0 10px 16px;list-style:disc">
              <li>Automatic promos: Buy 4 pieces to get 5% OFF; buy 5+ pieces for FREE shipping (where applicable).</li>
              <li>If you have an FNPSS coupon, share the code via the chatbot or email so we can validate and apply it to your order.</li>
            </ul>
          </li>
          <li><strong>Services and Scheduling</strong>
            <ul style="margin:6px 0 10px 16px;list-style:disc">
              <li>Service requests follow the agreed schedule. Busy dates may shift the earliest delivery or service date; the Checkout page shows the soonest available date.</li>
            </ul>
          </li>
          <li><strong>Warranty Terms &amp; Statuses</strong>
            <ul style="margin:6px 0 10px 16px;list-style:disc">
              <li><strong>Availability:</strong> Warranty information becomes available after your order status is <em>Order Received</em> (Delivered). Open My Orders → View Warranty &amp; Receipt.</li>
              <li><strong>How to claim:</strong> Present this digital receipt and the product with its serial number at our service center, or contact support via email. You can also message the seller by typing <code>@seller</code> in the chatbot.</li>
              <li><strong>Status definitions:</strong>
                <ul style="margin:6px 0 6px 18px;list-style:circle">
                  <li><strong>Active</strong>: Warranty is valid and unused within the coverage period.</li>
                  <li><strong>Claimed</strong>: You initiated a warranty service request; it’s currently in progress.</li>
                  <li><strong>Completed</strong>: Warranty service was finished successfully.</li>
                  <li><strong>Expired</strong>: Warranty coverage period has ended.</li>
                </ul>
              </li>
            </ul>
          </li>
        </ol>
        <p style="margin:12px 0 0;font-size:13px;color:#475569">By continuing, you acknowledge these Terms and agree to shop under these rules.</p>
      </div>
    </div>
  </div>
<?php endif; ?>
  <script>
    (function(){
      function syncFooter(){
        var mobile = window.matchMedia('(max-width: 900px)').matches;
        var acc = document.querySelectorAll('.f-acc'); if(!acc.length) return;
        acc.forEach(function(d){ mobile ? d.removeAttribute('open') : d.setAttribute('open',''); });
      }
      window.addEventListener('load', syncFooter);
      window.addEventListener('resize', syncFooter);
    })();
    // Terms modal open/close
    (function(){
      function qs(id){ return document.getElementById(id); }
      function on(el, ev, fn){ if(el) el.addEventListener(ev, fn, false); }
      function open(){ var m=qs('terms-modal'); if(!m) return; m.style.display='block'; document.body.style.overflow='hidden'; }
      function close(){ var m=qs('terms-modal'); if(!m) return; m.style.display='none'; document.body.style.overflow=''; }
      on(window, 'load', function(){
        var openBtn = document.getElementById('open-terms'); var modal = qs('terms-modal'); var closeBtn = qs('terms-close');
        on(openBtn,'click', function(e){ e.preventDefault(); open(); });
        on(closeBtn,'click', function(){ close(); });
        on(modal,'click', function(e){ if(e.target===modal){ close(); } });
        on(document,'keydown', function(e){ if(e.key==='Escape'){ close(); } });
      });
    })();
  </script>
  <script src="<?= BASE_URL ?>/assets/js/script.js?v=20251002"></script>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/chatbot.css?v=20250923">
  <script src="<?= BASE_URL ?>/assets/js/chatbot.js?v=20250923"></script>
</body>
</html>
