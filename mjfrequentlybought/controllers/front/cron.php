<?php
/**
 * CRON Controller per il rebuild delle associazioni.
 *
 * @author MJ Digital
 * @version 1.0.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class MjfrequentlyboughtCronModuleFrontController extends ModuleFrontController
{
    public $auth = false;

    public function initContent(): void
    {
        parent::initContent();

        $token = Tools::getValue('token');
        $savedToken = Configuration::get('MJ_FBT_CRON_TOKEN');

        if (empty($token) || $token !== $savedToken) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Invalid token']);
            exit;
        }

        if (!class_exists('FrequentlyBoughtAnalyzer')) {
            require_once _PS_MODULE_DIR_ . 'mjfrequentlybought/classes/FrequentlyBoughtAnalyzer.php';
        }

        $analyzer = new FrequentlyBoughtAnalyzer();
        $count = $analyzer->rebuildAllAssociations();

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Associations rebuilt successfully',
            'associations_count' => $count,
        ]);
        exit;
    }
}
