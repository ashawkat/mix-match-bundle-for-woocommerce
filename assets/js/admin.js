/**
 * Mix & Match Bundle - Admin JavaScript (Vanilla JS)
 */

(function() {
    'use strict';

    const MMB_Admin = {
        
        // State management
        selectedProductIds: new Set(), // Use Set for better performance
        selectedProductsOrder: [], // Array to maintain order
        allProducts: [], // Cache all products
        
        // DOM elements
        elements: {},
        
        /**
         * Initialize the admin interface
         */
        init() {
            console.log('🚀 === MMB Admin Initializing ===');
            
            this.cacheDom();
            
            // Verify critical elements exist
            console.log('Verifying DOM elements...');
            console.log('- Bundle form:', !!this.elements.form);
            console.log('- Products list:', !!this.elements.productsList);
            console.log('- Selected products list:', !!this.elements.selectedProductsList);
            console.log('- Selected products group:', !!this.elements.selectedProductsGroup);
            
            if (!this.elements.selectedProductsList || !this.elements.selectedProductsGroup) {
                console.warn('⚠️ Selected products elements not found on initial load');
                console.log('This might be normal if the HTML template is old. Elements will be re-cached when needed.');
            }
            
            this.bindEvents();
            this.loadBundles();
            this.initSweetAlert();
            
            // Load products on initial page load
            this.searchProducts('');
            
            // Add default tier if none exist
            if (this.elements.tiersContainer.querySelectorAll('.mmb-tier-input').length === 0) {
                this.addTierInput(2, 10);
            }
            
            console.log('✅ MMB Admin Initialized');
            
            // Make this accessible globally for debugging
            window.MMB_Admin = this;
            console.log('💡 TIP: You can access MMB_Admin from console for debugging');
        },
        
        /**
         * Cache DOM elements
         */
        cacheDom() {
            this.elements = {
                form: document.getElementById('mmb-bundle-form'),
                bundleId: document.getElementById('bundle_id'),
                bundleName: document.getElementById('bundle_name'),
                bundleDescription: document.getElementById('bundle_description'),
                bundleEnabled: document.getElementById('bundle_enabled'),
                useQuantity: document.getElementById('bundle_use_quantity'),
                headingText: document.getElementById('heading_text'),
                hintText: document.getElementById('hint_text'),
                primaryColor: document.getElementById('primary_color'),
                accentColor: document.getElementById('accent_color'),
                hoverBgColor: document.getElementById('hover_bg_color'),
                hoverAccentColor: document.getElementById('hover_accent_color'),
                buttonTextColor: document.getElementById('button_text_color'),
                buttonText: document.getElementById('button_text'),
                progressText: document.getElementById('progress_text'),
                cartBehavior: document.getElementById('cart_behavior'),
                showBundleTitle: document.getElementById('show_bundle_title'),
                showBundleDescription: document.getElementById('show_bundle_description'),
                showHeadingText: document.getElementById('show_heading_text'),
                showHintText: document.getElementById('show_hint_text'),
                showProgressText: document.getElementById('show_progress_text'),
                productSearch: document.getElementById('mmb-product-search'),
                productsList: document.getElementById('mmb-products-list'),
                selectedProductsList: document.getElementById('mmb-selected-products-list'),
                selectedProductsGroup: document.getElementById('mmb-selected-products-group'),
                tiersContainer: document.getElementById('mmb-tiers-container'),
                bundlesContainer: document.getElementById('mmb-bundles-container'),
                addTierBtn: document.getElementById('mmb-add-tier'),
                resetBtn: document.getElementById('mmb-reset-form')
            };
        },
        
        /**
         * Bind event listeners
         */
        bindEvents() {
            // Form submit
            this.elements.form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.saveBundle();
            });
            
            // Add tier button
            this.elements.addTierBtn.addEventListener('click', () => {
                this.addTierInput();
            });
            
            // Remove tier (delegated)
            this.elements.tiersContainer.addEventListener('click', (e) => {
                if (e.target.classList.contains('mmb-remove-tier')) {
                    e.target.closest('.mmb-tier-input').remove();
                }
            });
            
            // Reset button
            this.elements.resetBtn.addEventListener('click', () => {
                this.resetForm();
            });
            
            // Toggle max quantity field visibility
            const useQuantityCheckbox = document.getElementById('bundle_use_quantity');
            const maxQuantityGroup = document.getElementById('max_quantity_group');
            if (useQuantityCheckbox && maxQuantityGroup) {
                useQuantityCheckbox.addEventListener('change', () => {
                    maxQuantityGroup.style.display = useQuantityCheckbox.checked ? 'block' : 'none';
                });
            }
            
            // Product search with debounce
            let searchTimeout;
            this.elements.productSearch.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.searchProducts(e.target.value);
                }, 300);
            });
            
            // Product selection (delegated)
            this.elements.productsList.addEventListener('change', (e) => {
                if (e.target.type === 'checkbox') {
                    this.updateSelectedProducts(e.target);
                }
            });
            
            // Selected products drag and drop (delegated) - only if element exists
            if (this.elements.selectedProductsList) {
                this.elements.selectedProductsList.addEventListener('dragstart', (e) => {
                    if (e.target.classList.contains('mmb-selected-product-item')) {
                        e.target.classList.add('dragging');
                        e.dataTransfer.effectAllowed = 'move';
                        e.dataTransfer.setData('text/html', e.target.innerHTML);
                    }
                });
                
                this.elements.selectedProductsList.addEventListener('dragend', (e) => {
                    if (e.target.classList.contains('mmb-selected-product-item')) {
                        e.target.classList.remove('dragging');
                    }
                });
                
                this.elements.selectedProductsList.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    const afterElement = this.getDragAfterElement(this.elements.selectedProductsList, e.clientY);
                    const draggable = document.querySelector('.dragging');
                    if (draggable) {
                        if (afterElement == null) {
                            this.elements.selectedProductsList.appendChild(draggable);
                        } else {
                            this.elements.selectedProductsList.insertBefore(draggable, afterElement);
                        }
                        // Update order array
                        this.updateProductOrder();
                    }
                });
                
                // Remove product from selected list (delegated)
                this.elements.selectedProductsList.addEventListener('click', (e) => {
                    if (e.target.classList.contains('mmb-remove-selected-product')) {
                        const productId = parseInt(e.target.dataset.productId);
                        this.removeSelectedProduct(productId);
                    }
                });
            }
            
            // Bundle actions (delegated)
            this.elements.bundlesContainer.addEventListener('click', (e) => {
                if (e.target.classList.contains('mmb-edit-bundle')) {
                    const bundleId = e.target.dataset.bundleId;
                    this.editBundle(bundleId);
                } else if (e.target.classList.contains('mmb-delete-bundle')) {
                    const bundleId = e.target.dataset.bundleId;
                    this.confirmDeleteBundle(bundleId);
                } else if (e.target.classList.contains('mmb-copy-shortcode')) {
                    e.preventDefault();
                    this.copyShortcode(e.target);
                } else if (e.target.classList.contains('mmb-shortcode-input')) {
                    e.target.select();
                }
            });
        },
        
        /**
         * Initialize SweetAlert CSS
         */
        initSweetAlert() {
            if (!document.getElementById('mmb-sweetalert-styles')) {
                const style = document.createElement('style');
                style.id = 'mmb-sweetalert-styles';
                style.textContent = `
                    .mmb-modal-overlay {
                        position: fixed;
                        top: 0;
                        left: 0;
                        right: 0;
                        bottom: 0;
                        background: rgba(0, 0, 0, 0.5);
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        z-index: 999999;
                        animation: mmb-fade-in 0.2s ease-out;
                    }
                    
                    .mmb-modal {
                        background: white;
                        border-radius: 12px;
                        padding: 32px;
                        max-width: 500px;
                        width: 90%;
                        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
                        animation: mmb-scale-in 0.3s ease-out;
                        text-align: center;
                    }
                    
                    .mmb-modal-icon {
                        width: 80px;
                        height: 80px;
                        margin: 0 auto 20px;
                        border-radius: 50%;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        font-size: 40px;
                    }
                    
                    .mmb-modal-icon.success {
                        background: #d4edda;
                        color: #155724;
                    }
                    
                    .mmb-modal-icon.error {
                        background: #f8d7da;
                        color: #721c24;
                    }
                    
                    .mmb-modal-icon.warning {
                        background: #fff3cd;
                        color: #856404;
                    }
                    
                    .mmb-modal-icon.info {
                        background: #d1ecf1;
                        color: #0c5460;
                    }
                    
                    .mmb-modal-title {
                        font-size: 24px;
                        font-weight: 600;
                        color: #333;
                        margin: 0 0 10px;
                    }
                    
                    .mmb-modal-text {
                        font-size: 16px;
                        color: #666;
                        margin: 0 0 25px;
                        line-height: 1.5;
                    }
                    
                    .mmb-modal-buttons {
                        display: flex;
                        gap: 10px;
                        justify-content: center;
                    }
                    
                    .mmb-modal-btn {
                        padding: 12px 24px;
                        border: none;
                        border-radius: 6px;
                        font-size: 16px;
                        font-weight: 600;
                        cursor: pointer;
                        transition: all 0.2s ease;
                        min-width: 100px;
                    }
                    
                    .mmb-modal-btn.primary {
                        background: #2271b1;
                        color: white;
                    }
                    
                    .mmb-modal-btn.primary:hover {
                        background: #135e96;
                    }
                    
                    .mmb-modal-btn.danger {
                        background: #dc3545;
                        color: white;
                    }
                    
                    .mmb-modal-btn.danger:hover {
                        background: #c82333;
                    }
                    
                    .mmb-modal-btn.secondary {
                        background: #f0f0f1;
                        color: #2c3338;
                    }
                    
                    .mmb-modal-btn.secondary:hover {
                        background: #dcdcde;
                    }
                    
                    @keyframes mmb-fade-in {
                        from { opacity: 0; }
                        to { opacity: 1; }
                    }
                    
                    @keyframes mmb-scale-in {
                        from {
                            opacity: 0;
                            transform: scale(0.9);
                        }
                        to {
                            opacity: 1;
                            transform: scale(1);
                        }
                    }
                    
                    @keyframes mmb-scale-out {
                        from {
                            opacity: 1;
                            transform: scale(1);
                        }
                        to {
                            opacity: 0;
                            transform: scale(0.9);
                        }
                    }
                `;
                document.head.appendChild(style);
            }
        },
        
        /**
         * Show SweetAlert-style modal
         */
        showAlert(options) {
            return new Promise((resolve) => {
                const {
                    type = 'info',
                    title = '',
                    text = '',
                    confirmText = 'OK',
                    cancelText = 'Cancel',
                    showCancel = false
                } = options;
                
                const icons = {
                    success: '✓',
                    error: '✕',
                    warning: '⚠',
                    info: 'ℹ'
                };
                
                const overlay = document.createElement('div');
                overlay.className = 'mmb-modal-overlay';
                
                const modal = document.createElement('div');
                modal.className = 'mmb-modal';
                
                modal.innerHTML = `
                    <div class="mmb-modal-icon ${type}">
                        ${icons[type] || icons.info}
                    </div>
                    <h2 class="mmb-modal-title">${title}</h2>
                    <p class="mmb-modal-text">${text}</p>
                    <div class="mmb-modal-buttons">
                        ${showCancel ? `<button class="mmb-modal-btn secondary" data-action="cancel">${cancelText}</button>` : ''}
                        <button class="mmb-modal-btn ${showCancel ? 'danger' : 'primary'}" data-action="confirm">${confirmText}</button>
                    </div>
                `;
                
                overlay.appendChild(modal);
                document.body.appendChild(overlay);
                
                const closeModal = (confirmed) => {
                    modal.style.animation = 'mmb-scale-out 0.2s ease-out';
                    setTimeout(() => {
                        overlay.remove();
                        resolve(confirmed);
                    }, 200);
                };
                
                modal.addEventListener('click', (e) => {
                    if (e.target.dataset.action === 'confirm') {
                        closeModal(true);
                    } else if (e.target.dataset.action === 'cancel') {
                        closeModal(false);
                    }
                });
                
                overlay.addEventListener('click', (e) => {
                    if (e.target === overlay && !showCancel) {
                        closeModal(false);
                    }
                });
            });
        },
        
        /**
         * AJAX helper function
         */
        async ajax(data) {
            try {
                // Use FormData for better array handling
                const formData = new FormData();
                
                for (const key in data) {
                    const value = data[key];
                    
                    // Handle discount_tiers specially - use PHP array notation
                    if (key === 'discount_tiers' && Array.isArray(value)) {
                        console.log('Encoding discount_tiers:', value);
                        value.forEach((tier, index) => {
                            formData.append(`discount_tiers[${index}][quantity]`, tier.quantity);
                            formData.append(`discount_tiers[${index}][discount]`, tier.discount);
                        });
                    }
                    // Handle product_ids as JSON string
                    else if (key === 'product_ids' && Array.isArray(value)) {
                        console.log('Encoding product_ids:', value);
                        formData.append(key, JSON.stringify(value));
                    }
                    // Handle other values
                    else if (value !== null && value !== undefined) {
                        formData.append(key, value);
                    }
                }
                
                // Debug: Log what we're sending
                console.log('FormData entries:');
                for (let pair of formData.entries()) {
                    console.log(pair[0] + ': ' + pair[1]);
                }
                
                const response = await fetch(mmb_admin.ajaxurl, {
                    method: 'POST',
                    body: formData
                });
                
                return await response.json();
            } catch (error) {
                console.error('AJAX Error:', error);
                throw error;
            }
        },
        
        /**
         * Load all bundles
         */
        async loadBundles() {
            try {
                const response = await this.ajax({
                    action: 'mmb_get_bundles',
                    nonce: mmb_admin.nonce
                });
                
                    if (response.success) {
                    this.renderBundles(response.data);
                }
            } catch (error) {
                console.error('Error loading bundles:', error);
            }
        },
        
        /**
         * Render bundles list
         */
        renderBundles(bundles) {
            let html = '<div class="mmb-bundles-table-header"><div class="mmb-bundle-col">Name</div><div class="mmb-bundle-col">Products</div><div class="mmb-bundle-col">Tiers</div><div class="mmb-bundle-col">Status</div><div class="mmb-bundle-col">Shortcode</div><div class="mmb-bundle-col">Actions</div></div>';
            
            if (bundles.length === 0) {
                html += '<p class="mmb-no-bundles">No bundles yet. Create your first one!</p>';
            } else {
                bundles.forEach((bundle) => {
                    const status = bundle.enabled ? 'Enabled' : 'Disabled';
                    const statusClass = bundle.enabled ? 'enabled' : 'disabled';
                    const shortcode = `[mmb_bundle id="${bundle.id}"]`;
                    const shortcodeEscaped = shortcode.replace(/"/g, '&quot;');
                    
                    html += `
                        <div class="mmb-bundle-row">
                            <div class="mmb-bundle-col mmb-bundle-name">${this.escapeHtml(bundle.name)}</div>
                            <div class="mmb-bundle-col">${bundle.product_ids.length} products</div>
                            <div class="mmb-bundle-col">${bundle.discount_tiers.length} tiers</div>
                            <div class="mmb-bundle-col mmb-bundle-status ${statusClass}">${status}</div>
                            <div class="mmb-bundle-col mmb-shortcode-col">
                                <input type="text" class="mmb-shortcode-input" value="${shortcodeEscaped}" readonly title="${shortcodeEscaped}">
                                <button type="button" class="button mmb-copy-shortcode" data-shortcode="${shortcodeEscaped}" title="Copy shortcode">📋</button>
                            </div>
                            <div class="mmb-bundle-col mmb-bundle-actions">
                                <button class="button mmb-edit-bundle" data-bundle-id="${bundle.id}">Edit</button>
                                <button class="button button-link-delete mmb-delete-bundle" data-bundle-id="${bundle.id}">Delete</button>
                            </div>
                        </div>
                    `;
                });
            }
            
            this.elements.bundlesContainer.innerHTML = html;
        },
        
        /**
         * Save bundle
         */
        async saveBundle() {
            const discountTiers = this.getDiscountTiers();
            // Use ordered array instead of Set to preserve order
            const productIds = [...this.selectedProductsOrder];
            
            const formData = {
                action: 'mmb_save_bundle',
                nonce: mmb_admin.nonce,
                bundle_id: this.elements.bundleId.value,
                name: this.elements.bundleName.value,
                description: this.elements.bundleDescription.value,
                enabled: this.elements.bundleEnabled.checked ? 1 : 0,
                use_quantity: this.elements.useQuantity.checked ? 1 : 0,
                max_quantity: document.getElementById('max_quantity') ? parseInt(document.getElementById('max_quantity').value) || 10 : 10,
                heading_text: this.elements.headingText.value || 'Select Your Products Below',
                hint_text: this.elements.hintText.value || 'Bundle 2, 3, 4 or 5 items and watch the savings grow.',
                primary_color: this.elements.primaryColor.value || '#4caf50',
                accent_color: this.elements.accentColor.value || '#45a049',
                hover_bg_color: this.elements.hoverBgColor.value || '#388e3c',
                hover_accent_color: this.elements.hoverAccentColor.value || '#2e7d32',
                button_text_color: this.elements.buttonTextColor.value || '#ffffff',
                button_text: this.elements.buttonText.value || 'Add Bundle to Cart',
                progress_text: this.elements.progressText.value || 'Your Savings Progress',
                cart_behavior: this.elements.cartBehavior.value || 'sidecart',
                show_bundle_title: this.elements.showBundleTitle.checked ? 1 : 0,
                show_bundle_description: this.elements.showBundleDescription.checked ? 1 : 0,
                show_heading_text: this.elements.showHeadingText.checked ? 1 : 0,
                show_hint_text: this.elements.showHintText.checked ? 1 : 0,
                show_progress_text: this.elements.showProgressText.checked ? 1 : 0,
                product_ids: productIds,
                discount_tiers: discountTiers
            };
            
            console.log('=== SAVING BUNDLE ===');
            console.log('Bundle name:', formData.name);
            console.log('Product IDs (in order):', productIds);
            console.log('Discount tiers:', discountTiers);
            console.log('Full form data:', formData);
            
            // Validation
            if (!formData.name || formData.name.trim() === '') {
                await this.showAlert({
                    type: 'warning',
                    title: 'Missing Bundle Name',
                    text: 'Please enter a bundle name'
                });
                return;
            }
            
            if (formData.product_ids.length === 0) {
                await this.showAlert({
                    type: 'warning',
                    title: 'Missing Products',
                    text: 'Please select at least one product'
                });
                return;
            }
            
            if (formData.discount_tiers.length === 0) {
                await this.showAlert({
                    type: 'warning',
                    title: 'Missing Discount Tiers',
                    text: 'Please add at least one discount tier'
                });
                return;
            }
            
            try {
                const response = await this.ajax(formData);
                console.log('Save response:', response);
                
                    if (response.success) {
                    await this.showAlert({
                        type: 'success',
                        title: 'Success!',
                        text: 'Bundle saved successfully!'
                    });
                    this.resetForm();
                    this.loadBundles();
                    } else {
                    console.error('Save failed:', response);
                    
                    // Get detailed error message
                    let errorMessage = 'Failed to save bundle';
                    if (response.data) {
                        if (typeof response.data === 'string') {
                            errorMessage = response.data;
                        } else if (response.data.message) {
                            errorMessage = response.data.message;
                        }
                    }
                    
                    console.error('Error message:', errorMessage);
                    
                    await this.showAlert({
                        type: 'error',
                        title: 'Error Saving Bundle',
                        text: errorMessage
                    });
                }
            } catch (error) {
                console.error('Save error:', error);
                await this.showAlert({
                    type: 'error',
                    title: 'Network Error',
                    text: 'Failed to communicate with the server. Please check your connection and try again.'
                });
            }
        },
        
        /**
         * Confirm and delete bundle
         */
        async confirmDeleteBundle(bundleId) {
            const confirmed = await this.showAlert({
                type: 'warning',
                title: 'Are you sure?',
                text: 'Do you want to delete this bundle? This action cannot be undone.',
                confirmText: 'Yes, delete it',
                cancelText: 'Cancel',
                showCancel: true
            });
            
            if (confirmed) {
                this.deleteBundle(bundleId);
            }
        },
        
        /**
         * Delete bundle
         */
        async deleteBundle(bundleId) {
            try {
                const response = await this.ajax({
                    action: 'mmb_delete_bundle',
                    nonce: mmb_admin.nonce,
                    bundle_id: bundleId
                });
                
                    if (response.success) {
                    await this.showAlert({
                        type: 'success',
                        title: 'Deleted!',
                        text: 'Bundle has been deleted'
                    });
                    this.loadBundles();
                } else {
                    await this.showAlert({
                        type: 'error',
                        title: 'Error',
                        text: 'Failed to delete bundle'
                    });
                }
            } catch (error) {
                await this.showAlert({
                    type: 'error',
                    title: 'Error',
                    text: 'Failed to delete bundle. Please try again.'
                });
            }
        },
        
        /**
         * Edit bundle
         */
        async editBundle(bundleId) {
            try {
                console.log('=== EDIT BUNDLE DEBUG ===');
                console.log('Requesting bundle ID:', bundleId);
                
                const response = await this.ajax({
                    action: 'mmb_get_bundles',
                    nonce: mmb_admin.nonce
                });
                
                console.log('Full AJAX response:', response);
                
                    if (response.success) {
                    console.log('All bundles from server:', response.data);
                        const bundle = response.data.find(b => b.id == bundleId);
                    console.log('Found bundle:', bundle);
                    
                        if (bundle) {
                        // Log specifically the discount_tiers
                        console.log('Bundle discount_tiers type:', typeof bundle.discount_tiers);
                        console.log('Bundle discount_tiers value:', bundle.discount_tiers);
                        console.log('Bundle discount_tiers is Array?:', Array.isArray(bundle.discount_tiers));
                        console.log('Bundle discount_tiers length:', bundle.discount_tiers ? bundle.discount_tiers.length : 'N/A');
                        
                        this.populateForm(bundle);
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    } else {
                        console.error('Bundle not found in response data');
                    }
                } else {
                    console.error('Response not successful:', response);
                }
            } catch (error) {
                console.error('Error loading bundle:', error);
            }
        },
        
        /**
         * Populate form with bundle data
         */
        populateForm(bundle) {
            console.log('Populating form with bundle:', bundle);
            
            this.elements.bundleId.value = bundle.id;
            this.elements.bundleName.value = bundle.name;
            this.elements.bundleDescription.value = bundle.description;
            this.elements.bundleEnabled.checked = bundle.enabled === 1;
            this.elements.useQuantity.checked = bundle.use_quantity === 1;
            
            // Set max quantity and toggle visibility
            const maxQuantityInput = document.getElementById('max_quantity');
            const maxQuantityGroup = document.getElementById('max_quantity_group');
            if (maxQuantityInput) {
                maxQuantityInput.value = bundle.max_quantity || 10;
            }
            if (maxQuantityGroup) {
                maxQuantityGroup.style.display = bundle.use_quantity === 1 ? 'block' : 'none';
            }
            
            this.elements.headingText.value = bundle.heading_text || 'Select Your Products Below';
            this.elements.hintText.value = bundle.hint_text || 'Bundle 2, 3, 4 or 5 items and watch the savings grow.';
            this.elements.primaryColor.value = bundle.primary_color || '#4caf50';
            this.elements.accentColor.value = bundle.accent_color || '#45a049';
            this.elements.hoverBgColor.value = bundle.hover_bg_color || '#388e3c';
            this.elements.hoverAccentColor.value = bundle.hover_accent_color || '#2e7d32';
            this.elements.buttonTextColor.value = bundle.button_text_color || '#ffffff';
            this.elements.buttonText.value = bundle.button_text || 'Add Bundle to Cart';
            this.elements.progressText.value = bundle.progress_text || 'Your Savings Progress';
            this.elements.cartBehavior.value = bundle.cart_behavior || 'sidecart';
            this.elements.showBundleTitle.checked = bundle.show_bundle_title !== 0;
            this.elements.showBundleDescription.checked = bundle.show_bundle_description !== 0;
            this.elements.showHeadingText.checked = bundle.show_heading_text !== 0;
            this.elements.showHintText.checked = bundle.show_hint_text !== 0;
            this.elements.showProgressText.checked = bundle.show_progress_text !== 0;
            
            // Store selected products in state (preserving order)
            this.selectedProductsOrder = bundle.product_ids.map(id => parseInt(id));
            this.selectedProductIds = new Set(this.selectedProductsOrder);
            console.log('Selected product IDs:', this.selectedProductIds);
            console.log('Product order:', this.selectedProductsOrder);
            
            // Ensure all selected products are in cache before rendering
            // This prevents "not found in cache" warnings
            this.ensureProductsInCache(this.selectedProductsOrder).then(() => {
                console.log('✅ All selected products ensured in cache');
                // Render the selected products list
                this.renderSelectedProducts();
            });
            
            // Clear and repopulate tiers
            this.elements.tiersContainer.innerHTML = '';
            
            let tiers = bundle.discount_tiers;
            console.log('Raw discount tiers from bundle:', tiers, typeof tiers);
            
            // Handle if tiers is a JSON string
            if (typeof tiers === 'string') {
                try {
                    tiers = JSON.parse(tiers);
                    console.log('Parsed tiers from string:', tiers);
                } catch (e) {
                    console.error('Failed to parse tiers:', e);
                    tiers = [];
                }
            }
            
            if (Array.isArray(tiers) && tiers.length > 0) {
                console.log('Loading ' + tiers.length + ' tiers');
                tiers.forEach((tier, index) => {
                    console.log('Adding tier ' + index + ':', tier);
                    this.addTierInput(tier.quantity, tier.discount);
                });
            } else {
                console.warn('No valid tiers to load');
                // Add a default tier if none exist
                this.addTierInput(2, 10);
            }
            
            // Reload products to show selections
            this.searchProducts('');
        },
        
        /**
         * Ensure products are in cache
         * Fetches product data if not already cached
         */
        async ensureProductsInCache(productIds) {
            if (!productIds || productIds.length === 0) {
                return;
            }
            
            // Check which products are missing from cache
            const cachedIds = new Set(this.allProducts.map(p => p.id));
            const missingIds = productIds.filter(id => !cachedIds.has(id));
            
            if (missingIds.length === 0) {
                console.log('✅ All products already in cache');
                return;
            }
            
            console.log('📥 Fetching', missingIds.length, 'missing products:', missingIds);
            
            // Fetch all products to ensure we get the missing ones
            // This is simpler than fetching individual products
            try {
                const response = await this.ajax({
                    action: 'mmb_search_products',
                    nonce: mmb_admin.nonce,
                    search: '' // Empty search returns all products
                });
                
                if (response.success) {
                    // Merge into cache
                    const existingIds = new Set(this.allProducts.map(p => p.id));
                    response.data.forEach(product => {
                        if (!existingIds.has(product.id)) {
                            this.allProducts.push(product);
                        }
                    });
                    console.log('✅ Cache updated, total products:', this.allProducts.length);
                }
            } catch (error) {
                console.error('Error fetching products for cache:', error);
            }
        },
        
        /**
         * Search products
         */
        async searchProducts(searchTerm) {
            try {
                console.log('Searching products with term:', searchTerm);
                const response = await this.ajax({
                    action: 'mmb_search_products',
                    nonce: mmb_admin.nonce,
                    search: searchTerm
                });
                
                console.log('Search response:', response);
                
                    if (response.success) {
                    console.log('Products found:', response.data.length);
                    
                    // Merge new products into cache instead of replacing
                    // This ensures previously selected products remain in cache
                    const newProducts = response.data;
                    const existingIds = new Set(this.allProducts.map(p => p.id));
                    
                    newProducts.forEach(product => {
                        if (!existingIds.has(product.id)) {
                            this.allProducts.push(product);
                            existingIds.add(product.id);
                        }
                    });
                    
                    console.log('Total products in cache:', this.allProducts.length);
                    this.renderProducts(response.data); // Still render only search results
                } else {
                    console.error('Search failed:', response);
                    this.renderProducts([]);
                }
            } catch (error) {
                console.error('Error searching products:', error);
                this.renderProducts([]);
            }
        },
        
        /**
         * Render products list
         */
        renderProducts(products) {
            let html = '';
            
            products.forEach((product) => {
                const checked = this.selectedProductIds.has(product.id) ? 'checked' : '';
                html += `
                    <label class="mmb-product-option">
                        <input type="checkbox" value="${product.id}" ${checked}>
                        <span>${this.escapeHtml(product.name)}</span>
                        <span class="mmb-product-price">${product.price}</span>
                    </label>
                `;
            });
            
            this.elements.productsList.innerHTML = html || '<p>No products found</p>';
        },
        
        /**
         * Update selected products state
         */
        updateSelectedProducts(checkbox) {
            const productId = parseInt(checkbox.value);
            
            console.log('=== updateSelectedProducts called ===');
            console.log('Product ID:', productId);
            console.log('Checkbox checked:', checkbox.checked);
            
            if (checkbox.checked) {
                this.selectedProductIds.add(productId);
                // Add to ordered array if not already present
                if (!this.selectedProductsOrder.includes(productId)) {
                    this.selectedProductsOrder.push(productId);
                }
                console.log('✅ Added product:', productId);
            } else {
                this.selectedProductIds.delete(productId);
                // Remove from ordered array
                const index = this.selectedProductsOrder.indexOf(productId);
                if (index > -1) {
                    this.selectedProductsOrder.splice(index, 1);
                }
                console.log('❌ Removed product:', productId);
            }
            
            console.log('All selected products:', Array.from(this.selectedProductIds));
            console.log('Product order:', this.selectedProductsOrder);
            console.log('All products cache:', this.allProducts.length, 'products');
            
            // Update the selected products display with delay for heavy sites
            console.log('🔄 Calling renderSelectedProducts...');
            this.renderSelectedProducts();
            console.log('✅ renderSelectedProducts completed');
        },
        
        /**
         * Remove product from selection
         */
        removeSelectedProduct(productId) {
            // Remove from Set
            this.selectedProductIds.delete(productId);
            
            // Remove from order array
            const index = this.selectedProductsOrder.indexOf(productId);
            if (index > -1) {
                this.selectedProductsOrder.splice(index, 1);
            }
            
            // Uncheck in products list
            const checkbox = this.elements.productsList.querySelector(`input[value="${productId}"]`);
            if (checkbox) {
                checkbox.checked = false;
            }
            
            // Re-render
            this.renderSelectedProducts();
        },
        
        /**
         * Render selected products list
         */
        renderSelectedProducts() {
            console.log('🎨 === renderSelectedProducts START ===');
            
            // Check if elements exist
            console.log('Checking elements...');
            console.log('- selectedProductsList:', this.elements.selectedProductsList);
            console.log('- selectedProductsGroup:', this.elements.selectedProductsGroup);
            
            if (!this.elements.selectedProductsList || !this.elements.selectedProductsGroup) {
                console.error('❌ Elements not found! Cannot render selected products.');
                console.log('Try re-caching DOM elements...');
                
                // Try to re-cache the elements
                this.elements.selectedProductsList = document.getElementById('mmb-selected-products-list');
                this.elements.selectedProductsGroup = document.getElementById('mmb-selected-products-group');
                
                if (!this.elements.selectedProductsList || !this.elements.selectedProductsGroup) {
                    console.error('❌ Elements still not found after re-caching!');
                    return;
                }
                console.log('✅ Elements re-cached successfully');
            }
            
            console.log('Selected products count:', this.selectedProductsOrder.length);
            
            if (this.selectedProductsOrder.length === 0) {
                console.log('No products selected, hiding section');
                this.elements.selectedProductsGroup.style.display = 'none';
                this.elements.selectedProductsList.innerHTML = '';
                console.log('🎨 === renderSelectedProducts END (empty) ===');
                return;
            }
            
            console.log('✅ Showing selected products section');
            this.elements.selectedProductsGroup.style.display = 'block';
            this.elements.selectedProductsList.innerHTML = '';
            
            console.log('Rendering', this.selectedProductsOrder.length, 'products...');
            
            let renderedCount = 0;
            const missingProductIds = [];
            
            this.selectedProductsOrder.forEach((productId, index) => {
                console.log(`Rendering product ${index + 1}/${this.selectedProductsOrder.length}: ID ${productId}`);
                
                const product = this.allProducts.find(p => p.id === productId);
                if (!product) {
                    console.warn(`⚠️ Product ${productId} not found in cache!`);
                    missingProductIds.push(productId);
                    // Create a placeholder element
                    const item = document.createElement('div');
                    item.className = 'mmb-selected-product-item mmb-loading';
                    item.draggable = false;
                    item.dataset.productId = productId;
                    
                    item.innerHTML = `
                        <span class="mmb-drag-handle">⋮⋮</span>
                        <span class="mmb-product-order-number">${index + 1}.</span>
                        <span class="mmb-product-name">Loading product ${productId}...</span>
                        <span class="mmb-product-price-small">---</span>
                        <button type="button" class="mmb-remove-selected-product" data-product-id="${productId}" title="Remove">✕</button>
                    `;
                    
                    this.elements.selectedProductsList.appendChild(item);
                    return;
                }
                
                console.log(`- Product found:`, product.name);
                
                const item = document.createElement('div');
                item.className = 'mmb-selected-product-item';
                item.draggable = true;
                item.dataset.productId = productId;
                
                item.innerHTML = `
                    <span class="mmb-drag-handle">⋮⋮</span>
                    <span class="mmb-product-order-number">${index + 1}.</span>
                    <span class="mmb-product-name">${this.escapeHtml(product.name)}</span>
                    <span class="mmb-product-price-small">${product.price}</span>
                    <button type="button" class="mmb-remove-selected-product" data-product-id="${productId}" title="Remove">✕</button>
                `;
                
                this.elements.selectedProductsList.appendChild(item);
                renderedCount++;
                console.log(`✅ Product ${index + 1} rendered successfully`);
            });
            
            console.log(`✅ Rendered ${renderedCount} products total`);
            console.log('Final HTML length:', this.elements.selectedProductsList.innerHTML.length);
            
            // If we have missing products, fetch them and re-render
            if (missingProductIds.length > 0) {
                console.log('🔄 Fetching', missingProductIds.length, 'missing products and re-rendering...');
                this.ensureProductsInCache(missingProductIds).then(() => {
                    console.log('Re-rendering with fetched products...');
                    this.renderSelectedProducts();
                });
            }
            
            console.log('🎨 === renderSelectedProducts END (success) ===');
        },
        
        /**
         * Update product order from DOM
         */
        updateProductOrder() {
            if (!this.elements.selectedProductsList) {
                return;
            }
            
            const items = this.elements.selectedProductsList.querySelectorAll('.mmb-selected-product-item');
            this.selectedProductsOrder = Array.from(items).map(item => parseInt(item.dataset.productId));
            console.log('Updated product order:', this.selectedProductsOrder);
            
            // Update order numbers
            items.forEach((item, index) => {
                const orderNumber = item.querySelector('.mmb-product-order-number');
                if (orderNumber) {
                    orderNumber.textContent = `${index + 1}.`;
                }
            });
        },
        
        /**
         * Get drag after element
         */
        getDragAfterElement(container, y) {
            const draggableElements = [...container.querySelectorAll('.mmb-selected-product-item:not(.dragging)')];
            
            return draggableElements.reduce((closest, child) => {
                const box = child.getBoundingClientRect();
                const offset = y - box.top - box.height / 2;
                
                if (offset < 0 && offset > closest.offset) {
                    return { offset: offset, element: child };
                } else {
                    return closest;
                }
            }, { offset: Number.NEGATIVE_INFINITY }).element;
        },
        
        /**
         * Add tier input
         */
        addTierInput(quantity = '', discount = '') {
            console.log('addTierInput called with:', { quantity, discount });
            const index = this.elements.tiersContainer.querySelectorAll('.mmb-tier-input').length;
            console.log('Current tier index:', index);
            
            const tierDiv = document.createElement('div');
            tierDiv.className = 'mmb-tier-input';
            tierDiv.innerHTML = `
                    <input type="number" name="discount_tiers[${index}][quantity]" placeholder="Quantity" min="1" value="${quantity}" required>
                    <input type="number" name="discount_tiers[${index}][discount]" placeholder="Discount %" min="0" max="100" value="${discount}" step="0.01" required>
                    <button type="button" class="mmb-remove-tier">Remove</button>
            `;
            this.elements.tiersContainer.appendChild(tierDiv);
            console.log('Tier added to container, total tiers:', this.elements.tiersContainer.querySelectorAll('.mmb-tier-input').length);
        },
        
        /**
         * Get discount tiers
         */
        getDiscountTiers() {
            const tiers = [];
            const tierInputs = this.elements.tiersContainer.querySelectorAll('.mmb-tier-input');
            console.log('Getting discount tiers, found', tierInputs.length, 'tier inputs');
            
            tierInputs.forEach((tierInput, index) => {
                const quantityInput = tierInput.querySelector('input[name*="[quantity]"]');
                const discountInput = tierInput.querySelector('input[name*="[discount]"]');
                const quantity = quantityInput ? quantityInput.value : '';
                const discount = discountInput ? discountInput.value : '';
                
                console.log(`Tier ${index}:`, { quantity, discount });
                
                if (quantity && discount) {
                    tiers.push({
                        quantity: parseInt(quantity),
                        discount: parseFloat(discount)
                    });
                }
            });
            
            console.log('Returning tiers:', tiers);
            return tiers;
        },
        
        /**
         * Copy shortcode
         */
        async copyShortcode(button) {
            const shortcode = button.dataset.shortcode;
            
            try {
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(shortcode);
                } else {
                    // Fallback for older browsers
                    const input = button.previousElementSibling;
                    input.select();
                    input.setSelectionRange(0, 99999);
                    document.execCommand('copy');
                }
                
                const oldText = button.textContent;
                button.textContent = '✓ Copied!';
                setTimeout(() => {
                    button.textContent = oldText;
                }, 2000);
            } catch (error) {
                await this.showAlert({
                    type: 'error',
                    title: 'Copy Failed',
                    text: 'Failed to copy. Please select and copy manually.'
                });
            }
        },
        
        /**
         * Reset form
         */
        resetForm() {
            this.elements.form.reset();
            this.elements.bundleId.value = '0';
            this.elements.headingText.value = '';
            this.elements.hintText.value = '';
            this.elements.primaryColor.value = '#4caf50';
            this.elements.accentColor.value = '#45a049';
            this.elements.hoverBgColor.value = '#388e3c';
            this.elements.hoverAccentColor.value = '#2e7d32';
            this.elements.buttonTextColor.value = '#ffffff';
            this.elements.buttonText.value = '';
            this.elements.progressText.value = '';
            this.elements.cartBehavior.value = 'sidecart';
            this.elements.showBundleTitle.checked = true;
            this.elements.showBundleDescription.checked = true;
            this.elements.showHeadingText.checked = true;
            this.elements.showHintText.checked = true;
            this.elements.showProgressText.checked = true;
            this.elements.productsList.innerHTML = '';
            this.elements.tiersContainer.innerHTML = '';
            this.selectedProductIds.clear();
            this.selectedProductsOrder = [];
            this.renderSelectedProducts(); // Hide selected products section
            this.addTierInput(2, 10);
            this.searchProducts('');
        },
        
        /**
         * Escape HTML
         */
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },
        
        /**
         * Debug helper - Check current state
         */
        debugState() {
            console.log('=== MMB DEBUG STATE ===');
            console.log('Selected Product IDs:', Array.from(this.selectedProductIds));
            console.log('Product Order:', this.selectedProductsOrder);
            console.log('All Products Cache:', this.allProducts.length, 'products');
            console.log('Elements:', {
                form: !!this.elements.form,
                productsList: !!this.elements.productsList,
                selectedProductsList: !!this.elements.selectedProductsList,
                selectedProductsGroup: !!this.elements.selectedProductsGroup
            });
            
            if (this.elements.selectedProductsGroup) {
                console.log('Selected Products Group Display:', this.elements.selectedProductsGroup.style.display);
                console.log('Selected Products Group HTML:', this.elements.selectedProductsGroup.outerHTML.substring(0, 200));
            }
            
            if (this.elements.selectedProductsList) {
                console.log('Selected Products List Children:', this.elements.selectedProductsList.children.length);
                console.log('Selected Products List HTML Length:', this.elements.selectedProductsList.innerHTML.length);
            }
        },
        
        /**
         * Debug helper - Force re-render
         */
        forceRerender() {
            console.log('🔧 Forcing re-render...');
            this.cacheDom();
            this.renderSelectedProducts();
            console.log('✅ Re-render complete');
        }
    };
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => MMB_Admin.init());
    } else {
        MMB_Admin.init();
    }

})();
