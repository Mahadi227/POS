<?php
/**
 * Scripts partagés module caissier — contexte session + API client.
 */
$userName = htmlspecialchars($_SESSION['name'] ?? 'Caissier', ENT_QUOTES, 'UTF-8');
$userRole = htmlspecialchars($_SESSION['role'] ?? 'Caissier', ENT_QUOTES, 'UTF-8');
?>
<script>
window.CASHIER_CONTEXT = {
    userId: <?php echo json_encode((int) ($_SESSION['user_id'] ?? 0)); ?>,
    name: <?php echo json_encode($_SESSION['name'] ?? 'Cashier'); ?>,
    email: <?php echo json_encode($_SESSION['email'] ?? ''); ?>,
    role: <?php echo json_encode($_SESSION['role'] ?? ''); ?>,
    storeId: <?php echo json_encode(isset($_SESSION['store_id']) ? (int) $_SESSION['store_id'] : null); ?>,
    lang: <?php echo json_encode($_SESSION['lang'] ?? (defined('ACTIVE_LANG') ? ACTIVE_LANG : 'en')); ?>
};
</script>
<script src="../../assets/js/cashier/cashier-api.js"></script>
<script src="../../assets/js/cashier/cashier-shift.js?v=1"></script>
<script src="../../assets/js/cashier/cashier-sync-heartbeat.js?v=1"></script>
<script src="../../assets/js/app-theme.js?v=2"></script>
