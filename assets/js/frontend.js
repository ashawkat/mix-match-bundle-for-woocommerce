/**
 * Mix & Match Bundle - Frontend JavaScript (Vanilla JS)
 */

(function() {
    'use strict';

    /**
     * Dialog Utility (SweetAlert-style)
     */
    const MMBDialog = {
        show(options) {
            const {
                type = 'info',
                title = '',
                message = '',
                confirmText = 'OK',
                cancelText = 'Cancel',
                onConfirm = null,
                onCancel = null,
                showCancel = false
            } = options;
            
            // Create overlay
            const overlay = document.createElement('div');
            overlay.className = 'mmb-dialog-overlay';
            
            // Icon mapping
            const icons = {
                success: '✓',
                error: '✕',
                warning: '⚠',
                info: 'ℹ'
            };
            
            // Create dialog
            const dialog = document.createElement('div');
            dialog.className = 'mmb-dialog';
            dialog.innerHTML = `
                <div class="mmb-dialog-icon ${type}">
                    ${icons[type] || icons.info}
                </div>
                <h3>${title}</h3>
                <p>${message}</p>
                <div class="mmb-dialog-buttons">
                    <button class="mmb-dialog-button primary" data-action="confirm">
                        ${confirmText}
                    </button>
                    ${showCancel ? `
                        <button class="mmb-dialog-button secondary" data-action="cancel">
                            ${cancelText}
                        </button>
                    ` : ''}
                </div>
            `;
            
            overlay.appendChild(dialog);
            document.body.appendChild(overlay);
            
            // Handle button clicks
            const confirmBtn = dialog.querySelector('[data-action="confirm"]');
            const cancelBtn = dialog.querySelector('[data-action="cancel"]');
            
            const close = () => {
                overlay.style.animation = 'mmb-fadeOut 0.2s ease forwards';
                setTimeout(() => {
                    if (document.body.contains(overlay)) {
                        document.body.removeChild(overlay);
                    }
                }, 200);
            };
            
            confirmBtn.addEventListener('click', () => {
                close();
                if (onConfirm) onConfirm();
            });
            
            if (cancelBtn) {
                cancelBtn.addEventListener('click', () => {
                    close();
                    if (onCancel) onCancel();
                });
            }
            
            // Close on overlay click
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) {
                    close();
                    if (onCancel) onCancel();
                }
            });
            
            // Close on Escape key
            const escHandler = (e) => {
                if (e.key === 'Escape') {
                    close();
                    document.removeEventListener('keydown', escHandler);
                }
            };
            document.addEventListener('keydown', escHandler);
        },
        
        success(title, message, onConfirm) {
            this.show({
                type: 'success',
                title: title,
                message: message,
                confirmText: 'OK',
                onConfirm: onConfirm
            });
        },
        
        error(title, message, onConfirm) {
            this.show({
                type: 'error',
                title: title,
                message: message,
                confirmText: 'OK',
                onConfirm: onConfirm
            });
        },
        
        confirm(title, message, onConfirm, onCancel) {
            this.show({
                type: 'warning',
                title: title,
                message: message,
                confirmText: 'Yes',
                cancelText: 'No',
                showCancel: true,
                onConfirm: onConfirm,
                onCancel: onCancel
            });
        }
    };

    const MMB_Frontend = {
        
        // State
        bundleData: null,
        cartBehavior: 'sidecart',
        primaryColor: null,
        
        // DOM elements
        wrapper: null,
        productSelects: null,
        productQtys: null,
        variationDropdowns: null,
        bundleItemsContainer: null,
        itemCount: null,
        subtotalEl: null,
        discountEl: null,
        totalEl: null,
        addToCartBtn: null,
        // Mobile
        mobileCart: null,
        mobileItems: null,
        mobileTotal: null,
        mobileDiscount: null,
        mobileAddBtn: null,
        // Tiers
        tierItems: null,
        mobileDiscountBadge: null,
        
        /**
         * Initialize
         */
        init() {
            this.cacheDom();
            if (!this.wrapper) {
                return;
            }
            this.bindEvents();
            this.preventWooCommerceRedirect();
            this.cleanupWooAddedToCartLinks();
        },
        
        /**
         * Cache DOM elements
         */
        cacheDom() {
            this.wrapper = document.querySelector('.mmb-bundle-wrapper');
            if (!this.wrapper) {
                console.error('MMB: Wrapper not found!');
                return;
            }
            
            this.productSelects = this.wrapper.querySelectorAll('.mmb-product-select');
            this.productQtys = this.wrapper.querySelectorAll('.mmb-product-qty-input');
            this.variationDropdowns = this.wrapper.querySelectorAll('.mmb-variation-dropdown');
            this.bundleItemsContainer = this.wrapper.querySelector('#mmb-bundle-items');
            if (!this.bundleItemsContainer) {
                console.error('MMB: bundleItemsContainer (#mmb-bundle-items) NOT FOUND!');
            }
            
            this.itemCount = this.wrapper.querySelector('.mmb-item-count');
            this.subtotalEl = this.wrapper.querySelector('#mmb-subtotal');
            this.discountEl = this.wrapper.querySelector('#mmb-discount');
            this.totalEl = this.wrapper.querySelector('#mmb-total');
            this.addToCartBtn = this.wrapper.querySelector('#mmb-add-to-cart');
            
            // Mobile sticky cart
            this.mobileCart = this.wrapper.querySelector('.mmb-mobile-sticky-cart');
            if (this.mobileCart) {
                this.mobileItems = this.mobileCart.querySelector('.mmb-mobile-sticky-items');
                this.mobileTotal = this.mobileCart.querySelector('.mmb-mobile-sticky-total');
                this.mobileDiscount = this.mobileCart.querySelector('.mmb-mobile-discount-amount');
                this.mobileAddBtn = this.mobileCart.querySelector('#mmb-mobile-add-cart');
            }
            
            // Tier elements
            this.tierItems = this.wrapper.querySelectorAll('.mmb-tier-item');
            this.mobileDiscountBadge = this.wrapper.querySelector('#mmb-mobile-discount-badge');
            
            // Get settings
            this.cartBehavior = this.wrapper.dataset.cartBehavior || 'sidecart';

            this.storeOriginalButtonLabels();
            
            // Apply custom colors
            this.applyCustomColors();
        },
        
        /**
         * Apply custom colors
         */
        applyCustomColors() {
            this.primaryColor = this.wrapper.dataset.primaryColor || '#4caf50';
            this.accentColor = this.wrapper.dataset.accentColor || '#45a049';
            this.hoverBgColor = this.wrapper.dataset.hoverBgColor || '#388e3c';
            this.hoverAccentColor = this.wrapper.dataset.hoverAccentColor || '#2e7d32';
            this.buttonTextColor = this.wrapper.dataset.buttonTextColor || '#ffffff';
            
            // Set CSS custom properties on the wrapper - these will cascade to all children
            this.wrapper.style.setProperty('--mmb-primary-color', this.primaryColor);
            this.wrapper.style.setProperty('--mmb-accent-color', this.accentColor);
            this.wrapper.style.setProperty('--mmb-hover-bg-color', this.hoverBgColor);
            this.wrapper.style.setProperty('--mmb-hover-accent-color', this.hoverAccentColor);
            this.wrapper.style.setProperty('--mmb-button-text-color', this.buttonTextColor);
        },
        
        /**
         * Prevent WooCommerce default redirect
         */
        preventWooCommerceRedirect() {
            if (this.cartBehavior === 'sidecart') {
                document.body.addEventListener('added_to_cart', (e) => {
                    if (typeof wc_add_to_cart_params !== 'undefined') {
                        wc_add_to_cart_params.cart_redirect_after_add = 'no';
                    }
                });
            }
        },
        
        /**
         * Bind event listeners
         */
        bindEvents() {
            // Product checkboxes
            this.productSelects.forEach(checkbox => {
                checkbox.addEventListener('change', () => this.updateBundle());
            });
            
            // Quantity inputs
            this.productQtys.forEach(input => {
                input.addEventListener('change', () => this.updateBundle());
                input.addEventListener('input', () => this.updateBundle());
            });
            
            // Quantity +/- buttons
            const qtyMinusBtns = this.wrapper.querySelectorAll('.mmb-qty-minus');
            const qtyPlusBtns = this.wrapper.querySelectorAll('.mmb-qty-plus');
            
            qtyMinusBtns.forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const productId = btn.dataset.productId;
                    const input = this.wrapper.querySelector(`.mmb-product-qty-input[data-product-id="${productId}"]`);
                    if (input && !input.disabled) {
                        const currentValue = parseInt(input.value) || 0;
                        const minValue = parseInt(input.min) || 0;
                        if (currentValue > minValue) {
                            input.value = currentValue - 1;
                            input.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                    }
                });
            });
            
            qtyPlusBtns.forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const productId = btn.dataset.productId;
                    const input = this.wrapper.querySelector(`.mmb-product-qty-input[data-product-id="${productId}"]`);
                    if (input && !input.disabled) {
                        const currentValue = parseInt(input.value) || 0;
                        const maxValue = parseInt(input.max) || 10;
                        if (currentValue < maxValue) {
                            input.value = currentValue + 1;
                            input.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                    }
                });
            });
            
            // Variation dropdowns
            this.variationDropdowns.forEach(dropdown => {
                dropdown.addEventListener('change', (e) => this.handleVariationChange(e.target));
            });
            
            // Add to cart buttons
            if (this.addToCartBtn) {
                this.addToCartBtn.addEventListener('click', () => this.addBundleToCart());
            }
            if (this.mobileAddBtn) {
                this.mobileAddBtn.addEventListener('click', () => this.addBundleToCart());
            }
        },

        /**
         * Capture original text for Woo add-to-cart buttons to restore later
         */
        storeOriginalButtonLabels() {
            if (!this.wrapper) {
                return;
            }
            const buttons = this.wrapper.querySelectorAll('.add_to_cart_button');
            buttons.forEach((btn) => {
                if (!btn.dataset.mmbOriginalText) {
                    btn.dataset.mmbOriginalText = btn.textContent.trim();
                }
            });
        },

        /**
         * Remove WooCommerce "View cart" links injected after AJAX add-to-cart
         */
        cleanupWooAddedToCartLinks() {
            if (!this.wrapper) {
                return;
            }
            const addedLinks = this.wrapper.querySelectorAll('a.added_to_cart');
            addedLinks.forEach(link => link.remove());

            const buttons = this.wrapper.querySelectorAll('.add_to_cart_button.added');
            buttons.forEach(button => {
                button.classList.remove('added');
                if (button.dataset.mmbOriginalText) {
                    button.textContent = button.dataset.mmbOriginalText;
                }
            });
        },

        
        /**
         * Handle variation dropdown change
         */
        handleVariationChange(dropdown) {
            const card = dropdown.closest('.mmb-product-card');
            const select = card.querySelector('.mmb-product-select');
            const qtyInput = card.querySelector('.mmb-product-qty-input');
            const qtyMinusBtn = card.querySelector('.mmb-qty-minus');
            const qtyPlusBtn = card.querySelector('.mmb-qty-plus');
            const priceDisplay = card.querySelector('.mmb-product-price');
            const variationId = dropdown.value;
            const selectedOption = dropdown.options[dropdown.selectedIndex];
            
            if (variationId) {
                // Enable checkbox or quantity input
                if (select) select.disabled = false;
                if (qtyInput) qtyInput.disabled = false;
                if (qtyMinusBtn) qtyMinusBtn.disabled = false;
                if (qtyPlusBtn) qtyPlusBtn.disabled = false;
                
                // Update price display
                const price = selectedOption.dataset.price;
                if (price && priceDisplay) {
                    priceDisplay.innerHTML = this.formatPrice(price);
                }
                
                // Store variation data
                card.dataset.variationId = variationId;
                card.dataset.variationPrice = price;
            } else {
                // Disable and uncheck
                if (select) {
                    select.disabled = true;
                    select.checked = false;
                }
                if (qtyInput) {
                    qtyInput.disabled = true;
                    qtyInput.value = 0;
                }
                if (qtyMinusBtn) qtyMinusBtn.disabled = true;
                if (qtyPlusBtn) qtyPlusBtn.disabled = true;
                delete card.dataset.variationId;
                delete card.dataset.variationPrice;
            }
            
            this.updateBundle();
        },
        
        /**
         * Update bundle calculations
         */
        updateBundle() {
            const selectedProducts = this.getSelectedProducts();
            const itemCount = selectedProducts.length;
            
            if (itemCount === 0) {
                this.resetBundleDisplay();
                return;
            }
            
            // Calculate totals - account for quantities
            let subtotal = 0;
            selectedProducts.forEach(product => {
                // Each product in the array represents one item (quantity is represented by multiple entries)
                subtotal += parseFloat(product.price || 0);
            });
            
            // Get discount data from backend
            this.calculateBundleDiscount(selectedProducts, subtotal, itemCount);
        },
        
        /**
         * Calculate bundle discount via AJAX
         */
        async calculateBundleDiscount(products, subtotal, itemCount) {
            const bundleId = this.wrapper.dataset.bundleId;
            const productIds = products.map(p => ({
                product_id: p.product_id,
                variation_id: p.variation_id || 0
            }));
            
            try {
                const response = await fetch(mmb_frontend.ajaxurl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'mmb_update_bundle_items',
                        nonce: mmb_frontend.nonce,
                        bundle_id: bundleId,
                        product_ids: JSON.stringify(productIds)
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    this.updateBundleDisplay(data.data, itemCount);
                    this.updateTierDisplay(itemCount, data.data.discount_percentage);
                }
            } catch (error) {
                console.error('MMB: Error calculating bundle discount:', error);
            }
        },
        
        /**
         * Update bundle display
         */
        updateBundleDisplay(data, itemCount) {
            // Store bundle data
            this.bundleData = data;
            this.wrapper.dataset.bundleData = JSON.stringify(data);
            
            // Update item count
            if (this.itemCount) {
                this.itemCount.textContent = itemCount;
            }
            if (this.mobileItems) {
                this.mobileItems.textContent = `${itemCount} ${itemCount === 1 ? 'item' : 'items'}`;
            }
            
            // Populate product list in sidebar
            if (this.bundleItemsContainer && data.products && data.products.length > 0) {
                // Use document fragment for better performance
                const fragment = document.createDocumentFragment();
                
                data.products.forEach((product, index) => {
                    
                    const itemDiv = document.createElement('div');
                    itemDiv.className = 'mmb-bundle-item';
                    
                    const productName = product.name || 'Product';
                    const productPrice = parseFloat(product.price || 0);
                    const quantity = parseInt(product.quantity || 1);
                    
                    // Calculate item total (price * quantity)
                    const itemTotal = productPrice * quantity;
                    
                    let displayName = productName;
                    if (quantity > 1) {
                        displayName += ` × ${quantity}`;
                    }
                    if (product.variation_name) {
                        displayName += ` (${product.variation_name})`;
                    }
                    
                    itemDiv.innerHTML = `
                        <span class="mmb-item-name">${displayName}</span>
                        <span class="mmb-item-price">${this.formatPrice(itemTotal)}</span>
                    `;
                    
                    fragment.appendChild(itemDiv);
                });
                
                // Clear and append all at once
                this.bundleItemsContainer.innerHTML = '';
                this.bundleItemsContainer.appendChild(fragment);
            }
            
            // Format and update prices
            const subtotal = parseFloat(data.subtotal || 0);
            const discount = parseFloat(data.discount_amount || 0);
            let total = parseFloat(data.total_price || 0);
            
            // Ensure totals are valid numbers
            const validSubtotal = isNaN(subtotal) ? 0 : subtotal;
            const validDiscount = isNaN(discount) ? 0 : discount;
            
            // If total is 0 or invalid but we have subtotal and discount, calculate it
            if ((isNaN(total) || total <= 0) && validSubtotal > 0) {
                total = validSubtotal - validDiscount;
            }
            
            const validTotal = isNaN(total) ? 0 : Math.max(0, total); // Ensure total is never negative
            
            if (this.subtotalEl) this.subtotalEl.textContent = this.formatPrice(validSubtotal);
            if (this.discountEl) this.discountEl.textContent = '-' + this.formatPrice(validDiscount);
            if (this.totalEl) {
                this.totalEl.textContent = this.formatPrice(validTotal);
                // Also update inner text if it's a formatted price element
                if (this.totalEl.innerHTML) {
                    this.totalEl.innerHTML = this.formatPrice(validTotal);
                }
            }
            
            if (this.mobileTotal) this.mobileTotal.textContent = this.formatPrice(validTotal);
            if (this.mobileDiscount) this.mobileDiscount.textContent = this.formatPrice(validDiscount);
            
            // Enable add to cart button
            if (this.addToCartBtn) this.addToCartBtn.disabled = false;
            if (this.mobileAddBtn) this.mobileAddBtn.disabled = false;
            
            // Update mobile sticky cart
            this.updateMobileStickyCart();
        },
        
        /**
         * Update tier display
         */
        updateTierDisplay(itemCount, discountPercentage) {
            // Get all tiers sorted by quantity
            const allTiers = Array.from(this.tierItems).map(item => ({
                quantity: parseInt(item.dataset.quantity),
                discount: parseFloat(item.dataset.discount),
                element: item
            })).sort((a, b) => a.quantity - b.quantity);
            
            // Update desktop tiers - batch DOM updates
            const tierUpdates = [];
            this.tierItems.forEach(tierItem => {
                const tierQty = parseInt(tierItem.dataset.quantity);
                const tierDiscount = parseFloat(tierItem.dataset.discount);
                
                let classes = ['mmb-tier-item'];
                if (itemCount >= tierQty) {
                    classes.push('unlocked');
                    if (discountPercentage === tierDiscount) {
                        classes.push('active');
                    }
                }
                
                // Only update if classes changed
                const currentClasses = Array.from(tierItem.classList);
                if (currentClasses.sort().join('') !== classes.sort().join('')) {
                    tierUpdates.push({ element: tierItem, classes });
                }
            });
            
            // Apply all class updates at once
            tierUpdates.forEach(update => {
                update.element.className = update.classes.join(' ');
            });
            
            // Find next tier to unlock
            const nextTier = allTiers.find(tier => itemCount < tier.quantity);
            
            // Update mobile discount badge
            if (this.mobileDiscountBadge) {
                if (discountPercentage > 0) {
                    this.mobileDiscountBadge.classList.add('active');
                    const emoji = discountPercentage >= 20 ? '🎉' : discountPercentage >= 15 ? '🎁' : '✨';
                    
                    // Calculate average price per item
                    const currentSubtotal = this.bundleData ? parseFloat(this.bundleData.subtotal || 0) : 0;
                    const avgPricePerItem = itemCount > 0 ? currentSubtotal / itemCount : 0;
                    
                    // Show progress to next tier if available
                    let badgeText = `Save ${discountPercentage}% on this bundle!`;
                    if (nextTier) {
                        const itemsNeeded = nextTier.quantity - itemCount;
                        const estimatedAmountNeeded = itemsNeeded * avgPricePerItem;
                        badgeText = `Add ${itemsNeeded} more item${itemsNeeded > 1 ? 's' : ''} to get ${nextTier.discount}% off!`;
                    }
                    
                    this.mobileDiscountBadge.innerHTML = `
                        <span class="mmb-mobile-badge-icon">${emoji}</span>
                        <span class="mmb-mobile-badge-text">${badgeText}</span>
                    `;
                    if (this.primaryColor) {
                        this.mobileDiscountBadge.style.setProperty('background', `linear-gradient(135deg, ${this.primaryColor} 0%, ${this.primaryColor} 100%)`, 'important');
                        this.mobileDiscountBadge.style.setProperty('color', '#fff', 'important');
                    }
                } else {
                    // No discount yet - show how many items needed for first tier
                    this.mobileDiscountBadge.classList.remove('active');
                    
                    if (nextTier) {
                        const itemsNeeded = nextTier.quantity - itemCount;
                        const currentSubtotal = this.bundleData ? parseFloat(this.bundleData.subtotal || 0) : 0;
                        const avgPricePerItem = itemCount > 0 ? currentSubtotal / itemCount : 50; // Default estimate if no items
                        const estimatedAmountNeeded = itemsNeeded * avgPricePerItem;
                        
                        this.mobileDiscountBadge.innerHTML = `
                            <span class="mmb-mobile-badge-icon">🎯</span>
                            <span class="mmb-mobile-badge-text">Add ${itemsNeeded} more item${itemsNeeded > 1 ? 's' : ''} to get ${nextTier.discount}% OFF</span>
                        `;
                    } else {
                        this.mobileDiscountBadge.innerHTML = `
                            <span class="mmb-mobile-badge-icon">🎯</span>
                            <span class="mmb-mobile-badge-text">Select items to see your discount</span>
                        `;
                    }
                }
            }
        },
        
        /**
         * Update mobile sticky cart
         */
        updateMobileStickyCart() {
            if (!this.mobileCart) return;
            
            const hasItems = this.bundleData && this.bundleData.item_count > 0;
            this.mobileCart.classList.toggle('mmb-has-items', hasItems);
        },
        
        /**
         * Reset bundle display
         */
        resetBundleDisplay() {
            this.bundleData = null;
            delete this.wrapper.dataset.bundleData;
            
            // Clear product list
            if (this.bundleItemsContainer) {
                this.bundleItemsContainer.innerHTML = '<p style="color: #999; font-size: 14px; text-align: center; padding: 20px 0;">Select products to get started</p>';
            }
            
            if (this.itemCount) this.itemCount.textContent = '0';
            if (this.subtotalEl) this.subtotalEl.textContent = this.formatPrice(0);
            if (this.discountEl) this.discountEl.textContent = this.formatPrice(0);
            if (this.totalEl) {
                this.totalEl.textContent = this.formatPrice(0);
                // Also update inner text if it's a formatted price element
                if (this.totalEl.innerHTML) {
                    this.totalEl.innerHTML = this.formatPrice(0);
                }
            }
            
            if (this.addToCartBtn) this.addToCartBtn.disabled = true;
            if (this.mobileAddBtn) this.mobileAddBtn.disabled = true;
            
            if (this.mobileItems) this.mobileItems.textContent = '0 items';
            if (this.mobileTotal) this.mobileTotal.textContent = this.formatPrice(0);
            if (this.mobileDiscount) this.mobileDiscount.textContent = this.formatPrice(0);
            
            // Reset tiers
            this.tierItems.forEach(item => {
                item.classList.remove('unlocked', 'active');
                item.style.removeProperty('background-color');
                item.style.removeProperty('color');
            });
            
            // Reset mobile badge - get first tier to show message
            if (this.mobileDiscountBadge) {
                this.mobileDiscountBadge.classList.remove('active');
                
                // Get first tier to show initial message
                const allTiers = Array.from(this.tierItems).map(item => ({
                    quantity: parseInt(item.dataset.quantity),
                    discount: parseFloat(item.dataset.discount)
                })).sort((a, b) => a.quantity - b.quantity);
                
                const firstTier = allTiers[0];
                if (firstTier) {
                    this.mobileDiscountBadge.innerHTML = `
                        <span class="mmb-mobile-badge-icon">🎯</span>
                        <span class="mmb-mobile-badge-text">Add ${firstTier.quantity} item${firstTier.quantity > 1 ? 's' : ''} to get ${firstTier.discount}% OFF</span>
                    `;
                } else {
                    this.mobileDiscountBadge.innerHTML = `
                        <span class="mmb-mobile-badge-icon">🎯</span>
                        <span class="mmb-mobile-badge-text">Select items to see your discount</span>
                    `;
                }
            }
            
            this.updateMobileStickyCart();
        },
        
        /**
         * Get selected products
         */
        getSelectedProducts() {
            const selected = [];
            const isQuantityMode = this.productQtys.length > 0 && this.productSelects.length === 0;
            
            if (isQuantityMode) {
                // Quantity mode
                this.productQtys.forEach(input => {
                    const qty = parseInt(input.value) || 0;
                    if (qty > 0) {
                        const card = input.closest('.mmb-product-card');
                        const productId = parseInt(card.dataset.productId);
                        const variationId = parseInt(card.dataset.variationId) || 0;
                        const price = parseFloat(card.dataset.variationPrice || card.dataset.price) || 0;
                        const isVariable = card.dataset.isVariable === '1';
                        
                        // Skip if variable product without variation selected
                        if (isVariable && !variationId) {
                            return;
                        }
                        
                        // Add multiple entries for quantity
                        for (let i = 0; i < qty; i++) {
                            selected.push({
                                id: variationId || productId,
                                product_id: productId,
                                variation_id: variationId,
                                price: price
                            });
                        }
                    }
                });
            } else {
                // Checkbox mode
                this.productSelects.forEach(checkbox => {
                    if (checkbox.checked) {
                        const card = checkbox.closest('.mmb-product-card');
                        const productId = parseInt(card.dataset.productId);
                        const variationId = parseInt(card.dataset.variationId) || 0;
                        const price = parseFloat(card.dataset.variationPrice || card.dataset.price) || 0;
                        const isVariable = card.dataset.isVariable === '1';
                        
                        // Skip if variable product without variation selected
                        if (isVariable && !variationId) {
                            return;
                        }
                        
                        selected.push({
                            id: variationId || productId,
                            product_id: productId,
                            variation_id: variationId,
                            price: price
                        });
                    }
                });
            }
            
            return selected;
        },
        
        /**
         * Add bundle to cart
         */
        async addBundleToCart() {
            if (!this.bundleData || !this.bundleData.products || this.bundleData.products.length === 0) {
                MMBDialog.error(
                    'No Products Selected',
                    'Please select at least one product to add to your bundle.'
                );
                return;
            }
            
            const bundleId = this.wrapper.dataset.bundleId;
            
            // Disable buttons
            if (this.addToCartBtn) {
                this.addToCartBtn.disabled = true;
                this.addToCartBtn.textContent = 'Adding to cart...';
            }
            if (this.mobileAddBtn) {
                this.mobileAddBtn.disabled = true;
                this.mobileAddBtn.textContent = 'Adding...';
            }
            
            try {
                // Calculate discounted prices for each item
                const products = this.bundleData.products;
                const discountAmount = parseFloat(this.bundleData.discount_amount || 0);
                const totalPrice = parseFloat(this.bundleData.total_price || 0);
                
                // Calculate original subtotal from products if not in bundleData
                let originalSubtotal = parseFloat(this.bundleData.subtotal || 0);
                if (originalSubtotal <= 0 && products && products.length > 0) {
                    originalSubtotal = products.reduce((sum, product) => {
                        const price = parseFloat(product.price || 0);
                        const quantity = parseInt(product.quantity || 1);
                        return sum + (price * quantity);
                    }, 0);
                }
                
                // Calculate discount ratio
                const discountRatio = originalSubtotal > 0 ? discountAmount / originalSubtotal : 0;
                
                // Calculate discounted price for each product
                const productsWithDiscount = products.map(product => {
                    const originalPrice = parseFloat(product.price || 0);
                    const quantity = parseInt(product.quantity || 1);
                    const itemSubtotal = originalPrice * quantity;
                    const itemDiscount = itemSubtotal * discountRatio;
                    const discountedPrice = Math.max(0, originalPrice - (itemDiscount / quantity));
                    
                    return {
                        ...product,
                        original_price: originalPrice,
                        discounted_price: discountedPrice,
                        price: discountedPrice, // Use discounted price as the main price
                        quantity: quantity // Ensure quantity is included
                    };
                });
                
                // Prepare data for AJAX request with discounted prices
                const bundleItemsJson = JSON.stringify(productsWithDiscount);
                
                const formData = new URLSearchParams({
                    action: 'mmb_add_bundle_to_cart',
                    nonce: mmb_frontend.nonce,
                    bundle_id: bundleId,
                    bundle_items: bundleItemsJson,
                    total_price: this.bundleData.total_price,
                    discount_amount: this.bundleData.discount_amount
                });
                
                // Store bundle data in session
                const sessionResponse = await fetch(mmb_frontend.ajaxurl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: formData
                });
                
                const sessionData = await sessionResponse.json();
                
                if (sessionData.success) {
                    // Add all products in a single AJAX call (use products with discounted prices)
                    await this.addAllProductsToCart(productsWithDiscount);
                } else {
                    const errorMessage = typeof sessionData.data === 'string' ? sessionData.data : 'Failed to save bundle to session';
                    console.error('Session error:', errorMessage);
                    MMBDialog.error(
                        'Error',
                        errorMessage
                    );
                    this.resetAddToCartButtons();
                }
            } catch (error) {
                console.error('Error adding bundle to cart:', error);
                MMBDialog.error(
                    'Error',
                    'An unexpected error occurred while adding the bundle to cart. Please try again.'
                );
                this.resetAddToCartButtons();
            }
        },
        
        /**
         * Add all products to cart in a single AJAX call
         */
        async addAllProductsToCart(products) {
            // Group products and combine quantities, preserving discounted prices
            const productMap = {};
            products.forEach(product => {
                const productId = product.id || product.product_id;
                const variationId = product.variation_id || 0;
                const key = `${productId}_${variationId}`;
                
                // Get discounted price (should already be calculated)
                const discountedPrice = product.discounted_price || product.price;
                const originalPrice = product.original_price || product.price;
                
                if (productMap[key]) {
                    // Increment quantity for existing product
                    productMap[key].quantity += (product.quantity || 1);
                } else {
                    productMap[key] = {
                        id: productId,
                        product_id: productId,
                        variation_id: variationId,
                        quantity: product.quantity || 1,
                        name: product.name || '',
                        price: discountedPrice, // Set price to discounted price (backend will use this)
                        discounted_price: discountedPrice,
                        original_price: originalPrice
                    };
                }
            });
            
            // Convert map back to array
            const groupedProducts = Object.values(productMap);
            
            try {
                // Get discount amount from bundle data
                const discountAmount = this.bundleData && this.bundleData.discount_amount ? this.bundleData.discount_amount : 0;
                
                const params = new URLSearchParams({
                    action: 'mmb_wc_ajax_add_to_cart',
                    nonce: mmb_frontend.nonce,
                    products: JSON.stringify(groupedProducts),
                    discount_amount: discountAmount
                });
                
                const response = await fetch(mmb_frontend.ajaxurl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    },
                    body: params.toString()
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const responseText = await response.text();
                
                // Check if response is empty or contains HTML (error page)
                if (!responseText || responseText.trim().startsWith('<')) {
                    throw new Error('Invalid response from server');
                }
                
                // Try to parse JSON with better error handling
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('MMB: Failed to parse JSON response:', parseError);
                    console.error('MMB: Raw response:', responseText);
                    throw new Error('Invalid JSON response from server');
                }
                
                // Handle bundle session storage response
                if (data.success && data.data && data.data.bundle_id) {
                    // Bundle items stored in session, now add to cart
                    console.log('MMB: Bundle items stored, adding to cart...');
                    
                    // Call addBundleToCart again with stored bundle data
                    return this.addBundleToCart();
                }
                
                // Validate data exists
                if (!data) {
                    throw new Error('No data received from server');
                }

                // Handle both response formats: { success: true, data: {...} } or direct { fragments: {...} }
                let fragmentsObject = null;
                let cartHash = null;
                let failedItems = [];

                // Check for error response first
                if (data.success === false) {
                    let errorMessage = 'Failed to add products to cart';
                    
                    if (data.data) {
                        if (typeof data.data === 'string') {
                            errorMessage = data.data;
                        } else if (data.data.message) {
                            errorMessage = data.data.message;
                        } else if (data.data.errors && Array.isArray(data.data.errors)) {
                            errorMessage = data.data.errors.join(', ');
                        }
                    }
                    
                    console.error('Add to cart failed:', errorMessage);
                    MMBDialog.error('Error', errorMessage);
                    this.resetAddToCartButtons();
                    return;
                }

                // WooCommerce native format: { fragments: {...}, cart_hash: '...' }
                // This is the standard format, not wrapped in success/data
                if (data.fragments && typeof data.fragments === 'object') {
                    fragmentsObject = data.fragments;
                    cartHash = data.cart_hash || null;
                } else if (data.success && data.data && typeof data.data === 'object') {
                    // Fallback: Standard format: { success: true, data: { fragments, cart_hash, failed_items } }
                    fragmentsObject = data.data.fragments || null;
                    cartHash = data.data.cart_hash || null;
                    failedItems = data.data.failed_items || [];
                }

                if (fragmentsObject && typeof fragmentsObject === 'object' && Object.keys(fragmentsObject).length > 0) {
                    // Let WooCommerce handle fragment updates naturally
                    // Just trigger the standard WooCommerce events
                    if (window.jQuery) {
                        window.jQuery(document.body).trigger('wc_fragment_refresh');
                        window.jQuery(document.body).trigger('added_to_cart', [fragmentsObject, cartHash, null]);
                    }
                    
                    // Update WooCommerce Blocks cart store with fragments
                    this.updateBlocksCartStore(fragmentsObject, cartHash);

                    // Show success
                    this.onBundleAddedSuccess();
                } else {
                    console.error('MMB: No valid fragments in response. Full response:', data);
                    MMBDialog.error('Error', 'Failed to add products to cart - invalid response from server');
                    this.resetAddToCartButtons();
                }
            } catch (error) {
                console.error('AJAX error adding products:', error);
                console.error('Error stack:', error.stack);
                MMBDialog.error(
                    'Error',
                    'An unexpected error occurred while adding products to cart. Please try again.'
                );
                this.resetAddToCartButtons();
            }
        },
        
        /**
         * Update WooCommerce Blocks cart store
         */
        updateBlocksCartStore(fragments, cartHash) {
            if (!window.wp || !window.wp.data) {
                return;
            }
            
            try {
                const dispatch = window.wp.data.dispatch('wc/store/cart');
                
                if (dispatch) {
                    // Force cart data refresh
                    if (typeof dispatch.invalidateResolutionForStoreSelector === 'function') {
                        dispatch.invalidateResolutionForStoreSelector('getCartData');
                        dispatch.invalidateResolutionForStoreSelector('getCartTotals');
                        dispatch.invalidateResolutionForStoreSelector('getCartItems');
                        dispatch.invalidateResolutionForStoreSelector('getCartCoupons');
                    }
                    
                    // Trigger cart refresh
                    if (typeof dispatch.refreshCart === 'function') {
                        dispatch.refreshCart();
                    }
                    
                    // Additional refresh after delay to ensure coupon is processed
                    setTimeout(() => {
                        if (typeof dispatch.refreshCart === 'function') {
                            dispatch.refreshCart();
                        }
                    }, 300);
                    
                    // Final refresh after longer delay
                    setTimeout(() => {
                        if (typeof dispatch.refreshCart === 'function') {
                            dispatch.refreshCart();
                        }
                    }, 1000);
                }
            } catch (e) {
                // Silently fail - don't break cart functionality
                if (window.console && console.error) {
                    console.error('MMB: Failed to update Blocks cart store:', e);
                }
            }
        },
        
        /**
         * On bundle added success
         */
        onBundleAddedSuccess() {
            // Update buttons
            if (this.addToCartBtn) {
                this.addToCartBtn.textContent = '✓ Added to Cart!';
                this.addToCartBtn.classList.add('mmb-success');
            }
            if (this.mobileAddBtn) {
                this.mobileAddBtn.textContent = '✓ Added!';
                this.mobileAddBtn.classList.add('mmb-success');
            }

            this.cleanupWooAddedToCartLinks();
            
            // Trigger WooCommerce events
            this.triggerCustomEvent('wc_fragment_refresh');
            this.triggerCustomEvent('added_to_cart');
            
            // Handle cart behavior
            if (this.cartBehavior === 'redirect') {
                setTimeout(() => {
                    window.location.href = mmb_frontend.cart_url || (typeof wc_add_to_cart_params !== 'undefined' ? wc_add_to_cart_params.cart_url : '/cart');
                }, 500);
            } else {
                
                // Reset buttons after 3 seconds
                setTimeout(() => {
                    this.resetAddToCartButtons();
                    this.cleanupWooAddedToCartLinks();
                }, 3000);
                
                // Trigger sidecart events
                this.triggerSideCartEvents();
                
                // Update mobile sticky cart
                this.updateMobileStickyCart();
            }
        },
        
        /**
         * Reset add to cart buttons
         */
        resetAddToCartButtons() {
            if (this.addToCartBtn) {
                this.addToCartBtn.textContent = 'Add Bundle to Cart';
                this.addToCartBtn.classList.remove('mmb-success');
                this.addToCartBtn.disabled = false;
            }
            if (this.mobileAddBtn) {
                this.mobileAddBtn.textContent = 'Add to Cart';
                this.mobileAddBtn.classList.remove('mmb-success');
                this.mobileAddBtn.disabled = false;
            }
        },
        
        /**
         * Trigger sidecart events
         */
        triggerSideCartEvents() {
            // FunnelKit Cart (WooFunnels) - PRIORITY
            if (typeof wfacp_frontend !== 'undefined' || document.querySelector('.wcf-cart-slide-out') || document.querySelector('#wcf-quick-view-content')) {
                this.triggerCustomEvent('wc_fragments_loaded');
                this.triggerCustomEvent('wc_fragment_refresh');
                this.triggerCustomEvent('updated_wc_div');
                this.triggerCustomEvent('updated_cart_totals');
                
                // Try to open FunnelKit cart
                const funnelKitTrigger = document.querySelector('.wcf-cart-trigger, .wcf-side-cart-trigger, .wcf-cart-icon');
                if (funnelKitTrigger) funnelKitTrigger.click();
            }
            
            // Side Cart for WooCommerce by XooIt
            if (typeof xoo_wsc_cart !== 'undefined' || document.querySelector('.xoo-wsc-container')) {
                this.triggerCustomEvent('xoo_wsc_cart_updated');
                const xooTrigger = document.querySelector('.xoo-wsc-basket');
                if (xooTrigger) xooTrigger.click();
            }
            
            // WooCommerce default events
            this.triggerCustomEvent('wc_fragment_refresh');
            this.triggerCustomEvent('added_to_cart');
            this.triggerCustomEvent('update_checkout');
            this.triggerCustomEvent('wc_fragments_refreshed');
            
            // WooCommerce Blocks (Twenty Twenty-Five)
            this.triggerCustomEvent('wc-blocks_added_to_cart');
            this.triggerCustomEvent('wc-blocks_update_cart');
            
            // Update WooCommerce Blocks cart store (using the method that handles it properly)
            // This will be called with fragments from the response, but we also trigger it here as fallback
            this.updateBlocksCartStore(null, null);
            
            // Try to open sidecart
            setTimeout(() => {
                this.tryOpenSideCart();
            }, 300);
            
            // Update sidecart prices after it opens
            setTimeout(() => {
                this.updateSidecartPrices();
            }, 500);
        },
        
        /**
         * Update sidecart prices with bundle discounts
         */
        updateSidecartPrices() {
            // This will be called after sidecart opens to update prices
            // Sidecart will update naturally via WooCommerce fragments
        },
        
        /**
         * Try to open sidecart
         */
        tryOpenSideCart() {
            const cartTriggers = [
                // WordPress Default Themes
                '.wp-block-woocommerce-mini-cart-contents',
                '.wp-block-woocommerce-mini-cart button',
                '.wc-block-mini-cart__button',
                'button.wc-block-mini-cart__button',
                'header .wp-block-woocommerce-mini-cart',
                '.wp-site-blocks .wp-block-woocommerce-mini-cart button',
                // General WooCommerce
                '.widget_shopping_cart_content',
                '.cart-customlocation',
                'a.cart-contents',
                '.shopping-cart-icon',
                // Common theme cart icons
                '.header-cart-toggle',
                '.header-cart-link',
                '.mini-cart-toggle',
                '.cart-toggle'
            ];
            
            for (const selector of cartTriggers) {
                const trigger = document.querySelector(selector);
                if (trigger) {
                    trigger.click();
                    return;
                }
            }
            
            this.showSuccessNotification();
            
            // Final fallback: WooCommerce Blocks mini cart
            setTimeout(() => {
                const blockCartButton = document.querySelector('.wp-block-woocommerce-mini-cart button, button.wc-block-mini-cart__button');
                if (blockCartButton && !blockCartButton.classList.contains('mmb-final-attempt')) {
                    blockCartButton.classList.add('mmb-final-attempt');
                    blockCartButton.click();
                    setTimeout(() => {
                        blockCartButton.classList.remove('mmb-final-attempt');
                    }, 2000);
                }
            }, 1500);
        },
        
        /**
         * Show success notification (fallback)
         */
        showSuccessNotification() {
            const notification = document.createElement('div');
            notification.className = 'mmb-success-notification';
            notification.innerHTML = '<span class="mmb-success-icon">✓</span> Bundle added to cart successfully!';
            
            Object.assign(notification.style, {
                position: 'fixed',
                top: '20px',
                right: '20px',
                background: this.primaryColor || '#4caf50',
                color: '#fff',
                padding: '15px 25px',
                borderRadius: '8px',
                boxShadow: '0 4px 12px rgba(0,0,0,0.3)',
                zIndex: '99999',
                fontWeight: '600',
                fontSize: '14px',
                display: 'flex',
                alignItems: 'center',
                gap: '10px',
                animation: 'slideInRight 0.3s ease-out'
            });
            
            const icon = notification.querySelector('.mmb-success-icon');
            if (icon) {
                Object.assign(icon.style, {
                    display: 'inline-block',
                    width: '24px',
                    height: '24px',
                    background: 'rgba(255,255,255,0.3)',
                    borderRadius: '50%',
                    textAlign: 'center',
                    lineHeight: '24px',
                    fontSize: '16px'
                });
            }
            
            // Add animation keyframes if not exists
            if (!document.getElementById('mmb-notification-styles')) {
                const style = document.createElement('style');
                style.id = 'mmb-notification-styles';
                style.textContent = `
                    @keyframes slideInRight {
                        from { transform: translateX(100%); opacity: 0; }
                        to { transform: translateX(0); opacity: 1; }
                    }
                `;
                document.head.appendChild(style);
            }
            
            document.body.appendChild(notification);
            
            // Remove after 4 seconds
            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transition = 'opacity 0.3s ease';
                setTimeout(() => notification.remove(), 300);
            }, 4000);
        },
        
        /**
         * Trigger custom event
         */
        triggerCustomEvent(eventName, detail = []) {
            const payload = Array.isArray(detail) ? detail : (detail ? [detail] : []);
            const event = new CustomEvent(eventName, { detail: payload, bubbles: true });
            document.body.dispatchEvent(event);

            if (window.jQuery && window.jQuery.fn && typeof window.jQuery(document.body).trigger === 'function') {
                window.jQuery(document.body).trigger(eventName, payload);
            }
        },
        
        /**
         * Format price
         */
        formatPrice(price) {
            const numPrice = parseFloat(price) || 0;
            
            // Try to use WooCommerce currency settings
            if (typeof accounting !== 'undefined' && typeof woocommerce_params !== 'undefined') {
                return accounting.formatMoney(numPrice, {
                    symbol: woocommerce_params.currency_format_symbol || '$',
                    decimal: woocommerce_params.currency_format_decimal_sep || '.',
                    thousand: woocommerce_params.currency_format_thousand_sep || ',',
                    precision: woocommerce_params.currency_format_num_decimals || 2,
                    format: woocommerce_params.currency_format || '%s%v'
                });
            }
            
            // Fallback to basic formatting
            return '$' + numPrice.toFixed(2);
        }
    };
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => MMB_Frontend.init());
    } else {
        MMB_Frontend.init();
    }

})();
