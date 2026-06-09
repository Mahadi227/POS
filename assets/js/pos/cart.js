// assets/js/pos/cart.js
// Handles shopping cart state, taxes, and rendering

let cart = [];
const TAX_RATE = 0.18; // 18% VAT

function addItemToCart(product) {
    const existingItem = cart.find(item => String(item.id) === String(product.id));
    if (existingItem) {
        existingItem.qty += 1;
    } else {
        cart.push({ ...product, qty: 1 });
    }
    renderCart();
}

function updateItemQty(productId, newQty) {
    const item = cart.find(item => String(item.id) === String(productId));
    if (item) {
        item.qty = Math.max(1, newQty); // Ensure minimum 1
        renderCart();
    }
}

function removeItemFromCart(productId) {
    cart = cart.filter(item => String(item.id) !== String(productId));
    renderCart();
}

function clearCart() {
    cart = [];
    renderCart();
}

function calculateTotals() {
    let subtotal = 0;
    cart.forEach(item => {
        subtotal += parseFloat(item.price) * item.qty;
    });
    
    const tax = subtotal * TAX_RATE;
    const total = subtotal + tax;
    
    return { subtotal, tax, total };
}

function formatCurrency(amount) {
    return Number(amount).toLocaleString() + ' FCFA';
}

function updateDisplay(id, value) {
    const el = document.getElementById(id);
    if (el && el.innerText !== value) {
        el.innerText = value;
        el.classList.remove('pop-anim');
        void el.offsetWidth; // Trigger reflow
        el.classList.add('pop-anim');
    }
}

function renderCart() {
    let cartList = document.getElementById('cartItemsList');
    // For pos.html which uses id="cartItems" instead of "cartItemsList"
    if (!cartList) cartList = document.getElementById('cartItems'); 
    
    if (!cartList) return; // Failsafe
    
    const totals = calculateTotals();
    const checkoutBtn = document.getElementById('checkoutBtn');
    
    if (cart.length === 0) {
        cartList.innerHTML = `
            <div class="empty-cart-state" style="animation: fadeIn 0.3s ease;">
                <span class="material-icons-round">shopping_cart</span>
                <p>Le panier est vide</p>
            </div>
        `;
        if (checkoutBtn) checkoutBtn.disabled = true;
    } else {
        // Remove empty state if present
        const emptyState = cartList.querySelector('.empty-cart-state');
        if (emptyState) emptyState.remove();

        const existingItems = Array.from(cartList.querySelectorAll('.cart-item'));
        
        // Remove elements that are no longer in the cart
        existingItems.forEach(el => {
            const id = el.getAttribute('data-id');
            if (!cart.find(item => String(item.id) === String(id))) {
                el.style.transform = 'translateX(100%)';
                el.style.opacity = '0';
                setTimeout(() => el.remove(), 250);
            }
        });

        // Add or update elements
        cart.forEach(item => {
            let el = cartList.querySelector(`.cart-item[data-id="${item.id}"]`);
            const itemTotal = formatCurrency(item.price * item.qty);
            
            const contentHTML = `
                <div class="item-info">
                    <div class="name">${item.name}</div>
                    <div class="price">${formatCurrency(item.price)}</div>
                    <div class="item-qty">
                        <button class="qty-btn" onclick="Cart.updateItemQty('${item.id}', ${item.qty - 1})">-</button>
                        <span class="qty-display">${item.qty}</span>
                        <button class="qty-btn" onclick="Cart.updateItemQty('${item.id}', ${item.qty + 1})">+</button>
                    </div>
                </div>
                <div class="item-action" style="display:flex; flex-direction:column; align-items:flex-end;">
                    <div class="item-total" id="total-${item.id}">${itemTotal}</div>
                    <button class="icon-btn" onclick="Cart.removeItemFromCart('${item.id}')" style="color:var(--danger); margin-top:auto;">
                        <span class="material-icons-round">delete_outline</span>
                    </button>
                </div>
            `;

            if (!el) {
                el = document.createElement('div');
                el.className = 'cart-item';
                el.setAttribute('data-id', item.id);
                el.style.animation = 'slideInRight 0.3s ease-out forwards';
                el.innerHTML = contentHTML;
                cartList.appendChild(el);
            } else {
                // Update HTML only if there is a change to preserve focus/state
                if (el.innerHTML !== contentHTML) {
                    el.innerHTML = contentHTML;
                    const qtySpan = el.querySelector('.qty-display');
                    if (qtySpan) {
                        qtySpan.classList.remove('pop-anim');
                        void qtySpan.offsetWidth;
                        qtySpan.classList.add('pop-anim');
                    }
                }
            }
        });
        
        if (checkoutBtn) checkoutBtn.disabled = false;
    }
    
    // Support both IDs used in index.html and pos.html
    updateDisplay('cartSubtotal', formatCurrency(totals.subtotal));
    updateDisplay('subtotalDisplay', formatCurrency(totals.subtotal));
    
    updateDisplay('cartTax', formatCurrency(totals.tax));
    updateDisplay('taxDisplay', formatCurrency(totals.tax));
    
    updateDisplay('cartTotal', formatCurrency(totals.total));
    updateDisplay('totalDisplay', formatCurrency(totals.total));
    
    updateDisplay('btnTotal', formatCurrency(totals.total));
}

// Expose globally
window.Cart = {
    addItemToCart,
    updateItemQty,
    removeItemFromCart,
    clearCart,
    calculateTotals,
    getCart: () => cart
};
