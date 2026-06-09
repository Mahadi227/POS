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
    name: <?php echo json_encode($_SESSION['name'] ?? 'Caissier'); ?>,
    email: <?php echo json_encode($_SESSION['email'] ?? ''); ?>,
    role: <?php echo json_encode($_SESSION['role'] ?? ''); ?>,
    storeId: <?php echo json_encode(isset($_SESSION['store_id']) ? (int) $_SESSION['store_id'] : null); ?>
};
</script>
<script src="../../assets/js/cashier/cashier-api.js"></script>
