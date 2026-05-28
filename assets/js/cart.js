// Global Add to Cart function for inline onclick handlers
window.addToCart = async (productId, quantity = 1, variantId = 0) => {
    try {
        const response = await fetch('manage_cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=update&product_id=${productId}&variant_id=${variantId}&quantity=${quantity}&incremental=1`
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Update cart count badge if it exists
            const cartCount = document.getElementById('cart-count');
            if (cartCount) {
                cartCount.innerText = data.total_items;
            }
            
            const cartBadges = document.querySelectorAll('.badge.bg-danger');
            cartBadges.forEach(badge => {
                badge.innerText = data.total_items;
            });
            
            // Show a nice toast or alert
            const alertBox = document.createElement('div');
            alertBox.className = 'alert alert-success position-fixed bottom-0 end-0 m-3 shadow-lg animate__animated animate__fadeInUp';
            alertBox.style.zIndex = '9999';
            alertBox.innerHTML = '<i class="bi bi-check-circle-fill me-2"></i> Product added to cart successfully!';
            document.body.appendChild(alertBox);
            
            setTimeout(() => {
                alertBox.classList.replace('animate__fadeInUp', 'animate__fadeOutDown');
                setTimeout(() => alertBox.remove(), 500);
            }, 3000);
        } else {
            alert(data.error || 'Failed to add product to cart');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    }
};

document.addEventListener('DOMContentLoaded', () => {
    // Add to Cart Logic (Product Cards)
    const addButtons = document.querySelectorAll('.add-to-cart');
    addButtons.forEach(button => {
        button.addEventListener('click', async (e) => {
            const productId = button.dataset.productId;
            const variantId = button.dataset.variantId || 0;
            // Look for a quantity input in the same card or nearby
            let quantity = 1;
            const cardBody = button.closest('.card-body');
            const qtyInput = cardBody ? cardBody.querySelector('.qty-select') : document.getElementById('qty_input');
            
            if (qtyInput) {
                quantity = parseInt(qtyInput.value) || 1;
            }
            
            if (quantity <= 0) {
                alert('Please select a valid quantity (1 or more).');
                return;
            }
            
            // Call the global function
            window.addToCart(productId, quantity, variantId);
            
            // Specific visual feedback for the button
            const originalText = button.innerHTML;
            button.classList.replace('btn-success', 'btn-outline-success');
            button.innerHTML = '<i class="bi bi-check2"></i> Added!';
            setTimeout(() => {
                button.classList.replace('btn-outline-success', 'btn-success');
                button.innerHTML = originalText;
            }, 2000);
        });
    });

    // Cart Page Logic (Quantity +/- and Remove)
    const updateCart = async (cartKey, quantity) => {
        try {
            const response = await fetch('manage_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update&cart_key=${cartKey}&quantity=${quantity}`
            });
            const data = await response.json();
            if (data.success) {
                window.location.reload();
            }
        } catch (error) {
            console.error('Error:', error);
        }
    };

    document.querySelectorAll('.qty-plus').forEach(btn => {
        btn.addEventListener('click', () => {
            const cartKey = btn.dataset.cartKey;
            const input = btn.parentElement.querySelector('.qty-input');
            const newQty = parseInt(input.value) + 1;
            updateCart(cartKey, newQty);
        });
    });

    document.querySelectorAll('.qty-minus').forEach(btn => {
        btn.addEventListener('click', () => {
            const cartKey = btn.dataset.cartKey;
            const input = btn.parentElement.querySelector('.qty-input');
            const newQty = Math.max(1, parseInt(input.value) - 1);
            updateCart(cartKey, newQty);
        });
    });

    document.querySelectorAll('.qty-input').forEach(input => {
        input.addEventListener('change', () => {
            const cartKey = input.dataset.cartKey;
            const newQty = Math.max(1, parseInt(input.value));
            updateCart(cartKey, newQty);
        });
    });

    document.querySelectorAll('.remove-item').forEach(btn => {
        btn.addEventListener('click', () => {
            const cartKey = btn.dataset.cartKey;
            updateCart(cartKey, 0); // 0 will trigger remove in manage_cart.php
        });
    });
});
