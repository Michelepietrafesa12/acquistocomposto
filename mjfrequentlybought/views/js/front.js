/**
 * MJ Frequently Bought Together - Frontend JavaScript
 *
 * @author MJ Digital
 * @version 1.0.0
 */
document.addEventListener('DOMContentLoaded', function () {
    var container = document.querySelector('.mjfbt-container');
    if (!container) {
        return;
    }

    var checkboxes = container.querySelectorAll('.mjfbt-checkbox');
    var totalValueEl = container.querySelector('.mjfbt-total-value');
    var addToCartBtn = container.querySelector('.mjfbt-add-to-cart');
    var btnOriginalText = addToCartBtn ? addToCartBtn.textContent : '';
    var successMsg = container.querySelector('.mjfbt-success-message');
    var errorMsg = container.querySelector('.mjfbt-error-message');
    var ajaxUrl = container.dataset.ajaxUrl || '';

    function updateTotal() {
        var total = 0;
        var selectedCount = 0;

        checkboxes.forEach(function (cb) {
            var card = cb.closest('.mjfbt-product-card');
            if (cb.checked) {
                total += parseFloat(card.dataset.price) || 0;
                selectedCount++;
                card.classList.add('selected');
            } else {
                card.classList.remove('selected');
            }
        });

        if (totalValueEl) {
            totalValueEl.textContent = formatPrice(total);
        }

        if (addToCartBtn) {
            addToCartBtn.disabled = selectedCount === 0;
        }
    }

    function formatPrice(amount) {
        // Use the prestashop currency format if available
        if (typeof prestashop !== 'undefined' && prestashop.currency) {
            var formatted = amount.toFixed(prestashop.currency.precision || 2);
            formatted = formatted.replace('.', prestashop.currency.decimal || ',');

            if (prestashop.currency.sign) {
                if (prestashop.currency.format === 2 || prestashop.currency.iso_code === 'EUR') {
                    return formatted + '\u00A0' + prestashop.currency.sign;
                }
                return prestashop.currency.sign + '\u00A0' + formatted;
            }
        }
        // Default EUR format
        return amount.toFixed(2).replace('.', ',') + '\u00A0\u20AC';
    }

    function getSelectedProducts() {
        var products = [];
        checkboxes.forEach(function (cb) {
            if (cb.checked) {
                var card = cb.closest('.mjfbt-product-card');
                products.push({
                    id_product: parseInt(card.dataset.idProduct, 10),
                    id_product_attribute: parseInt(card.dataset.idProductAttribute, 10) || 0
                });
            }
        });
        return products;
    }

    function showMessage(el, text) {
        if (!el) return;
        el.textContent = text;
        el.style.display = 'block';
        setTimeout(function () {
            el.style.display = 'none';
        }, 5000);
    }

    function addToCart() {
        var products = getSelectedProducts();
        if (products.length === 0) {
            return;
        }

        // Hide any previous messages
        if (successMsg) successMsg.style.display = 'none';
        if (errorMsg) errorMsg.style.display = 'none';

        // Show loading
        addToCartBtn.disabled = true;
        addToCartBtn.innerHTML = '<span class="mjfbt-spinner"></span> ' + btnOriginalText;

        var formData = new FormData();
        formData.append('action', 'addToCart');
        formData.append('products', JSON.stringify(products));
        formData.append('ajax', '1');

        fetch(ajaxUrl, {
            method: 'POST',
            body: formData,
        })
        .then(function (response) {
            return response.json();
        })
        .then(function (data) {
            addToCartBtn.innerHTML = btnOriginalText;
            addToCartBtn.disabled = false;

            if (data.success) {
                showMessage(successMsg, data.message);

                // Trigger PrestaShop cart update event
                if (typeof prestashop !== 'undefined') {
                    prestashop.emit('updateCart', {
                        reason: {
                            idProduct: products[0].id_product,
                            idProductAttribute: products[0].id_product_attribute,
                            linkAction: 'add-to-cart'
                        }
                    });
                }

                // Uncheck all checkboxes
                checkboxes.forEach(function (cb) {
                    cb.checked = false;
                });
                updateTotal();
            } else {
                showMessage(errorMsg, data.message || 'An error occurred');
            }
        })
        .catch(function (error) {
            addToCartBtn.innerHTML = btnOriginalText;
            addToCartBtn.disabled = false;
            showMessage(errorMsg, 'Network error. Please try again.');
        });
    }

    // Event listeners
    checkboxes.forEach(function (cb) {
        cb.addEventListener('change', updateTotal);
    });

    if (addToCartBtn) {
        addToCartBtn.addEventListener('click', function (e) {
            e.preventDefault();
            addToCart();
        });
    }

    // Initialize
    updateTotal();
});
