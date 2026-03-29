<?php
/**
 * MJ Frequently Bought Together - Main Module Class
 *
 * @author MJ Digital
 * @version 1.0.0
 * @license MIT
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Core\Module\WidgetInterface;

require_once __DIR__ . '/classes/FrequentlyBoughtAnalyzer.php';

class Mjfrequentlybought extends Module implements WidgetInterface
{
    public function __construct()
    {
        $this->name = 'mjfrequentlybought';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'MJ Digital';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => '8.99.99'];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('MJ - Spesso Comprati Insieme');
        $this->description = $this->l('Mostra i prodotti frequentemente acquistati insieme nella pagina prodotto.');
        $this->confirmUninstall = $this->l('Sei sicuro di voler disinstallare questo modulo? Tutte le associazioni verranno eliminate.');
    }

    public function install(): bool
    {
        // Generate cron token
        $cronToken = bin2hex(random_bytes(16));

        return parent::install()
            && $this->executeSqlFile('install')
            && $this->registerHook('displayProductAdditionalInfo')
            && $this->registerHook('displayFooterProduct')
            && $this->registerHook('displayReassurance')
            && $this->registerHook('actionValidateOrder')
            && $this->registerHook('actionAdminControllerSetMedia')
            && $this->registerHook('displayBackOfficeHeader')
            && $this->registerHook('displayWrapperTop')
            && Configuration::updateValue('MJ_FBT_ENABLED', 1)
            && Configuration::updateValue('MJ_FBT_MAX_PRODUCTS', 3)
            && Configuration::updateValue('MJ_FBT_MIN_ORDERS', 2)
            && Configuration::updateValue('MJ_FBT_VALID_STATES', '2,4,5')
            && Configuration::updateValue('MJ_FBT_EXCLUDE_OOS', 1)
            && Configuration::updateValue('MJ_FBT_SHOW_PRICES', 1)
            && Configuration::updateValue('MJ_FBT_SHOW_DISCOUNTS', 1)
            && Configuration::updateValue('MJ_FBT_BG_COLOR', '#e8f5f3')
            && Configuration::updateValue('MJ_FBT_CRON_TOKEN', $cronToken)
            && Configuration::updateValue('MJ_FBT_HOOK_POSITION', 'displayProductAdditionalInfo');
    }

    public function uninstall(): bool
    {
        return parent::uninstall()
            && $this->executeSqlFile('uninstall')
            && Configuration::deleteByName('MJ_FBT_ENABLED')
            && Configuration::deleteByName('MJ_FBT_MAX_PRODUCTS')
            && Configuration::deleteByName('MJ_FBT_MIN_ORDERS')
            && Configuration::deleteByName('MJ_FBT_VALID_STATES')
            && Configuration::deleteByName('MJ_FBT_EXCLUDE_OOS')
            && Configuration::deleteByName('MJ_FBT_SHOW_PRICES')
            && Configuration::deleteByName('MJ_FBT_SHOW_DISCOUNTS')
            && Configuration::deleteByName('MJ_FBT_BG_COLOR')
            && Configuration::deleteByName('MJ_FBT_CRON_TOKEN')
            && Configuration::deleteByName('MJ_FBT_HOOK_POSITION');
    }

    private function executeSqlFile(string $filename): bool
    {
        $filePath = __DIR__ . '/sql/' . $filename . '.sql';

        if (!file_exists($filePath)) {
            return false;
        }

        $sql = file_get_contents($filePath);
        $sql = str_replace('PREFIX_', _DB_PREFIX_, $sql);
        $queries = preg_split('/;\s*[\r\n]+/', $sql);

        foreach ($queries as $query) {
            $query = trim($query);
            if (!empty($query)) {
                if (!Db::getInstance()->execute($query)) {
                    return false;
                }
            }
        }

        return true;
    }

    public function getContent(): string
    {
        $output = '';

        // Handle AJAX rebuild request
        if (Tools::getValue('ajax') && Tools::getValue('action') === 'rebuildAssociations') {
            $this->processAjaxRebuild();
        }

        // Handle form submission
        if (Tools::isSubmit('submitMjFbtConfig')) {
            $output .= $this->postProcess();
        }

        $output .= $this->renderConfigForm();
        $output .= $this->renderRebuildPanel();

        return $output;
    }

    private function processAjaxRebuild(): void
    {
        $analyzer = new FrequentlyBoughtAnalyzer();
        $count = $analyzer->rebuildAllAssociations();

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => sprintf($this->l('%d associazioni ricostruite con successo.'), $count),
        ]);
        exit;
    }

    private function postProcess(): string
    {
        $errors = [];

        $enabled = (int) Tools::getValue('MJ_FBT_ENABLED');
        $maxProducts = (int) Tools::getValue('MJ_FBT_MAX_PRODUCTS');
        $minOrders = (int) Tools::getValue('MJ_FBT_MIN_ORDERS');
        $validStates = Tools::getValue('MJ_FBT_VALID_STATES');
        $excludeOos = (int) Tools::getValue('MJ_FBT_EXCLUDE_OOS');
        $showPrices = (int) Tools::getValue('MJ_FBT_SHOW_PRICES');
        $showDiscounts = (int) Tools::getValue('MJ_FBT_SHOW_DISCOUNTS');
        $bgColor = pSQL(Tools::getValue('MJ_FBT_BG_COLOR'));
        $hookPosition = pSQL(Tools::getValue('MJ_FBT_HOOK_POSITION'));

        if ($maxProducts < 1 || $maxProducts > 6) {
            $errors[] = $this->l('Il numero massimo di prodotti deve essere compreso tra 1 e 6.');
        }

        if ($minOrders < 1) {
            $errors[] = $this->l('Il numero minimo di ordini deve essere almeno 1.');
        }

        if (!empty($errors)) {
            return $this->displayError(implode('<br>', $errors));
        }

        // Handle multiselect valid states
        if (is_array($validStates)) {
            $validStates = implode(',', array_map('intval', $validStates));
        }

        Configuration::updateValue('MJ_FBT_ENABLED', $enabled);
        Configuration::updateValue('MJ_FBT_MAX_PRODUCTS', $maxProducts);
        Configuration::updateValue('MJ_FBT_MIN_ORDERS', $minOrders);
        Configuration::updateValue('MJ_FBT_VALID_STATES', $validStates);
        Configuration::updateValue('MJ_FBT_EXCLUDE_OOS', $excludeOos);
        Configuration::updateValue('MJ_FBT_SHOW_PRICES', $showPrices);
        Configuration::updateValue('MJ_FBT_SHOW_DISCOUNTS', $showDiscounts);
        Configuration::updateValue('MJ_FBT_BG_COLOR', $bgColor);
        Configuration::updateValue('MJ_FBT_HOOK_POSITION', $hookPosition);

        FrequentlyBoughtAnalyzer::clearCache();

        return $this->displayConfirmation($this->l('Impostazioni salvate con successo.'));
    }

    private function renderConfigForm(): string
    {
        $orderStates = OrderState::getOrderStates($this->context->language->id);
        $statesOptions = [];
        foreach ($orderStates as $state) {
            $statesOptions[] = [
                'id' => $state['id_order_state'],
                'name' => $state['name'],
            ];
        }

        $selectedStates = explode(',', Configuration::get('MJ_FBT_VALID_STATES') ?? '2,4,5');

        $hookOptions = [
            ['id' => 'displayProductAdditionalInfo', 'name' => 'displayProductAdditionalInfo'],
            ['id' => 'displayFooterProduct', 'name' => 'displayFooterProduct'],
            ['id' => 'displayReassurance', 'name' => 'displayReassurance'],
        ];

        $fields = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Configurazione'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'switch',
                        'label' => $this->l('Abilita modulo'),
                        'name' => 'MJ_FBT_ENABLED',
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'active_on', 'value' => 1, 'label' => $this->l('Si')],
                            ['id' => 'active_off', 'value' => 0, 'label' => $this->l('No')],
                        ],
                        'desc' => $this->l('Abilita o disabilita la sezione "Spesso comprati insieme".'),
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Numero massimo prodotti'),
                        'name' => 'MJ_FBT_MAX_PRODUCTS',
                        'options' => [
                            'query' => [
                                ['id' => 1, 'name' => '1'],
                                ['id' => 2, 'name' => '2'],
                                ['id' => 3, 'name' => '3'],
                                ['id' => 4, 'name' => '4'],
                                ['id' => 5, 'name' => '5'],
                                ['id' => 6, 'name' => '6'],
                            ],
                            'id' => 'id',
                            'name' => 'name',
                        ],
                        'desc' => $this->l('Numero massimo di prodotti da mostrare nella sezione (1-6).'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Numero minimo co-occorrenze'),
                        'name' => 'MJ_FBT_MIN_ORDERS',
                        'class' => 'fixed-width-sm',
                        'desc' => $this->l('Numero minimo di volte che due prodotti devono essere stati acquistati insieme per mostrare l\'associazione.'),
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Stati ordine validi'),
                        'name' => 'MJ_FBT_VALID_STATES[]',
                        'multiple' => true,
                        'size' => 6,
                        'options' => [
                            'query' => $statesOptions,
                            'id' => 'id',
                            'name' => 'name',
                        ],
                        'desc' => $this->l('Seleziona gli stati ordine da considerare per l\'analisi (tieni premuto Ctrl per selezione multipla).'),
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Escludi prodotti fuori stock'),
                        'name' => 'MJ_FBT_EXCLUDE_OOS',
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'oos_on', 'value' => 1, 'label' => $this->l('Si')],
                            ['id' => 'oos_off', 'value' => 0, 'label' => $this->l('No')],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Mostra prezzi'),
                        'name' => 'MJ_FBT_SHOW_PRICES',
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'price_on', 'value' => 1, 'label' => $this->l('Si')],
                            ['id' => 'price_off', 'value' => 0, 'label' => $this->l('No')],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Mostra prezzo scontato'),
                        'name' => 'MJ_FBT_SHOW_DISCOUNTS',
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'disc_on', 'value' => 1, 'label' => $this->l('Si')],
                            ['id' => 'disc_off', 'value' => 0, 'label' => $this->l('No')],
                        ],
                        'desc' => $this->l('Mostra il prezzo originale barrato se il prodotto e\' in sconto.'),
                    ],
                    [
                        'type' => 'color',
                        'label' => $this->l('Colore sfondo sezione'),
                        'name' => 'MJ_FBT_BG_COLOR',
                        'desc' => $this->l('Colore di sfondo della sezione "Spesso comprati insieme".'),
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Posizione hook'),
                        'name' => 'MJ_FBT_HOOK_POSITION',
                        'options' => [
                            'query' => $hookOptions,
                            'id' => 'id',
                            'name' => 'name',
                        ],
                        'desc' => $this->l('Seleziona l\'hook in cui mostrare la sezione.'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Token Cron'),
                        'name' => 'MJ_FBT_CRON_TOKEN',
                        'readonly' => true,
                        'class' => 'fixed-width-xxl',
                        'desc' => $this->l('Token per l\'URL cron (generato automaticamente). Non modificabile.'),
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Salva'),
                    'class' => 'btn btn-default pull-right',
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');
        $helper->allow_employee_form_lang = (int) Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');
        $helper->title = $this->displayName;
        $helper->show_toolbar = false;
        $helper->submit_action = 'submitMjFbtConfig';

        $helper->fields_value = [
            'MJ_FBT_ENABLED' => Configuration::get('MJ_FBT_ENABLED'),
            'MJ_FBT_MAX_PRODUCTS' => Configuration::get('MJ_FBT_MAX_PRODUCTS'),
            'MJ_FBT_MIN_ORDERS' => Configuration::get('MJ_FBT_MIN_ORDERS'),
            'MJ_FBT_VALID_STATES[]' => $selectedStates,
            'MJ_FBT_EXCLUDE_OOS' => Configuration::get('MJ_FBT_EXCLUDE_OOS'),
            'MJ_FBT_SHOW_PRICES' => Configuration::get('MJ_FBT_SHOW_PRICES'),
            'MJ_FBT_SHOW_DISCOUNTS' => Configuration::get('MJ_FBT_SHOW_DISCOUNTS'),
            'MJ_FBT_BG_COLOR' => Configuration::get('MJ_FBT_BG_COLOR'),
            'MJ_FBT_HOOK_POSITION' => Configuration::get('MJ_FBT_HOOK_POSITION'),
            'MJ_FBT_CRON_TOKEN' => Configuration::get('MJ_FBT_CRON_TOKEN'),
        ];

        return $helper->generateForm([$fields]);
    }

    private function renderRebuildPanel(): string
    {
        $cronToken = Configuration::get('MJ_FBT_CRON_TOKEN');
        $cronUrl = $this->context->link->getModuleLink($this->name, 'cron', ['token' => $cronToken]);

        $this->context->smarty->assign([
            'mjfbt_cron_url' => $cronUrl,
            'mjfbt_admin_token' => Tools::getAdminTokenLite('AdminModules'),
            'mjfbt_admin_ajax_url' => AdminController::$currentIndex . '&configure=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules'),
        ]);

        return $this->context->smarty->fetch($this->getLocalPath() . 'views/templates/admin/configure.tpl');
    }

    // --- Frontend Hooks ---

    public function hookDisplayProductAdditionalInfo(array $params): string
    {
        $hookPosition = Configuration::get('MJ_FBT_HOOK_POSITION') ?? 'displayProductAdditionalInfo';
        if ($hookPosition !== 'displayProductAdditionalInfo') {
            return '';
        }

        return $this->renderFbtSection($params);
    }

    public function hookDisplayFooterProduct(array $params): string
    {
        $hookPosition = Configuration::get('MJ_FBT_HOOK_POSITION') ?? 'displayProductAdditionalInfo';
        if ($hookPosition !== 'displayFooterProduct') {
            return '';
        }

        return $this->renderFbtSection($params);
    }

    public function hookDisplayReassurance(array $params): string
    {
        $hookPosition = Configuration::get('MJ_FBT_HOOK_POSITION') ?? 'displayProductAdditionalInfo';
        if ($hookPosition !== 'displayReassurance') {
            return '';
        }

        return $this->renderFbtSection($params);
    }

    public function renderWidget($hookName, array $configuration): string
    {
        return $this->renderFbtSection($configuration);
    }

    public function getWidgetVariables($hookName, array $configuration): array
    {
        return [];
    }

    private function renderFbtSection(array $params): string
    {
        if (!(int) Configuration::get('MJ_FBT_ENABLED')) {
            return '';
        }

        $product = $params['product'] ?? null;
        if (is_array($product)) {
            $idProduct = (int) ($product['id_product'] ?? 0);
        } elseif (is_object($product)) {
            $idProduct = (int) ($product->id ?? $product->id_product ?? 0);
        } else {
            $idProduct = (int) Tools::getValue('id_product');
        }

        if (!$idProduct) {
            return '';
        }

        $idShop = (int) $this->context->shop->id;
        $idLang = (int) $this->context->language->id;

        $analyzer = new FrequentlyBoughtAnalyzer();
        $products = $analyzer->getAssociatedProducts($idProduct, $idShop, $idLang);

        if (empty($products)) {
            return '';
        }

        $bgColor = Configuration::get('MJ_FBT_BG_COLOR') ?? '#e8f5f3';
        $ajaxUrl = $this->context->link->getModuleLink($this->name, 'ajax');

        $this->context->controller->registerStylesheet(
            'mjfbt-front-css',
            'modules/' . $this->name . '/views/css/front.css',
            ['media' => 'all', 'priority' => 200]
        );

        $this->context->controller->registerJavascript(
            'mjfbt-front-js',
            'modules/' . $this->name . '/views/js/front.js',
            ['position' => 'bottom', 'priority' => 200]
        );

        $this->context->smarty->assign([
            'mjfbt_products' => $products,
            'mjfbt_bg_color' => $bgColor,
            'mjfbt_ajax_url' => $ajaxUrl,
        ]);

        return $this->display(__FILE__, 'views/templates/hook/displayProductAdditionalInfo.tpl');
    }

    // --- Backend Hooks ---

    public function hookActionValidateOrder(array $params): void
    {
        $order = $params['order'] ?? null;

        if (!$order || !Validate::isLoadedObject($order)) {
            return;
        }

        $orderDetails = $order->getOrderDetailList();
        $productIds = [];

        foreach ($orderDetails as $detail) {
            $productId = (int) $detail['product_id'];
            if ($productId > 0 && !in_array($productId, $productIds, true)) {
                $productIds[] = $productId;
            }
        }

        if (count($productIds) >= 2) {
            $analyzer = new FrequentlyBoughtAnalyzer();
            $analyzer->updateAssociationsFromOrder($productIds);
        }
    }

    public function hookActionAdminControllerSetMedia(array $params): void
    {
        // Admin media if needed
    }

    public function hookDisplayBackOfficeHeader(array $params): string
    {
        return '';
    }

    public function isUsingNewTranslationSystem(): bool
    {
        return false;
    }
}
