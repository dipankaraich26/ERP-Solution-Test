<?php
/**
 * Floating WhatsApp button for customer portal pages.
 * Requires: $company_settings (with 'phone'), $customer (with 'company_name', 'customer_name', 'customer_id')
 */
$waPhone = '';
if (isset($company_settings) && !empty($company_settings['phone'])) {
    $waPhone = preg_replace('/[^0-9]/', '', $company_settings['phone']);
    if (strlen($waPhone) === 10) $waPhone = '91' . $waPhone;
}
if ($waPhone):
    $waName = htmlspecialchars($customer['company_name'] ?: $customer['customer_name']);
    $waCustId = htmlspecialchars($customer['customer_id'] ?? '');
    $waMsg = urlencode("Hi, I am {$waName} ({$waCustId}). I need assistance.");
?>
<a href="https://wa.me/<?= $waPhone ?>?text=<?= $waMsg ?>"
   target="_blank"
   class="whatsapp-float"
   title="Chat with us on WhatsApp">
    <svg viewBox="0 0 32 32" width="32" height="32" fill="white">
        <path d="M16.004 0C7.164 0 .002 7.158.002 15.995c0 2.817.736 5.567 2.137 7.994L0 32l8.27-2.104a16.01 16.01 0 007.73 1.97h.005C24.844 31.866 32 24.708 32 15.87 32 7.158 24.844 0 16.004 0zm0 29.314a13.47 13.47 0 01-6.87-1.88l-.492-.293-5.104 1.337 1.363-4.975-.322-.51A13.4 13.4 0 012.55 15.995C2.55 8.572 8.577 2.548 16.004 2.548S29.454 8.572 29.454 15.995c0 7.424-6.026 13.319-13.45 13.319zm7.382-9.975c-.405-.203-2.396-1.182-2.768-1.317-.372-.135-.643-.203-.913.203-.27.405-1.048 1.317-1.285 1.588-.236.27-.473.304-.878.101-.405-.203-1.71-.63-3.258-2.01-1.204-1.074-2.017-2.4-2.253-2.806-.237-.405-.025-.624.178-.825.182-.182.405-.473.608-.71.203-.237.27-.405.405-.675.135-.27.068-.507-.034-.71-.101-.203-.913-2.2-1.25-3.012-.33-.791-.665-.684-.913-.697l-.778-.013c-.27 0-.71.101-1.082.507-.372.405-1.42 1.385-1.42 3.378 0 1.993 1.454 3.918 1.657 4.188.203.27 2.862 4.37 6.934 6.13.97.418 1.726.668 2.316.855.973.309 1.858.265 2.558.16.78-.116 2.396-.979 2.734-1.924.338-.945.338-1.755.236-1.924-.101-.168-.372-.27-.777-.473z"/>
    </svg>
</a>
<style>
    .whatsapp-float {
        position: fixed;
        bottom: 30px;
        right: 30px;
        width: 60px;
        height: 60px;
        background: #25D366;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 12px rgba(0,0,0,0.25);
        z-index: 999;
        transition: transform 0.3s, box-shadow 0.3s;
        text-decoration: none;
    }
    .whatsapp-float:hover {
        transform: scale(1.1);
        box-shadow: 0 6px 20px rgba(0,0,0,0.35);
    }
</style>
<?php endif; ?>
