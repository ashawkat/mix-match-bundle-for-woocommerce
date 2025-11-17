# Debug Guide for Client Site Issues

## Issue: Selected Products Not Showing on Heavy/Older Sites

### Quick Diagnosis

Upload the updated `admin.js` file to your client site, then follow these steps:

### Step 1: Open Browser Console

1. Go to Mix & Match admin page
2. Press `F12` to open Developer Tools
3. Go to **Console** tab

### Step 2: Check Initial Load

Look for these logs when page loads:
```
🚀 === MMB Admin Initializing ===
Verifying DOM elements...
- Bundle form: true
- Products list: true
- Selected products list: true/false  ← IMPORTANT!
- Selected products group: true/false  ← IMPORTANT!
```

**If you see `false` for selected products elements:**
- ✅ This is the issue - HTML template is outdated
- 📝 Solution below in "Fix Missing HTML"

### Step 3: Select a Product

Click a checkbox to select a product. Watch console for:
```
=== updateSelectedProducts called ===
Product ID: 12345
Checkbox checked: true
✅ Added product: 12345
🔄 Calling renderSelectedProducts...
🎨 === renderSelectedProducts START ===
```

**Check what happens next:**

#### Scenario A: Elements Not Found
```
❌ Elements not found! Cannot render selected products.
Try re-caching DOM elements...
❌ Elements still not found after re-caching!
```
**Problem:** HTML template missing the new elements  
**Solution:** See "Fix Missing HTML" below

#### Scenario B: Products Not in Cache
```
⚠️ Product 12345 not found in cache!
```
**Problem:** Product search didn't complete or failed  
**Solution:** Scroll down to "Fix Product Cache"

#### Scenario C: CSS Hiding Elements
```
✅ Showing selected products section
✅ Rendered 3 products total
```
But you still don't see it on screen!  
**Problem:** CSS conflict or override  
**Solution:** See "Fix CSS Conflicts" below

### Step 4: Use Debug Helpers

In browser console, type:
```javascript
// Check current state
MMB_Admin.debugState()

// Force re-render
MMB_Admin.forceRerender()
```

---

## Solutions

### Fix Missing HTML

**Problem:** The PHP template file is outdated (cached or not updated)

**Solution 1: Clear PHP Cache**
```bash
# If using object cache
wp cache flush

# If using OPcache
# Restart PHP-FPM or Apache
```

**Solution 2: Verify File Upload**
1. Check `admin/bundle-editor.php` line 90-94
2. Should have this code:
```php
<div class="mmb-form-group" id="mmb-selected-products-group" style="display: none;">
    <label><?php echo esc_html__( 'Selected Products (Drag to Reorder)', 'mix-match-bundle' ); ?></label>
    <p class="description"><?php echo esc_html__( 'Products will appear on the frontend in this order. Drag to rearrange.', 'mix-match-bundle' ); ?></p>
    <div id="mmb-selected-products-list" class="mmb-selected-products-list"></div>
</div>
```

**Solution 3: Force Template Refresh**
```php
// Add to wp-config.php temporarily
define('WP_DEBUG', true);
define('SCRIPT_DEBUG', true);
```
Then hard refresh browser (Ctrl+Shift+R)

---

### Fix Product Cache

**Problem:** `allProducts` array is empty or incomplete

**In Console:**
```javascript
// Check product cache
console.log(MMB_Admin.allProducts.length)
// Should show number > 0

// If 0, manually trigger search
MMB_Admin.searchProducts('')
```

**If search fails**, check Network tab for:
- 400/500 errors on `admin-ajax.php?action=mmb_search_products`
- Server timeouts (heavy sites)

**Solution: Increase Timeout**
Add to `wp-config.php`:
```php
define('WP_MEMORY_LIMIT', '256M');
set_time_limit(300);
```

---

### Fix CSS Conflicts

**Problem:** Elements render but are hidden by CSS

**Quick Test in Console:**
```javascript
// Make section visible
document.getElementById('mmb-selected-products-group').style.display = 'block';
document.getElementById('mmb-selected-products-group').style.visibility = 'visible';
document.getElementById('mmb-selected-products-group').style.opacity = '1';
```

**If that works**, there's a CSS conflict.

**Find the Conflict:**
1. Right-click the hidden area
2. Choose "Inspect Element"
3. Look for computed styles with `display: none` or `visibility: hidden`
4. Check which CSS file is applying it

**Solution: Add CSS Override**
Add to `assets/css/admin.css`:
```css
/* Force visibility - add at end of file */
#mmb-selected-products-group {
    display: block !important;
}

#mmb-selected-products-group[style*="display: none"] {
    display: none !important; /* Respect JS hide */
}
```

---

### Fix JavaScript Conflicts

**Problem:** Another plugin is breaking JavaScript

**Check Console for:**
- Any JS errors before MMB loads
- Errors from other plugins
- jQuery version conflicts

**Solution: Load MMB Last**
Add to your theme's `functions.php`:
```php
function mmb_load_last() {
    wp_dequeue_script('mix-match-admin');
    wp_enqueue_script('mix-match-admin', 
        plugins_url('assets/js/admin.js', __FILE__), 
        ['jquery', 'wp-api'], 
        '1.0.1', // Change version to force reload
        true 
    );
}
add_action('admin_enqueue_scripts', 'mmb_load_last', 999);
```

---

## Advanced Debugging

### Enable Verbose Logging

The updated `admin.js` now includes detailed logs:
- 🚀 Initialization
- 🎨 Rendering
- ✅ Success operations
- ❌ Errors
- ⚠️ Warnings

All operations are logged with emoji indicators for easy scanning.

### Manual Testing

```javascript
// In browser console:

// 1. Check if MMB is loaded
console.log(typeof MMB_Admin)  // Should be 'object'

// 2. Check elements
MMB_Admin.debugState()

// 3. Manually add a product
MMB_Admin.selectedProductsOrder.push(12345)
MMB_Admin.selectedProductIds.add(12345)
MMB_Admin.renderSelectedProducts()

// 4. Check if it renders
document.getElementById('mmb-selected-products-list').children.length
```

### Check File Versions

Make sure all files are updated:
```bash
# Check file modification dates
ls -la wp-content/plugins/mix-match-bundle/assets/js/admin.js
ls -la wp-content/plugins/mix-match-bundle/assets/css/admin.css  
ls -la wp-content/plugins/mix-match-bundle/admin/bundle-editor.php
```

All should have recent timestamps (today's date).

---

## Common Issues on Heavy Sites

### Issue 1: Slow AJAX Responses
**Symptom:** Products load but selected products appear after delay  
**Solution:** The code now handles this - just wait a bit longer

### Issue 2: Memory Limits
**Symptom:** Page stops responding or shows 500 error  
**Solution:** Increase PHP memory in `wp-config.php`:
```php
define('WP_MEMORY_LIMIT', '512M');
define('WP_MAX_MEMORY_LIMIT', '512M');
```

### Issue 3: Too Many Products
**Symptom:** Product search returns empty or incomplete  
**Solution:** Add pagination or limit in `mmb_search_products` (in main plugin file)

### Issue 4: Plugin Conflicts
**Symptom:** Works on local but not on live  
**Solution:** 
1. Deactivate all other plugins except WooCommerce
2. Test if it works
3. Reactivate plugins one by one to find conflict

---

## Still Not Working?

### Collect Debug Info

Run this in console and send output:
```javascript
// Collect all debug info
console.log('=== DEBUG INFO FOR SUPPORT ===');
console.log('WordPress Version:', document.querySelector('meta[name="generator"]')?.content);
console.log('PHP Version: Check phpinfo()');
MMB_Admin.debugState();
console.log('Window size:', window.innerWidth, 'x', window.innerHeight);
console.log('User agent:', navigator.userAgent);
console.log('=== END DEBUG INFO ===');
```

### Check Server Logs

Look at:
- `wp-content/debug.log` (if WP_DEBUG_LOG enabled)
- Apache/Nginx error logs
- PHP error logs

Search for:
- "MMB:"
- "mix-match"
- Fatal errors
- Memory exhausted

---

## Emergency Fallback

If nothing works, you can manually check products in the list without drag-and-drop sorting. The functionality will still work, just without the visual reordering interface. Products will be saved in the order you check them.

---

## Contact Support

If you've tried everything and it still doesn't work, provide:
1. Console logs (full output)
2. PHP version
3. WordPress version  
4. List of active plugins
5. Screenshots of issue
6. Server specs (if known)

