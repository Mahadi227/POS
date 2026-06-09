// assets/js/pos/checkout.js & payments.js
// Handles Payment Modal and Checkout execution

const paymentModal = document.getElementById('checkoutModal');
const closePaymentModalBtn = document.getElementById('closePaymentModalBtn');
const methodCards = document.querySelectorAll('.method-btn');
let currentPaymentMethod = 'cash';

function openPaymentModal() {
    const totals = window.Cart.calculateTotals();
    document.getElementById('modalTotalDisplay').innerText = Number(totals.total).toLocaleString() + ' FCFA';
    
    // Reset inputs
    document.getElementById('amountTendered').value = '';
    document.getElementById('changeDisplay').innerText = '0 FCFA';
    
    paymentModal.style.display = 'flex';
}

closePaymentModalBtn.addEventListener('click', () => {
    paymentModal.style.display = 'none';
});

// Payment Method Selection
methodCards.forEach(card => {
    card.addEventListener('click', () => {
        methodCards.forEach(c => c.classList.remove('active'));
        card.classList.add('active');
        currentPaymentMethod = card.getAttribute('data-method');
        
        document.getElementById('cashDetails').style.display = currentPaymentMethod === 'cash' ? 'block' : 'none';
        document.getElementById('momoDetails').style.display = currentPaymentMethod === 'mobile_money' ? 'block' : 'none';
    });
});

// Cash Change Calculator
document.getElementById('amountTendered').addEventListener('input', (e) => {
    const tendered = parseFloat(e.target.value) || 0;
    const due = window.Cart.calculateTotals().total;
    const change = tendered - due;
    
    const display = document.getElementById('changeDisplay');
    if (change >= 0) {
        display.innerText = Number(change).toLocaleString() + ' FCFA';
        display.style.color = 'var(--success)';
    } else {
        display.innerText = "Montant insuffisant";
        display.style.color = 'var(--danger)';
    }
});

// Quick Cash Buttons
document.querySelectorAll('.quick-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const input = document.getElementById('amountTendered');
        const due = window.Cart.calculateTotals().total;
        
        if (btn.classList.contains('exact-btn') || btn.id === 'exactAmountBtn') {
            input.value = due;
        } else {
            const current = parseFloat(input.value) || 0;
            input.value = current + parseFloat(btn.getAttribute('data-val'));
        }
        
        input.dispatchEvent(new Event('input')); // Trigger change calculation
    });
});

// Confirm Payment
document.getElementById('confirmPaymentBtn').addEventListener('click', async () => {
    const totals = window.Cart.calculateTotals();
    const cart = window.Cart.getCart();
    
    if (currentPaymentMethod === 'cash') {
        const tendered = parseFloat(document.getElementById('amountTendered').value) || 0;
        if (tendered < totals.total) {
            alert("Le montant reçu est insuffisant.");
            return;
        }
    }

    const salePayload = {
        receipt_no: `REC-${Date.now()}`,
        total: totals.total,
        tax: totals.tax,
        discount: 0,
        payment_method: currentPaymentMethod,
        reference: document.getElementById('momoPhone') ? document.getElementById('momoPhone').value : '',
        items: cart.map(item => ({
            product_id: item.id,
            quantity: item.qty,
            unit_price: item.price
        }))
    };

    const printData = {
        cart: cart,
        totals: totals,
        payment_method: currentPaymentMethod
    };

    // Try online, fallback to offline
    try {
        const response = await fetch('../../api/v1/index.php?request=sales', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(salePayload)
        });
        const result = await response.json();
        
        if (result.status === 'success') {
            paymentModal.style.display = 'none';
            window.Receipt.printReceipt(result.receipt_no || salePayload.receipt_no, printData);
            window.Cart.clearCart();
        } else {
            alert("Erreur lors de l'enregistrement: " + result.message);
        }
    } catch (e) {
        // Offline Fallback
        console.warn("Offline! Saving to IndexedDB...", e);
        await window.OfflineSync.saveOfflineSale(salePayload);
        
        paymentModal.style.display = 'none';
        window.Receipt.printReceipt('OFFLINE-' + Date.now(), printData);
        window.Cart.clearCart();
        alert("Enregistré hors ligne. Synchronisation automatique à la reconnexion.");
    }
});

// Expose
window.Checkout = { openPaymentModal };
