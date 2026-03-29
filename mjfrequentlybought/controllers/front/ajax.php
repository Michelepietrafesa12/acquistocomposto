<?php
/**
 * AJAX Controller per il modulo MJ Frequently Bought Together.
 *
 * @author MJ Digital
 * @version 1.0.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class MjfrequentlyboughtAjaxModuleFrontController extends ModuleFrontController
{
    public function initContent(): void
    {
        parent::initContent();

        $action = Tools::getValue('action');

        switch ($action) {
            case 'addToCart':
                $this->processAddToCart();
                break;
            default:
                $this->ajaxResponse(['success' => false, 'message' => 'Invalid action'], 400);
                break;
        }
    }

    private function processAddToCart(): void
    {
        $productsJson = Tools::getValue('products');

        if (empty($productsJson)) {
            $this->ajaxResponse(['success' => false, 'message' => $this->module->l('No products provided', 'ajax')], 400);
            return;
        }

        $products = json_decode($productsJson, true);

        if (!is_array($products) || empty($products)) {
            $this->ajaxResponse(['success' => false, 'message' => $this->module->l('Invalid products data', 'ajax')], 400);
            return;
        }

        // Rate limiting: max 10 products per request
        if (count($products) > 10) {
            $this->ajaxResponse(['success' => false, 'message' => $this->module->l('Too many products (max 10)', 'ajax')], 400);
            return;
        }

        $cart = $this->context->cart;

        // Ensure cart exists
        if (!$cart || !$cart->id) {
            if ($this->context->cookie->id_cart) {
                $cart = new Cart($this->context->cookie->id_cart);
            }

            if (!$cart || !$cart->id) {
                $cart = new Cart();
                $cart->id_currency = $this->context->currency->id;
                $cart->id_lang = $this->context->language->id;
                $cart->id_shop = $this->context->shop->id;

                if ($this->context->customer->id) {
                    $cart->id_customer = $this->context->customer->id;
                    $cart->id_address_delivery = (int) Address::getFirstCustomerAddressId($this->context->customer->id);
                    $cart->id_address_invoice = $cart->id_address_delivery;
                }

                $cart->secure_key = $this->context->customer->secure_key ?? md5(uniqid((string) rand(), true));
                $cart->add();
                $this->context->cart = $cart;
                $this->context->cookie->id_cart = (int) $cart->id;
                $this->context->cookie->write();
            }
        }

        // Verify cart belongs to customer
        if ($this->context->customer->id && $cart->id_customer != $this->context->customer->id) {
            $this->ajaxResponse(['success' => false, 'message' => $this->module->l('Cart error', 'ajax')], 403);
            return;
        }

        $addedProducts = [];
        $errors = [];

        foreach ($products as $product) {
            $idProduct = (int) ($product['id_product'] ?? 0);
            $idProductAttribute = (int) ($product['id_product_attribute'] ?? 0);

            if ($idProduct <= 0) {
                continue;
            }

            // Verify product exists and is active
            $productObj = new Product($idProduct, false, $this->context->language->id);
            if (!Validate::isLoadedObject($productObj) || !$productObj->active) {
                $errors[] = sprintf($this->module->l('Product %d is not available', 'ajax'), $idProduct);
                continue;
            }

            // Check stock
            if (!Product::isAvailableWhenOutOfStock($productObj->out_of_stock)) {
                $quantity = StockAvailable::getQuantityAvailableByProduct($idProduct, $idProductAttribute);
                if ($quantity <= 0) {
                    $errors[] = sprintf($this->module->l('Product %s is out of stock', 'ajax'), $productObj->name);
                    continue;
                }
            }

            $result = $cart->updateQty(1, $idProduct, $idProductAttribute);

            if ($result === true || $result > 0) {
                $addedProducts[] = [
                    'id_product' => $idProduct,
                    'name' => $productObj->name,
                ];
            } else {
                $errors[] = sprintf($this->module->l('Could not add %s to cart', 'ajax'), $productObj->name);
            }
        }

        if (empty($addedProducts) && !empty($errors)) {
            $this->ajaxResponse([
                'success' => false,
                'message' => implode(', ', $errors),
            ], 200);
            return;
        }

        // Get updated cart summary
        $cartTotal = $cart->getOrderTotal(true, Cart::BOTH);
        $cartCount = $cart->nbProducts();

        $this->ajaxResponse([
            'success' => true,
            'message' => sprintf(
                $this->module->l('%d product(s) added to cart', 'ajax'),
                count($addedProducts)
            ),
            'added_products' => $addedProducts,
            'errors' => $errors,
            'cart' => [
                'products_count' => $cartCount,
                'total' => Tools::displayPrice($cartTotal),
            ],
        ]);
    }

    private function ajaxResponse(array $data, int $httpCode = 200): void
    {
        if ($httpCode !== 200) {
            http_response_code($httpCode);
        }

        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
