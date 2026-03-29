<?php
/**
 * FrequentlyBoughtAnalyzer - Analizza gli ordini storici per trovare prodotti acquistati insieme.
 *
 * @author MJ Digital
 * @version 1.0.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class FrequentlyBoughtAnalyzer
{
    private static array $cache = [];

    public function getAssociatedProducts(int $idProduct, int $idShop, int $idLang): array
    {
        $cacheKey = $idProduct . '_' . $idShop . '_' . $idLang;

        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        $enabled = (int) Configuration::get('MJ_FBT_ENABLED');
        if (!$enabled) {
            return [];
        }

        $maxProducts = (int) Configuration::get('MJ_FBT_MAX_PRODUCTS') ?: 3;
        $minOrders = (int) Configuration::get('MJ_FBT_MIN_ORDERS') ?: 2;
        $excludeOos = (int) Configuration::get('MJ_FBT_EXCLUDE_OOS');
        $showPrices = (int) Configuration::get('MJ_FBT_SHOW_PRICES');
        $showDiscounts = (int) Configuration::get('MJ_FBT_SHOW_DISCOUNTS');

        // Check manual associations first
        $manualProducts = $this->getManualAssociations($idProduct, $idShop, $idLang, $maxProducts, $excludeOos);

        if (!empty($manualProducts)) {
            $result = $this->enrichProducts($manualProducts, $idLang, $idShop, $showPrices, $showDiscounts);
            self::$cache[$cacheKey] = $result;
            return $result;
        }

        // Automatic associations from order history
        $autoProducts = $this->getAutomaticAssociations($idProduct, $idShop, $maxProducts, $minOrders, $excludeOos);

        $result = $this->enrichProducts($autoProducts, $idLang, $idShop, $showPrices, $showDiscounts);
        self::$cache[$cacheKey] = $result;

        return $result;
    }

    private function getManualAssociations(int $idProduct, int $idShop, int $idLang, int $limit, bool $excludeOos): array
    {
        $sql = 'SELECT m.`id_product_associated` AS `id_product`
                FROM `' . _DB_PREFIX_ . 'mj_fbt_manual` m
                INNER JOIN `' . _DB_PREFIX_ . 'product` p
                    ON p.`id_product` = m.`id_product_associated`
                    AND p.`active` = 1
                INNER JOIN `' . _DB_PREFIX_ . 'product_shop` ps
                    ON ps.`id_product` = p.`id_product`
                    AND ps.`id_shop` = ' . $idShop . '
                    AND ps.`active` = 1
                    AND ps.`visibility` IN ("both", "catalog")
                WHERE m.`id_product` = ' . $idProduct . '
                AND m.`active` = 1';

        if ($excludeOos) {
            $sql .= ' AND (
                SELECT COALESCE(SUM(sa.`quantity`), 0)
                FROM `' . _DB_PREFIX_ . 'stock_available` sa
                WHERE sa.`id_product` = p.`id_product`
                AND sa.`id_product_attribute` = 0
                AND sa.`id_shop` = ' . $idShop . '
            ) > 0';
        }

        $sql .= ' ORDER BY m.`position` ASC LIMIT ' . $limit;

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql) ?? [];
    }

    private function getAutomaticAssociations(int $idProduct, int $idShop, int $limit, int $minOrders, bool $excludeOos): array
    {
        $validStates = Configuration::get('MJ_FBT_VALID_STATES') ?? '2,4,5';
        $validStatesArray = array_map('intval', explode(',', $validStates));
        $validStatesStr = implode(',', $validStatesArray);

        // First try from pre-computed associations table
        $sql = 'SELECT a.`id_product_associated` AS `id_product`, a.`times_bought_together`
                FROM `' . _DB_PREFIX_ . 'mj_fbt_associations` a
                INNER JOIN `' . _DB_PREFIX_ . 'product` p
                    ON p.`id_product` = a.`id_product_associated`
                    AND p.`active` = 1
                INNER JOIN `' . _DB_PREFIX_ . 'product_shop` ps
                    ON ps.`id_product` = p.`id_product`
                    AND ps.`id_shop` = ' . $idShop . '
                    AND ps.`active` = 1
                    AND ps.`visibility` IN ("both", "catalog")
                WHERE a.`id_product` = ' . $idProduct . '
                AND a.`times_bought_together` >= ' . $minOrders;

        if ($excludeOos) {
            $sql .= ' AND (
                SELECT COALESCE(SUM(sa.`quantity`), 0)
                FROM `' . _DB_PREFIX_ . 'stock_available` sa
                WHERE sa.`id_product` = p.`id_product`
                AND sa.`id_product_attribute` = 0
                AND sa.`id_shop` = ' . $idShop . '
            ) > 0';
        }

        $sql .= ' ORDER BY a.`times_bought_together` DESC LIMIT ' . $limit;

        $results = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql) ?? [];

        if (!empty($results)) {
            return $results;
        }

        // Fallback: real-time query on orders
        $sql = 'SELECT od2.`product_id` AS `id_product`, COUNT(*) AS `times_bought_together`
                FROM `' . _DB_PREFIX_ . 'order_detail` od1
                INNER JOIN `' . _DB_PREFIX_ . 'order_detail` od2
                    ON od1.`id_order` = od2.`id_order`
                    AND od1.`product_id` != od2.`product_id`
                INNER JOIN `' . _DB_PREFIX_ . 'orders` o
                    ON o.`id_order` = od1.`id_order`
                    AND o.`current_state` IN (' . $validStatesStr . ')
                    AND o.`id_shop` = ' . $idShop . '
                INNER JOIN `' . _DB_PREFIX_ . 'product` p
                    ON p.`id_product` = od2.`product_id`
                    AND p.`active` = 1
                INNER JOIN `' . _DB_PREFIX_ . 'product_shop` ps
                    ON ps.`id_product` = p.`id_product`
                    AND ps.`id_shop` = ' . $idShop . '
                    AND ps.`active` = 1
                    AND ps.`visibility` IN ("both", "catalog")
                WHERE od1.`product_id` = ' . $idProduct;

        if ($excludeOos) {
            $sql .= ' AND (
                SELECT COALESCE(SUM(sa.`quantity`), 0)
                FROM `' . _DB_PREFIX_ . 'stock_available` sa
                WHERE sa.`id_product` = p.`id_product`
                AND sa.`id_product_attribute` = 0
                AND sa.`id_shop` = ' . $idShop . '
            ) > 0';
        }

        $sql .= ' GROUP BY od2.`product_id`
                  HAVING `times_bought_together` >= ' . $minOrders . '
                  ORDER BY `times_bought_together` DESC
                  LIMIT ' . $limit;

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql) ?? [];
    }

    private function enrichProducts(array $products, int $idLang, int $idShop, bool $showPrices, bool $showDiscounts): array
    {
        $enriched = [];
        $link = Context::getContext()->link;

        foreach ($products as $row) {
            $idProduct = (int) $row['id_product'];
            $product = new Product($idProduct, false, $idLang, $idShop);

            if (!Validate::isLoadedObject($product)) {
                continue;
            }

            $cover = Product::getCover($idProduct);
            $imageId = $cover['id_image'] ?? null;
            $imageUrl = '';

            if ($imageId) {
                $rewrite = is_array($product->link_rewrite) ? ($product->link_rewrite[$idLang] ?? reset($product->link_rewrite)) : $product->link_rewrite;
                $imageUrl = $link->getImageLink($rewrite, (int) $imageId, 'home_default');
            }

            $specificPriceOutput = null;
            $priceWithTax = Product::getPriceStatic($idProduct, true, null, 6, null, false, true, 1, false, null, null, null, $specificPriceOutput);
            $priceWithoutReduction = Product::getPriceStatic($idProduct, true, null, 6, null, false, false);

            $hasDiscount = $priceWithoutReduction > $priceWithTax;

            $productUrl = $link->getProductLink($idProduct, $product->link_rewrite, null, null, $idLang, $idShop);

            $priceFormatted = Tools::displayPrice($priceWithTax);

            $item = [
                'id_product' => $idProduct,
                'id_product_attribute' => 0,
                'name' => $product->name,
                'link' => $productUrl,
                'image_url' => $imageUrl,
                'price_amount' => round($priceWithTax, 2),
                'price' => $priceFormatted,
                'show_price' => (bool) $showPrices,
            ];

            if ($showDiscounts && $hasDiscount) {
                $item['price_original'] = Tools::displayPrice($priceWithoutReduction);
                $item['price_original_amount'] = round($priceWithoutReduction, 2);
                $item['has_discount'] = true;
            } else {
                $item['has_discount'] = false;
                $item['price_original'] = '';
                $item['price_original_amount'] = 0;
            }

            if (isset($row['times_bought_together'])) {
                $item['times_bought_together'] = (int) $row['times_bought_together'];
            }

            $enriched[] = $item;
        }

        return $enriched;
    }

    public function updateAssociationsFromOrder(array $productIds): void
    {
        if (count($productIds) < 2) {
            return;
        }

        // Ensure all IDs are integers
        $productIds = array_map('intval', $productIds);
        $now = date('Y-m-d H:i:s');

        foreach ($productIds as $idA) {
            foreach ($productIds as $idB) {
                if ($idA === $idB) {
                    continue;
                }

                $existing = Db::getInstance()->getValue(
                    'SELECT `id_association`
                     FROM `' . _DB_PREFIX_ . 'mj_fbt_associations`
                     WHERE `id_product` = ' . $idA . '
                     AND `id_product_associated` = ' . $idB
                );

                if ($existing) {
                    Db::getInstance()->execute(
                        'UPDATE `' . _DB_PREFIX_ . 'mj_fbt_associations`
                         SET `times_bought_together` = `times_bought_together` + 1,
                             `date_upd` = "' . pSQL($now) . '"
                         WHERE `id_association` = ' . (int) $existing
                    );
                } else {
                    Db::getInstance()->execute(
                        'INSERT INTO `' . _DB_PREFIX_ . 'mj_fbt_associations`
                         (`id_product`, `id_product_associated`, `times_bought_together`, `date_add`, `date_upd`)
                         VALUES (' . $idA . ', ' . $idB . ', 1, "' . pSQL($now) . '", "' . pSQL($now) . '")'
                    );
                }
            }
        }

        // Invalidate cache
        self::$cache = [];
    }

    public function rebuildAllAssociations(): int
    {
        $validStates = Configuration::get('MJ_FBT_VALID_STATES') ?? '2,4,5';
        $validStatesArray = array_map('intval', explode(',', $validStates));
        $validStatesStr = implode(',', $validStatesArray);

        // Truncate existing associations
        Db::getInstance()->execute('TRUNCATE TABLE `' . _DB_PREFIX_ . 'mj_fbt_associations`');

        $now = date('Y-m-d H:i:s');

        // Rebuild from all valid orders
        $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'mj_fbt_associations`
                    (`id_product`, `id_product_associated`, `times_bought_together`, `date_add`, `date_upd`)
                SELECT
                    od1.`product_id`,
                    od2.`product_id`,
                    COUNT(*),
                    "' . pSQL($now) . '",
                    "' . pSQL($now) . '"
                FROM `' . _DB_PREFIX_ . 'order_detail` od1
                INNER JOIN `' . _DB_PREFIX_ . 'order_detail` od2
                    ON od1.`id_order` = od2.`id_order`
                    AND od1.`product_id` != od2.`product_id`
                INNER JOIN `' . _DB_PREFIX_ . 'orders` o
                    ON o.`id_order` = od1.`id_order`
                    AND o.`current_state` IN (' . $validStatesStr . ')
                INNER JOIN `' . _DB_PREFIX_ . 'product` p
                    ON p.`id_product` = od2.`product_id`
                    AND p.`active` = 1
                GROUP BY od1.`product_id`, od2.`product_id`';

        Db::getInstance()->execute($sql);

        $count = (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'mj_fbt_associations`'
        );

        // Invalidate cache
        self::$cache = [];

        return $count;
    }

    public static function clearCache(): void
    {
        self::$cache = [];
    }
}
