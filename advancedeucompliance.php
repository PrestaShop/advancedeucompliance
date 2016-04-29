<?php
/**
 * 2007-2016 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author     PrestaShop SA <contact@prestashop.com>
 * @copyright  2007-2016 PrestaShop SA
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

/* Include required entities */
include_once dirname(__FILE__) . '/entities/AeucCMSRoleEmailEntity.php';
include_once dirname(__FILE__) . '/entities/AeucEmailEntity.php';

class Advancedeucompliance extends Module
{
    /* Class members */
    protected   $config_form = false;
    protected   $entity_manager;
    protected   $filesystem;
    protected   $emails;
    private     $missing_templates = array();
    protected   $_errors;
    protected   $_warnings;

    /* Constants used for LEGAL/CMS Management */
    const LEGAL_NO_ASSOC        = 'NO_ASSOC';
    const LEGAL_NOTICE          = 'LEGAL_NOTICE';
    const LEGAL_CONDITIONS      = 'LEGAL_CONDITIONS';
    const LEGAL_REVOCATION      = 'LEGAL_REVOCATION';
    const LEGAL_REVOCATION_FORM = 'LEGAL_REVOCATION_FORM';
    const LEGAL_PRIVACY         = 'LEGAL_PRIVACY';
    const LEGAL_ENVIRONMENTAL   = 'LEGAL_ENVIRONMENTAL';
    const LEGAL_SHIP_PAY        = 'LEGAL_SHIP_PAY';

    const DEFAULT_PS_PRODUCT_WEIGHT_PRECISION = 2;

    public function __construct(Core_Foundation_Database_EntityManager $entity_manager,
                                Core_Foundation_FileSystem_FileSystem $fs,
                                Core_Business_Email_EmailLister $email)
    {

        $this->name = 'advancedeucompliance';
        $this->tab = 'administration';
        $this->version = '2.0.2';
        $this->author = 'PrestaShop';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        /* Register dependencies to module */
        $this->entity_manager = $entity_manager;
        $this->filesystem = $fs;
        $this->emails = $email;

        $this->displayName = $this->l('Advanced EU Compliance');
        $this->description = $this->l('This module helps European merchants comply with applicable e-commerce laws.');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall this module?');

        /* Init errors var */
        $this->_errors = array();
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        $return = parent::install() &&
                  $this->loadTables() &&
                  $this->installHooks() &&
                  $this->registerModulesBackwardCompatHook() &&
                  $this->registerHook('header') &&
                  $this->registerHook('displayProductPriceBlock') &&
                  $this->registerHook('overrideTOSDisplay') &&
                  $this->registerHook('actionEmailAddAfterContent') &&
                  $this->registerHook('advancedPaymentOptions') &&
                  $this->registerHook('displayAfterShoppingCartBlock') &&
                  $this->registerHook('displayBeforeShoppingCartBlock') &&
                  $this->registerHook('displayCartTotalPriceLabel') &&
                  $this->createConfig();

        $this->emptyTemplatesCache();

        return (bool)$return;
    }

    public function isThemeCompliant()
    {
        $return = true;

        foreach ($this->getRequiredThemeTemplate() as $required_tpl) {

            if (!is_file(_PS_THEME_DIR_ . $required_tpl)) {
                $this->missing_templates[] = $required_tpl;
                $return = false;
            }
        }

        return $return;
    }

    public function getRequiredThemeTemplate()
    {
        return array(
            'order-address-advanced.tpl',
            'order-carrier-advanced.tpl',
            'order-carrier-opc-advanced.tpl',
            'order-opc-advanced.tpl',
            'order-opc-new-account-advanced.tpl',
            'order-payment-advanced.tpl',
            'shopping-cart-advanced.tpl'
        );
    }

    public function uninstall()
    {
        return parent::uninstall() &&
               $this->dropConfig() &&
               $this->unloadTables();
    }

    public function disable($force_all = false)
    {
        $is_adv_api_disabled = (bool)Configuration::updateValue('PS_ADVANCED_PAYMENT_API', false);
        $is_adv_api_disabled &= (bool)Configuration::updateValue('PS_ATCP_SHIPWRAP', false);
        return parent::disable() && $is_adv_api_disabled;
    }

    public function registerModulesBackwardCompatHook()
    {
        $return = true;
        $module_to_check = array(
            'bankwire', 'cheque', 'paypal',
            'adyen', 'hipay', 'cashondelivery', 'sofortbanking',
            'pigmbhpaymill', 'ogone', 'moneybookers',
            'syspay'
        );
        $display_payment_eu_hook_id = (int)Hook::getIdByName('displayPaymentEu');
        $already_hooked_modules_ids = array_keys(Hook::getModulesFromHook($display_payment_eu_hook_id));

        foreach ($module_to_check as $module_name) {

            if (($module = Module::getInstanceByName($module_name)) !== false &&
                Module::isInstalled($module_name) &&
                $module->active &&
                !in_array($module->id, $already_hooked_modules_ids) &&
                !$module->isRegisteredInHook('displayPaymentEu') ) {

                    $return &= $module->registerHook('displayPaymentEu');
            }
        }

        return $return;
    }

    public function installHooks()
    {
        $hooks = array(
            'displayBeforeShoppingCartBlock' => array(
                'name'      => 'display before Shopping cart block',
                'description' => 'Display content after Shopping Cart'
            ),
            'displayAfterShoppingCartBlock'  => array(
                'name'      => 'display after Shopping cart block',
                'description' => 'Display content after Shopping Cart'
            ),
            'displayPaymentEu'  => array(
                'name'      => 'Display EU payment options (helper)',
                'description' => 'Hook to display payment options'
            )
        );

        $return = true;

        foreach ($hooks as $hook_name => $hook) {

            if (Hook::getIdByName($hook_name)) {
                continue;
            }

            $new_hook = new Hook();
            $new_hook->name = $hook_name;
            $new_hook->title = $hook_name;
            $new_hook->description = $hook['description'];
            $new_hook->position = true;
            $new_hook->live_edit = false;

            if (!$new_hook->add()) {
                $return &= false;
                $this->_errors[] = $this->l('Could not install new hook', 'advancedeucompliance') . ': ' . $hook_name;
            }

        }

        return $return;
    }

    public function createConfig()
    {
        $delivery_time_available_values = array();
        $delivery_time_oos_values = array();
        $shopping_cart_text_before = array();
        $shopping_cart_text_after = array();

        $langs_repository = $this->entity_manager->getRepository('Language');
        $langs = $langs_repository->findAll();

        foreach ($langs as $lang) {
            $delivery_time_available_values[(int)$lang->id] = $this->l('Delivery: 1 to 3 weeks', 'advancedeucompliance');
            $delivery_time_oos_values[(int)$lang->id] = $this->l('Delivery: 3 to 6 weeks', 'advancedeucompliance');
            $shopping_cart_text_before[(int)$lang->id] = '';
            $shopping_cart_text_after[(int)$lang->id] = '';
        }

        /* Base settings */
        $this->processAeucFeatTellAFriend(true);
        $this->processAeucFeatReorder(true);
        $this->processAeucFeatAdvPaymentApi(false);
        $this->processAeucLabelRevocationTOS(false);
        $this->processAeucLabelRevocationVP(false);
        $this->processAeucLabelSpecificPrice(true);
        $this->processAeucLabelTaxIncExc(true);
        $this->processAeucLabelShippingIncExc(false);
        $this->processAeucLabelWeight(true);
        $this->processAeucLabelCombinationFrom(true);

        $is_theme_compliant = $this->isThemeCompliant();

        $ps_weight_precision_installed = Configuration::get('PS_PRODUCT_WEIGHT_PRECISION') ?
            (int)Configuration::get('PS_PRODUCT_WEIGHT_PRECISION') :
            Advancedeucompliance::DEFAULT_PS_PRODUCT_WEIGHT_PRECISION;

        return Configuration::updateValue('AEUC_FEAT_TELL_A_FRIEND', false) &&
               Configuration::updateValue('AEUC_FEAT_ADV_PAYMENT_API', false) &&
               Configuration::updateValue('AEUC_LABEL_DELIVERY_TIME_AVAILABLE', $delivery_time_available_values) &&
               Configuration::updateValue('AEUC_LABEL_DELIVERY_TIME_OOS', $delivery_time_oos_values) &&
               Configuration::updateValue('AEUC_LABEL_SPECIFIC_PRICE', true) &&
               Configuration::updateValue('AEUC_LABEL_TAX_INC_EXC', true) &&
               Configuration::updateValue('AEUC_LABEL_WEIGHT', true) &&
               Configuration::updateValue('AEUC_LABEL_REVOCATION_TOS', false) &&
               Configuration::updateValue('AEUC_LABEL_REVOCATION_VP', true) &&
               Configuration::updateValue('AEUC_LABEL_SHIPPING_INC_EXC', false) &&
               Configuration::updateValue('AEUC_LABEL_COMBINATION_FROM', true) &&
               Configuration::updateValue('AEUC_SHOPPING_CART_TEXT_BEFORE', $shopping_cart_text_before) &&
               Configuration::updateValue('AEUC_SHOPPING_CART_TEXT_AFTER', $shopping_cart_text_after) &&
               Configuration::updateValue('AEUC_IS_THEME_COMPLIANT', (bool)$is_theme_compliant) &&
               Configuration::updateValue('PS_PRODUCT_WEIGHT_PRECISION', (int)$ps_weight_precision_installed);
    }

    public function unloadTables()
    {
        $state = true;
        $sql = require dirname(__FILE__) . '/install/sql_install.php';
        foreach ($sql as $name => $v) {
            $state &= Db::getInstance()->execute('DROP TABLE IF EXISTS ' . $name);
        }

        return $state;
    }

    public function loadTables()
    {
        $state = true;

        // Create module's table
        $sql = require dirname(__FILE__) . '/install/sql_install.php';
        foreach ($sql as $s) {
            $state &= Db::getInstance()->execute($s);
        }

        // Fillin CMS ROLE
        $roles_array = $this->getCMSRoles();
        $roles = array_keys($roles_array);
        $cms_role_repository = $this->entity_manager->getRepository('CMSRole');

        foreach ($roles as $role) {
            if (!$cms_role_repository->findOneByName($role)) {
                $cms_role = $cms_role_repository->getNewEntity();
                $cms_role->id_cms = 0; // No assoc at this time
                $cms_role->name = $role;
                $state &= (bool)$cms_role->save();
            }
        }

        $default_path_email = _PS_MAIL_DIR_ . 'en' . DIRECTORY_SEPARATOR;
        // Fill-in aeuc_mail table
        foreach ($this->emails->getAvailableMails($default_path_email) as $mail) {
            $new_email = new AeucEmailEntity();
            $new_email->filename = (string)$mail;
            $new_email->display_name = $this->emails->getCleanedMailName($mail);
            $new_email->save();
            unset($new_email);
        }

        return $state;
    }


    public function dropConfig()
    {
        // Remove roles
        $roles_array = $this->getCMSRoles();
        $roles = array_keys($roles_array);
        $cms_role_repository = $this->entity_manager->getRepository('CMSRole');
        $cleaned = true;

        foreach ($roles as $role) {
            $cms_role_tmp = $cms_role_repository->findOneByName($role);
            if ($cms_role_tmp) {
                $cleaned &= $cms_role_tmp->delete();
            }
        }

        return Configuration::deleteByName('AEUC_FEAT_TELL_A_FRIEND') &&
               Configuration::deleteByName('AEUC_FEAT_ADV_PAYMENT_API') &&
               Configuration::deleteByName('AEUC_LABEL_DELIVERY_TIME_AVAILABLE') &&
               Configuration::deleteByName('AEUC_LABEL_DELIVERY_TIME_OOS') &&
               Configuration::deleteByName('AEUC_LABEL_SPECIFIC_PRICE') &&
               Configuration::deleteByName('AEUC_LABEL_TAX_INC_EXC') &&
               Configuration::deleteByName('AEUC_LABEL_WEIGHT') &&
               Configuration::deleteByName('AEUC_LABEL_REVOCATION_TOS') &&
               Configuration::deleteByName('AEUC_LABEL_REVOCATION_VP') &&
               Configuration::deleteByName('AEUC_LABEL_SHIPPING_INC_EXC') &&
               Configuration::deleteByName('AEUC_LABEL_COMBINATION_FROM') &&
               Configuration::deleteByName('AEUC_SHOPPING_CART_TEXT_BEFORE') &&
               Configuration::deleteByName('AEUC_SHOPPING_CART_TEXT_AFTER') &&
               Configuration::deleteByName('AEUC_IS_THEME_COMPLIANT') &&
               Configuration::updateValue('PS_ADVANCED_PAYMENT_API', false) &&
               Configuration::updateValue('PS_ATCP_SHIPWRAP', false);
    }

    /*
        This method checks if cart has virtual products
        It's better to add this method (as hasVirtualProduct) and add 'protected static $_hasVirtualProduct = array(); property
        in Cart class in next version of prestashop.
    */
    private function hasCartVirtualProduct(Cart $cart)
    {
        $products = $cart->getProducts();

        if (!count($products)) {
            return false;
        }

        foreach ($products as $product) {
            if ($product['is_virtual']) {
                return true;
            }
        }

        return false;
    }

    public function hookDisplayCartTotalPriceLabel($param)
    {
        $smartyVars = array();
        if ((bool)Configuration::get('AEUC_LABEL_TAX_INC_EXC') === true) {

            $customer_default_group_id = (int)$this->context->customer->id_default_group;
            $customer_default_group = new Group($customer_default_group_id);

            if ((bool)Configuration::get('PS_TAX') === true && $this->context->country->display_tax_label &&
                !(Validate::isLoadedObject($customer_default_group) && (bool)$customer_default_group->price_display_method === true)) {
                $smartyVars['price']['tax_str_i18n'] = $this->l('Tax included', 'advancedeucompliance');
            } else {
                $smartyVars['price']['tax_str_i18n'] = $this->l('Tax excluded', 'advancedeucompliance');
            }
        }

        if (isset($param['from'])) {
            if ($param['from'] == 'shopping_cart') {
                $smartyVars['css_class'] = 'aeuc_tax_label_shopping_cart';
            }
            if ($param['from'] == 'blockcart') {
                $smartyVars['css_class'] = 'aeuc_tax_label_blockcart';
            }
        }

        $this->context->smarty->assign(array('smartyVars' => $smartyVars));


        return $this->display(__FILE__, 'displayCartTotalPriceLabel.tpl');
    }

    /* This hook is present to maintain backward compatibility */
    public function hookAdvancedPaymentOptions($param)
    {
        $legacyOptions = Hook::exec('displayPaymentEU', array(), null, true);
        $newOptions = array();

        Media::addJsDef(array('aeuc_tos_err_str' => Tools::htmlentitiesUTF8($this->l('You must agree to our Terms of Service before going any further!',
                                                                                     'advancedeucompliance'))));
        Media::addJsDef(array('aeuc_submit_err_str' => Tools::htmlentitiesUTF8($this->l('Something went wrong. If the problem persists, please contact us.',
                                                                                        'advancedeucompliance'))));
        Media::addJsDef(array('aeuc_no_pay_err_str' => Tools::htmlentitiesUTF8($this->l('Select a payment option first.',
                                                                                        'advancedeucompliance'))));
        Media::addJsDef(array('aeuc_virt_prod_err_str' => Tools::htmlentitiesUTF8($this->l('Please check "Revocation of virtual products" box first !',
                                                                                           'advancedeucompliance'))));
        if ($legacyOptions) {
            foreach ($legacyOptions as $module_name => $legacyOption) {

                if (!$legacyOption) {
                    continue;
                }

                foreach (Core_Business_Payment_PaymentOption::convertLegacyOption($legacyOption) as $option) {
                    $option->setModuleName($module_name);
                    $to_be_cleaned = $option->getForm();
                    if ($to_be_cleaned) {
                        $cleaned = str_replace('@hiddenSubmit', '', $to_be_cleaned);
                        $option->setForm($cleaned);
                    }
                    $newOptions[] = $option;
                }
            }

            return $newOptions;
        }

        return null;
    }

    public function hookActionEmailAddAfterContent($param)
    {
        if (!isset($param['template']) || !isset($param['template_html']) || !isset($param['template_txt'])) {
            return;
        }

        $tpl_name = (string)$param['template'];
        $tpl_name_exploded = explode('.', $tpl_name);
        if (is_array($tpl_name_exploded)) {
            $tpl_name = (string)$tpl_name_exploded[0];
        }

        $id_lang = (int)$param['id_lang'];
        $mail_id = AeucEmailEntity::getMailIdFromTplFilename($tpl_name);
        if (!isset($mail_id['id_mail'])) {
            return;
        }

        $mail_id = (int)$mail_id['id_mail'];
        $cms_role_ids = AeucCMSRoleEmailEntity::getCMSRoleIdsFromIdMail($mail_id);
        if (!$cms_role_ids) {
            return;
        }

        $tmp_cms_role_list = array();
        foreach ($cms_role_ids as $cms_role_id) {
            $tmp_cms_role_list[] = $cms_role_id['id_cms_role'];
        }

        $cms_role_repository = $this->entity_manager->getRepository('CMSRole');
        $cms_roles = $cms_role_repository->findByIdCmsRole($tmp_cms_role_list);
        if (!$cms_roles) {
            return;
        }

        $cms_repo = $this->entity_manager->getRepository('CMS');
        $cms_contents = array();

        foreach ($cms_roles as $cms_role) {
            $cms_page = $cms_repo->i10nFindOneById((int)$cms_role->id_cms, $id_lang, $this->context->shop->id);

            if (!isset($cms_page->content)) {
                continue;
            }

            $cms_contents[] = $cms_page->content;
            $param['template_txt'] .= strip_tags($cms_page->content, true);
        }

        $this->context->smarty->assign(array('cms_contents' => $cms_contents));
        $param['template_html'] .= $this->display(__FILE__, 'hook-email-wrapper.tpl');

    }

    public function hookHeader($param)
    {
        $css_required = array(
            'index',
            'product',
            'order',
            'order-opc',
            'category',
            'products-comparison',

        );

        if (isset($this->context->controller->php_self) && in_array($this->context->controller->php_self, $css_required)) {
            $this->context->controller->addCSS($this->_path . 'views/css/aeuc_front.css', 'all');
        }

    }

    public function hookOverrideTOSDisplay($param)
    {
        $has_tos_override_opt = (bool)Configuration::get('AEUC_LABEL_REVOCATION_TOS');
        $cms_repository = $this->entity_manager->getRepository('CMS');
        // Check first if LEGAL_REVOCATION CMS Role is set
        $cms_role_repository = $this->entity_manager->getRepository('CMSRole');
        $cms_page_associated = $cms_role_repository->findOneByName(Advancedeucompliance::LEGAL_REVOCATION);

        // Check if cart has virtual product
        $has_virtual_product = (bool)Configuration::get('AEUC_LABEL_REVOCATION_VP') && $this->hasCartVirtualProduct($this->context->cart);
        Media::addJsDef(array('aeuc_has_virtual_products' => (bool)$has_virtual_product,
                              'aeuc_virt_prod_err_str' => Tools::htmlentitiesUTF8($this->l('Please check "Revocation of virtual products" box first !',
                                                                                           'advancedeucompliance'))));
        if ($has_tos_override_opt || (bool)Configuration::get('AEUC_LABEL_REVOCATION_VP')) {
            $this->context->controller->addJS($this->_path . 'views/js/fo_aeuc_tnc.js', true);
        }

        $checkedTos = false;
        $link_conditions = '';
        $link_revocations = '';

        // Get IDs of CMS pages required
        $cms_conditions_id = (int)Configuration::get('PS_CONDITIONS_CMS_ID');
        $cms_revocation_id = (int)$cms_page_associated->id_cms;

        // Get misc vars
        $id_lang = (int)$this->context->language->id;
        $id_shop = (int)$this->context->shop->id;
        $is_ssl_enabled = (bool)Configuration::get('PS_SSL_ENABLED');
        $checkedTos = $this->context->cart->checkedTos ? true : false;

        // Get CMS OBJs
        $cms_conditions = $cms_repository->i10nFindOneById($cms_conditions_id, $id_lang, $id_shop);
        $link_conditions =
            $this->context->link->getCMSLink($cms_conditions, $cms_conditions->link_rewrite, $is_ssl_enabled);

        if (!strpos($link_conditions, '?')) {
            $link_conditions .= '?content_only=1';
        } else {
            $link_conditions .= '&content_only=1';
        }

        if ($has_tos_override_opt === true) {

            $cms_revocations = $cms_repository->i10nFindOneById($cms_revocation_id, $id_lang, $id_shop);
            // Get links to revocation page
            $link_revocations =
                $this->context->link->getCMSLink($cms_revocations, $cms_revocations->link_rewrite, $is_ssl_enabled);

            if (!strpos($link_revocations, '?')) {
                $link_revocations .= '?content_only=1';
            } else {
                $link_revocations .= '&content_only=1';
            }
        }

        $this->context->smarty->assign(array(
                                           'has_tos_override_opt' => $has_tos_override_opt,
                                           'checkedTOS'           => $checkedTos,
                                           'link_conditions'      => $link_conditions,
                                           'link_revocations'     => $link_revocations,
                                           'has_virtual_product'  => $has_virtual_product
                                       ));

        return $this->display(__FILE__, 'hookOverrideTOSDisplay.tpl');
    }

    public function hookDisplayBeforeShoppingCartBlock($params)
    {
        if ($this->context->controller instanceof OrderOpcController || property_exists($this->context->controller, 'step') && $this->context->controller->step == 3) {
            $cart_text = Configuration::get('AEUC_SHOPPING_CART_TEXT_BEFORE', $this->context->language->id);

            if ($cart_text) {
                $this->context->smarty->assign('cart_text', $cart_text);

                return $this->display(__FILE__, 'displayShoppingCartBeforeBlock.tpl');
            }
        }
    }

    public function hookDisplayAfterShoppingCartBlock($params)
    {

        $cart_text = Configuration::get('AEUC_SHOPPING_CART_TEXT_AFTER', Context::getContext()->language->id);

        if ($cart_text && isset($params['colspan_total'])) {
            $this->context->smarty->assign(array('cart_text'     => $cart_text,
                                                 'colspan_total' => (int)$params['colspan_total']
                                           ));

            return $this->display(__FILE__, 'displayShoppingCartAfterBlock.tpl');
        }
    }

    public function hookDisplayProductPriceBlock($param)
    {
        if (!isset($param['product']) || !isset($param['type'])) {
            return;
        }

        $product = $param['product'];

        if (is_array($product)) {
            $product_repository = $this->entity_manager->getRepository('Product');
            $product = $product_repository->findOne((int)$product['id_product']);
        }
        if (!Validate::isLoadedObject($product)) {
            return;
        }

        $smartyVars = array();

        /* Handle Product Combinations label */
        if ($param['type'] == 'before_price' && (bool)Configuration::get('AEUC_LABEL_COMBINATION_FROM') === true) {
            if ($product->hasAttributes()) {
                $need_display = false;
                $combinations = $product->getAttributeCombinations($this->context->language->id);
                if ($combinations && is_array($combinations)) {
                    foreach ($combinations as $combination) {
                        if ((float)$combination['price'] > 0) {
                            $need_display = true;
                            break;
                        }
                    }

                    unset($combinations);

                    if ($need_display) {
                        $smartyVars['before_price'] = array();
                        $smartyVars['before_price']['from_str_i18n'] = $this->l('From', 'advancedeucompliance');

                        return $this->dumpHookDisplayProductPriceBlock($smartyVars);
                    }
                }

                return;
            }
        }

        /* Handle Specific Price label*/
        if ($param['type'] == 'old_price' && (bool)Configuration::get('AEUC_LABEL_SPECIFIC_PRICE') === true) {
            $smartyVars['old_price'] = array();
            $smartyVars['old_price']['before_str_i18n'] = $this->l('Before', 'advancedeucompliance');

            return $this->dumpHookDisplayProductPriceBlock($smartyVars);
        }

        /* Handle taxes  Inc./Exc. and Shipping Inc./Exc.*/
        if ($param['type'] == 'price') {
            $smartyVars['price'] = array();
            $need_shipping_label = true;

            if ((bool)Configuration::get('AEUC_LABEL_TAX_INC_EXC') === true) {

                $customer_default_group_id = (int)$this->context->customer->id_default_group;
                $customer_default_group = new Group($customer_default_group_id);

                if ((bool)Configuration::get('PS_TAX') === true && $this->context->country->display_tax_label &&
                    !(Validate::isLoadedObject($customer_default_group) && (bool)$customer_default_group->price_display_method === true)) {
                    $smartyVars['price']['tax_str_i18n'] = $this->l('Tax included', 'advancedeucompliance');
                } else {
                    $smartyVars['price']['tax_str_i18n'] = $this->l('Tax excluded', 'advancedeucompliance');
                }

                if (isset($param['from']) && $param['from'] == 'blockcart') {
                    $smartyVars['price']['css_class'] = 'aeuc_tax_label_blockcart';
                    $need_shipping_label = false;
                }
            }
            if ((bool)Configuration::get('AEUC_LABEL_SHIPPING_INC_EXC') === true && $need_shipping_label === true) {

                if (!$product->is_virtual) {
                    $cms_role_repository = $this->entity_manager->getRepository('CMSRole');
                    $cms_repository = $this->entity_manager->getRepository('CMS');
                    $cms_page_associated = $cms_role_repository->findOneByName(Advancedeucompliance::LEGAL_SHIP_PAY);

                    if (isset($cms_page_associated->id_cms) && $cms_page_associated->id_cms != 0) {

                        $cms_ship_pay_id = (int)$cms_page_associated->id_cms;
                        $cms_revocations = $cms_repository->i10nFindOneById($cms_ship_pay_id, $this->context->language->id,
                                                                            $this->context->shop->id);
                        $is_ssl_enabled = (bool)Configuration::get('PS_SSL_ENABLED');
                        $link_ship_pay = $this->context->link->getCMSLink($cms_revocations, $cms_revocations->link_rewrite, $is_ssl_enabled);

                        if (!strpos($link_ship_pay, '?')) {
                            $link_ship_pay .= '?content_only=1';
                        } else {
                            $link_ship_pay .= '&content_only=1';
                        }

                        $smartyVars['ship'] = array();
                        $smartyVars['ship']['link_ship_pay'] = $link_ship_pay;
                        $smartyVars['ship']['ship_str_i18n'] = $this->l('Shipping excluded', 'advancedeucompliance');
                    }
                }
            }

            return $this->dumpHookDisplayProductPriceBlock($smartyVars);
        }

        /* Handles product's weight */
        if ($param['type'] == 'weight' && (bool)Configuration::get('PS_DISPLAY_PRODUCT_WEIGHT') === true &&
            isset($param['hook_origin']) && $param['hook_origin'] == 'product_sheet'
        ) {
            if ((float)$product->weight) {
                $smartyVars['weight'] = array();
                $rounded_weight = round((float)$product->weight, Configuration::get('PS_PRODUCT_WEIGHT_PRECISION'));
                $smartyVars['weight']['rounded_weight_str_i18n'] =
                    $rounded_weight . ' ' . Configuration::get('PS_WEIGHT_UNIT');

                return $this->dumpHookDisplayProductPriceBlock($smartyVars);
            }
        }

        /* Handle Estimated delivery time label */
        if ($param['type'] == 'after_price' && !$product->is_virtual) {
            $context_id_lang = $this->context->language->id;
            $is_product_available = (StockAvailable::getQuantityAvailableByProduct($product->id) >= 1 ? true : false);
            $smartyVars['after_price'] = array();

            if ($is_product_available) {
                $contextualized_content =
                    Configuration::get('AEUC_LABEL_DELIVERY_TIME_AVAILABLE', (int)$context_id_lang);
                $smartyVars['after_price']['delivery_str_i18n'] = $contextualized_content;
            } else {
                $contextualized_content = Configuration::get('AEUC_LABEL_DELIVERY_TIME_OOS', (int)$context_id_lang);
                $smartyVars['after_price']['delivery_str_i18n'] = $contextualized_content;
            }

            return $this->dumpHookDisplayProductPriceBlock($smartyVars);
        }
    }

    private function emptyTemplatesCache()
    {
        $this->_clearCache('product.tpl');
        $this->_clearCache('product-list.tpl');
    }

    private function dumpHookDisplayProductPriceBlock(array $smartyVars)
    {
        $this->context->smarty->assign(array('smartyVars' => $smartyVars));
        $this->context->controller->addJS($this->_path . 'views/js/fo_aeuc_tnc.js', true);

        return $this->display(__FILE__, 'hookDisplayProductPriceBlock.tpl');
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        $theme_warning = null;
        $this->refreshThemeStatus();
        $success_band = $this->_postProcess();
        if ((bool)Configuration::get('AEUC_IS_THEME_COMPLIANT') === false) {
            $missing = '<ul>';
            foreach ($this->missing_templates as $missing_tpl) {
                $missing .= '<li>'.$missing_tpl.' '.$this->l('missing').'</li>';
            }
            $missing .= '</ul><br/>';
            $discard_warning_link = $this->context->link->getAdminLink('AdminModules', false) .
                                    '&configure='.$this->name.
                                    '&tab_module='.$this->tab.
                                    '&module_name='.$this->name.
                                    '&discard_tpl_warn=1'.
                                    '&token='.Tools::getAdminTokenLite('AdminModules');
            $missing .= '<a href="'.$discard_warning_link.'" type="button">'.$this->l('Hide this, I know what I am doing.',
                                                                                      'advancedeucompliance').
                        '</a>';
            $theme_warning = $this->displayWarning($this->l('It seems that your current theme is not compatible with this module, some mandatory templates are missing. It is possible some options may not work as expected.',
                                                            'advancedeucompliance').$missing);

        }

        $this->context->smarty->assign('module_dir', $this->_path);
        $this->context->smarty->assign('errors', $this->_errors);
        $this->context->controller->addCSS($this->_path . 'views/css/configure.css', 'all');
        // Render all required form for each 'part'
        $formLabelsManager = $this->renderFormLabelsManager();
        $formFeaturesManager = $this->renderFormFeaturesManager();
        $formLegalContentManager = $this->renderFormLegalContentManager();
        $formEmailAttachmentsManager = $this->renderFormEmailAttachmentsManager();

        return $theme_warning . $success_band . $formLabelsManager . $formFeaturesManager . $formLegalContentManager .
               $formEmailAttachmentsManager;
    }

    /**
     * Save form data.
     */
    protected function _postProcess()
    {
        $has_processed_something = false;

        $post_keys_switchable =
            array_keys(array_merge($this->getConfigFormLabelsManagerValues(), $this->getConfigFormFeaturesManagerValues()));

        $post_keys_complex = array('AEUC_legalContentManager',
                                   'AEUC_emailAttachmentsManager',
                                   'PS_PRODUCT_WEIGHT_PRECISION',
                                   'discard_tpl_warn'
        );

        $i10n_inputs_received = array();
        $received_values = Tools::getAllValues();

        foreach (array_keys($received_values) as $key_received) {
            /* Case its one of form with only switches in it */
            if (in_array($key_received, $post_keys_switchable)) {
                $is_option_active = Tools::getValue($key_received);
                $key = Tools::strtolower($key_received);
                $key = Tools::toCamelCase($key);

                if (method_exists($this, 'process' . $key)) {

                    $this->{'process' . $key}($is_option_active);
                    $has_processed_something = true;
                }
                continue;
            }
            /* Case we are on more complex forms */
            if (in_array($key_received, $post_keys_complex)) {
                // Clean key
                $key = Tools::strtolower($key_received);
                $key = Tools::toCamelCase($key, true);

                if (method_exists($this, 'process' . $key)) {
                    $this->{'process' . $key}();
                    $has_processed_something = true;
                }
            }

            /* Case Multi-lang input */
            if (strripos($key_received, 'AEUC_LABEL_DELIVERY_TIME_AVAILABLE') !== false) {
                $exploded = explode('_', $key_received);
                $count = count($exploded);
                $id_lang = (int)$exploded[$count - 1];
                $i10n_inputs_received['AEUC_LABEL_DELIVERY_TIME_AVAILABLE'][$id_lang] = $received_values[$key_received];
            }
            if (strripos($key_received, 'AEUC_LABEL_DELIVERY_TIME_OOS') !== false) {
                $exploded = explode('_', $key_received);
                $count = count($exploded);
                $id_lang = (int)$exploded[$count - 1];
                $i10n_inputs_received['AEUC_LABEL_DELIVERY_TIME_OOS'][$id_lang] = $received_values[$key_received];
            }
            if (strripos($key_received, 'AEUC_SHOPPING_CART_TEXT_BEFORE') !== false) {
                $exploded = explode('_', $key_received);
                $count = count($exploded);
                $id_lang = (int)$exploded[$count - 1];
                $i10n_inputs_received['AEUC_SHOPPING_CART_TEXT_BEFORE'][$id_lang] = $received_values[$key_received];
            }
            if (strripos($key_received, 'AEUC_SHOPPING_CART_TEXT_AFTER') !== false) {
                $exploded = explode('_', $key_received);
                $count = count($exploded);
                $id_lang = (int)$exploded[$count - 1];
                $i10n_inputs_received['AEUC_SHOPPING_CART_TEXT_AFTER'][$id_lang] = $received_values[$key_received];
            }
        }

        if (count($i10n_inputs_received) > 0) {
            $this->processAeucLabelDeliveryTime($i10n_inputs_received);
            $this->processAeucShoppingCartText($i10n_inputs_received);
            $has_processed_something = true;
        }

        if ($has_processed_something) {
            $this->emptyTemplatesCache();

            return (count($this->_errors) ? $this->displayError($this->_errors) : '') .
                   (count($this->_warnings) ? $this->displayWarning($this->_warnings) : '') .
                   $this->displayConfirmation($this->l('Settings saved successfully!', 'advancedeucompliance'));
        } else {
            return (count($this->_errors) ? $this->displayError($this->_errors) : '') .
                   (count($this->_warnings) ? $this->displayWarning($this->_warnings) : '') . '';
        }
    }

    protected function processPsProductWeightPrecision($option_value)
    {
        $option_value = (int)$option_value;

        /* Avoid negative values */
        if ($option_value < 0) {
            $option_value = 0;
        }

        Configuration::updateValue('PS_PRODUCT_WEIGHT_PRECISION', (int)$option_value);
    }

    protected function processAeucLabelDeliveryTime(array $i10n_inputs)
    {
        if (isset($i10n_inputs['AEUC_LABEL_DELIVERY_TIME_AVAILABLE'])) {
            Configuration::updateValue('AEUC_LABEL_DELIVERY_TIME_AVAILABLE', $i10n_inputs['AEUC_LABEL_DELIVERY_TIME_AVAILABLE']);
        }
        if (isset($i10n_inputs['AEUC_LABEL_DELIVERY_TIME_OOS'])) {
            Configuration::updateValue('AEUC_LABEL_DELIVERY_TIME_OOS', $i10n_inputs['AEUC_LABEL_DELIVERY_TIME_OOS']);
        }
    }

    protected function processAeucShoppingCartText(array $i10n_inputs)
    {
        if (isset($i10n_inputs['AEUC_SHOPPING_CART_TEXT_BEFORE'])) {
            Configuration::updateValue('AEUC_SHOPPING_CART_TEXT_BEFORE', $i10n_inputs['AEUC_SHOPPING_CART_TEXT_BEFORE']);
        }
        if (isset($i10n_inputs['AEUC_SHOPPING_CART_TEXT_AFTER'])) {
            Configuration::updateValue('AEUC_SHOPPING_CART_TEXT_AFTER', $i10n_inputs['AEUC_SHOPPING_CART_TEXT_AFTER']);
        }
    }

    protected function processAeucLabelCombinationFrom($is_option_active)
    {
        if ((bool)$is_option_active) {
            Configuration::updateValue('AEUC_LABEL_COMBINATION_FROM', true);
        } else {
            Configuration::updateValue('AEUC_LABEL_COMBINATION_FROM', false);
        }
    }

    protected function processAeucLabelSpecificPrice($is_option_active)
    {
        if ((bool)$is_option_active) {
            Configuration::updateValue('AEUC_LABEL_SPECIFIC_PRICE', true);
        } else {
            Configuration::updateValue('AEUC_LABEL_SPECIFIC_PRICE', false);
        }
    }

    protected function processAeucEmailAttachmentsManager()
    {
        $json_attach_assoc = Tools::jsonDecode(Tools::getValue('emails_attach_assoc'));

        if (!$json_attach_assoc) {
            return;
        }

        // Empty previous assoc to make new ones
        AeucCMSRoleEmailEntity::truncate();

        foreach ($json_attach_assoc as $assoc) {
            $assoc_obj = new AeucCMSRoleEmailEntity();
            $assoc_obj->id_mail = $assoc->id_mail;
            $assoc_obj->id_cms_role = $assoc->id_cms_role;

            if (!$assoc_obj->save()) {
                $this->_errors[] = $this->l('Failed to associate CMS content with an email template.', 'advancedeucompliance');
            }
        }
    }

    protected function processAeucLabelRevocationTOS($is_option_active)
    {
        // Check first if LEGAL_REVOCATION CMS Role has been set before doing anything here
        $cms_role_repository = $this->entity_manager->getRepository('CMSRole');
        $cms_page_associated = $cms_role_repository->findOneByName(Advancedeucompliance::LEGAL_REVOCATION);
        $cms_roles = $this->getCMSRoles();

        if ((bool)$is_option_active) {
            if (!$cms_page_associated instanceof CMSRole || (int)$cms_page_associated->id_cms == 0) {
                $this->_errors[] =
                    sprintf($this->l('\'Revocation Terms within ToS\' label cannot be activated unless you associate "%s" role with a CMS Page.',
                                     'advancedeucompliance'), (string)$cms_roles[Advancedeucompliance::LEGAL_REVOCATION]);

                return;
            }
            Configuration::updateValue('AEUC_LABEL_REVOCATION_TOS', true);
        } else {
            Configuration::updateValue('AEUC_LABEL_REVOCATION_TOS', false);
        }
    }

    protected function processAeucLabelRevocationVP($is_option_active)
    {
        if ((bool)$is_option_active) {
            Configuration::updateValue('AEUC_LABEL_REVOCATION_VP', true);
        } else {
            Configuration::updateValue('AEUC_LABEL_REVOCATION_VP', false);
        }
    }

    protected function processAeucLabelShippingIncExc($is_option_active)
    {
        // Check first if LEGAL_SHIP_PAY CMS Role has been set before doing anything here
        $cms_role_repository = $this->entity_manager->getRepository('CMSRole');
        $cms_page_associated = $cms_role_repository->findOneByName(Advancedeucompliance::LEGAL_SHIP_PAY);
        $cms_roles = $this->getCMSRoles();

        if ((bool)$is_option_active) {
            if (!$cms_page_associated instanceof CMSRole || (int)$cms_page_associated->id_cms === 0) {
                $this->_errors[] =
                    sprintf($this->l('Shipping fees label cannot be activated unless you associate "%s" role with a CMS Page',
                                     'advancedeucompliance'), (string)$cms_roles[Advancedeucompliance::LEGAL_SHIP_PAY]);

                return;
            }
            Configuration::updateValue('AEUC_LABEL_SHIPPING_INC_EXC', true);
        } else {
            Configuration::updateValue('AEUC_LABEL_SHIPPING_INC_EXC', false);
        }

    }

    protected function processAeucLabelTaxIncExc($is_option_active)
    {
        Configuration::updateValue('AEUC_LABEL_TAX_INC_EXC', (bool)$is_option_active);
    }

    private function refreshThemeStatus()
    {
        if ((bool)Configuration::get('AEUC_IS_THEME_COMPLIANT') === false) {
            $re_check = $this->isThemeCompliant();
            if ($re_check === true) {
                Configuration::updateValue('AEUC_IS_THEME_COMPLIANT', (bool)$re_check);
            }
        }
    }

    protected function processDiscardTplWarn()
    {
        Configuration::updateValue('AEUC_IS_THEME_COMPLIANT', true);
    }

    protected function processAeucFeatAdvPaymentApi($is_option_active)
    {
        $this->refreshThemeStatus();

        if ((bool)$is_option_active) {
            if ((bool)Configuration::get('AEUC_IS_THEME_COMPLIANT')) {
                Configuration::updateValue('PS_ADVANCED_PAYMENT_API', true);
                Configuration::updateValue('AEUC_FEAT_ADV_PAYMENT_API', true);
            } else {
                $this->_errors[] = $this->l('It is not possible to enable the "Advanced Checkout Page" as your theme is not compatible with this option.',
                                            'advancedeucompliance');
            }
        } else {
            Configuration::updateValue('PS_ADVANCED_PAYMENT_API', false);
            Configuration::updateValue('AEUC_FEAT_ADV_PAYMENT_API', false);
        }
    }

    protected function processPsAtcpShipWrap($is_option_active)
    {
        Configuration::updateValue('PS_ATCP_SHIPWRAP', $is_option_active);
    }

    protected function processAeucFeatTellAFriend($is_option_active)
    {
        $staf_module = Module::getInstanceByName('sendtoafriend');
        if ($staf_module) {

            if ((bool)$is_option_active) {
                Configuration::updateValue('AEUC_FEAT_TELL_A_FRIEND', true);
                if ($staf_module->isEnabledForShopContext() === false) {
                    $staf_module->enable();
                }
            } elseif (!(bool)$is_option_active) {
                Configuration::updateValue('AEUC_FEAT_TELL_A_FRIEND', false);
                if ($staf_module->isEnabledForShopContext() === true) {
                    $staf_module->disable();
                }
            }
        }
    }

    protected function processAeucFeatReorder($is_option_active)
    {

        if ((bool)$is_option_active) {
            Configuration::updateValue('PS_DISALLOW_HISTORY_REORDERING', false);
        } else {
            Configuration::updateValue('PS_DISALLOW_HISTORY_REORDERING', true);
        }
    }

    protected function processAeucLabelWeight($is_option_active)
    {
        if ((bool)$is_option_active) {
            Configuration::updateValue('PS_DISPLAY_PRODUCT_WEIGHT', true);
            Configuration::updateValue('AEUC_LABEL_WEIGHT', true);
        } elseif (!(bool)$is_option_active) {
            Configuration::updateValue('PS_DISPLAY_PRODUCT_WEIGHT', false);
            Configuration::updateValue('AEUC_LABEL_WEIGHT', false);
        }
    }

    protected function processAeucLegalContentManager()
    {

        $posted_values = Tools::getAllValues();
        $cms_role_repository = $this->entity_manager->getRepository('CMSRole');

        foreach ($posted_values as $key_name => $assoc_cms_id) {
            if (strpos($key_name, 'CMSROLE_') !== false) {
                $exploded_key_name = explode('_', $key_name);
                $cms_role = $cms_role_repository->findOne((int)$exploded_key_name[1]);
                $cms_role->id_cms = (int)$assoc_cms_id;
                $cms_role->update();
            }
        }
        unset($cms_role);
    }

    protected function getCMSRoles()
    {
        return array(Advancedeucompliance::LEGAL_NOTICE          => $this->l('Legal notice', 'advancedeucompliance'),
                     Advancedeucompliance::LEGAL_CONDITIONS      => $this->l('Terms of Service (ToS)', 'advancedeucompliance'),
                     Advancedeucompliance::LEGAL_REVOCATION      => $this->l('Revocation terms', 'advancedeucompliance'),
                     Advancedeucompliance::LEGAL_REVOCATION_FORM => $this->l('Revocation form', 'advancedeucompliance'),
                     Advancedeucompliance::LEGAL_PRIVACY         => $this->l('Privacy', 'advancedeucompliance'),
                     Advancedeucompliance::LEGAL_ENVIRONMENTAL   => $this->l('Environmental notice', 'advancedeucompliance'),
                     Advancedeucompliance::LEGAL_SHIP_PAY        => $this->l('Shipping and payment', 'advancedeucompliance')
        );
    }

    /**
     * Create the form that will let user choose all the wording options
     */
    protected function renderFormLabelsManager()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitAEUC_labelsManager';
        $helper->currentIndex =
            $this->context->link->getAdminLink('AdminModules', false) . '&configure=' . $this->name . '&tab_module=' .
            $this->tab . '&module_name=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules');
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars =
            array('fields_value' => $this->getConfigFormLabelsManagerValues(),
                  /* Add values for your inputs */
                  'languages'    => $this->context->controller->getLanguages(),
                  'id_language'  => $this->context->language->id,
            );

        return $helper->generateForm(array($this->getConfigFormLabelsManager()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigFormLabelsManager()
    {
        return array('form' => array('legend' => array('title' => $this->l('Labels', 'advancedeucompliance'),
                                                       'icon'  => 'icon-tags',
        ),
                                     'input'  => array(array('type'  => 'text',
                                                             'lang'  => true,
                                                             'label' => $this->l('Estimated delivery time label (available products)',
                                                                                 'advancedeucompliance'),
                                                             'name'  => 'AEUC_LABEL_DELIVERY_TIME_AVAILABLE',
                                                             'desc'  => $this->l('Indicate the estimated delivery time for your in-stock products. Leave the field empty to disable.',
                                                                                 'advancedeucompliance'),
                                                       ),
                                                       array('type'  => 'text',
                                                             'lang'  => true,
                                                             'label' => $this->l('Estimated delivery time label (out-of-stock products)',
                                                                                 'advancedeucompliance'),
                                                             'name'  => 'AEUC_LABEL_DELIVERY_TIME_OOS',
                                                             'desc'  => $this->l('Indicate the estimated delivery time for your out-of-stock products. Leave the field empty to disable.',
                                                                                 'advancedeucompliance'),
                                                       ),
                                                       array('type'    => 'switch',
                                                             'label'   => $this->l('\'Before\' Base price label',
                                                                                   'advancedeucompliance'),
                                                             'name'    => 'AEUC_LABEL_SPECIFIC_PRICE',
                                                             'is_bool' => true,
                                                             'desc'    => $this->l('When a product is on sale, displays the base price with a \'Before\' label.',
                                                                                   'advancedeucompliance'),
                                                             'values'  => array(array('id'    => 'active_on',
                                                                                      'value' => true,
                                                                                      'label' => $this->l('Enabled',
                                                                                                          'advancedeucompliance')
                                                                                ),
                                                                                array('id'    => 'active_off',
                                                                                      'value' => false,
                                                                                      'label' => $this->l('Disabled',
                                                                                                          'advancedeucompliance')
                                                                                )
                                                             ),
                                                       ),
                                                       array('type'    => 'switch',
                                                             'label'   => $this->l('Tax \'inc./excl.\' label',
                                                                                   'advancedeucompliance'),
                                                             'name'    => 'AEUC_LABEL_TAX_INC_EXC',
                                                             'is_bool' => true,
                                                             'desc'    => $this->l('Display whether the tax is included next to the product price (\'Tax included/excluded\' label).',
                                                                                   'advancedeucompliance'),
                                                             'values'  => array(array('id'    => 'active_on',
                                                                                      'value' => true,
                                                                                      'label' => $this->l('Enabled',
                                                                                                          'advancedeucompliance')
                                                                                ),
                                                                                array('id'    => 'active_off',
                                                                                      'value' => false,
                                                                                      'label' => $this->l('Disabled',
                                                                                                          'advancedeucompliance')
                                                                                )
                                                             ),
                                                       ),
                                                       array('type'    => 'switch',
                                                             'label'   => $this->l('Shipping fees \'Inc./Excl.\' label',
                                                                                   'advancedeucompliance'),
                                                             'name'    => 'AEUC_LABEL_SHIPPING_INC_EXC',
                                                             'is_bool' => true,
                                                             'desc'    => $this->l('Display whether the shipping fees are included, next to the product price (\'Shipping included / excluded\').',
                                                                                   'advancedeucompliance'),
                                                             'hint'    => $this->l('If enabled, make sure the Shipping terms are associated with a CMS page below (Legal Content Management). The label will link to this content.',
                                                                                   'advancedeucompliance'),
                                                             'values'  => array(
                                                                 array(
                                                                     'id'    => 'active_on',
                                                                     'value' => true,
                                                                     'label' => $this->l('Enabled',
                                                                                         'advancedeucompliance')
                                                                 ),
                                                                 array(
                                                                     'id'    => 'active_off',
                                                                     'value' => false,
                                                                     'label' => $this->l('Disabled',
                                                                                         'advancedeucompliance')
                                                                 )
                                                             ),
                                                       ),
                                                       array(
                                                           'type'    => 'switch',
                                                           'label'   => $this->l('Product weight label',
                                                                                 'advancedeucompliance'),
                                                           'name'    => 'AEUC_LABEL_WEIGHT',
                                                           'is_bool' => true,

                                                           'desc'    => sprintf($this->l('Display the weight of a product (when information is available and product weighs more than 1 %s).',
                                                                                         'advancedeucompliance'), Configuration::get('PS_WEIGHT_UNIT')),
                                                           'values'  => array(
                                                               array(
                                                                   'id'    => 'active_on',
                                                                   'value' => true,
                                                                   'label' => $this->l('Enabled',
                                                                                       'advancedeucompliance')
                                                               ),
                                                               array(
                                                                   'id'    => 'active_off',
                                                                   'value' => false,
                                                                   'label' => $this->l('Disabled',
                                                                                       'advancedeucompliance')
                                                               )
                                                           ),
                                                       ),
                                                       array(
                                                           'type'  => 'text',
                                                           'label' => $this->l('Decimals for product weight',
                                                                               'advancedeucompliance'),
                                                           'name'  => 'PS_PRODUCT_WEIGHT_PRECISION',
                                                           'desc'  => sprintf($this->l('Choose how many decimals to display for the product weight (e.g: 1 %s with 0 decimal, or 1.01 %s with 2 decimals)',
                                                                                       'advancedeucompliance'),
                                                                              Configuration::get('PS_WEIGHT_UNIT'),
                                                                              Configuration::get('PS_WEIGHT_UNIT')),
                                                           'hint'  => $this->l('This value must be positive.',
                                                                               'advancedeucompliance'),
                                                       ),
                                                       array(
                                                           'type'    => 'switch',
                                                           'label'   => $this->l('Revocation Terms within ToS',
                                                                                 'advancedeucompliance'),
                                                           'name'    => 'AEUC_LABEL_REVOCATION_TOS',
                                                           'is_bool' => true,
                                                           'desc'    => $this->l('Include content from the Revocation Terms CMS page within the Terms of Services (ToS).',
                                                                                 'advancedeucompliance'),
                                                           'hint'    => $this->l('If enabled, make sure the Revocation Terms are associated with a CMS page below (Legal Content Management).',
                                                                                 'advancedeucompliance'),
                                                           'disable' => true,
                                                           'values'  => array(
                                                               array(
                                                                   'id'    => 'active_on',
                                                                   'value' => true,
                                                                   'label' => $this->l('Enabled',
                                                                                       'advancedeucompliance')
                                                               ),
                                                               array(
                                                                   'id'    => 'active_off',
                                                                   'value' => false,
                                                                   'label' => $this->l('Disabled',
                                                                                       'advancedeucompliance')
                                                               )
                                                           ),
                                                       ),
                                                       array(
                                                           'type'    => 'switch',
                                                           'label'   => $this->l('Revocation for virtual products',
                                                                                 'advancedeucompliance'),
                                                           'name'    => 'AEUC_LABEL_REVOCATION_VP',
                                                           'is_bool' => true,
                                                           'desc'    => $this->l('Add a mandatory checkbox when the cart contains a virtual product. Use it to ensure customers are aware that a virtual product cannot be returned.',
                                                                                 'advancedeucompliance'),
                                                           'disable' => true,
                                                           'values'  => array(
                                                               array(
                                                                   'id'    => 'active_on',
                                                                   'value' => true,
                                                                   'label' => $this->l('Enabled',
                                                                                       'advancedeucompliance')
                                                               ),
                                                               array(
                                                                   'id'    => 'active_off',
                                                                   'value' => false,
                                                                   'label' => $this->l('Disabled',
                                                                                       'advancedeucompliance')
                                                               )
                                                           ),
                                                       ),
                                                       array(
                                                           'type'    => 'switch',
                                                           'label'   => $this->l('\'From\' price label (when combinations)'),
                                                           'name'    => 'AEUC_LABEL_COMBINATION_FROM',
                                                           'is_bool' => true,
                                                           'desc'    => $this->l('Display a \'From\' label before the price on products with combinations.',
                                                                                 'advancedeucompliance'),
                                                           'hint'    => $this->l('As prices can vary from a combination to another, this label indicates that the final price may be higher.',
                                                                                 'advancedeucompliance'),
                                                           'disable' => true,
                                                           'values'  => array(
                                                               array(
                                                                   'id'    => 'active_on',
                                                                   'value' => true,
                                                                   'label' => $this->l('Enabled',
                                                                                       'advancedeucompliance')
                                                               ),
                                                               array(
                                                                   'id'    => 'active_off',
                                                                   'value' => false,
                                                                   'label' => $this->l('Disabled',
                                                                                       'advancedeucompliance')
                                                               )
                                                           ),
                                                       ),
                                                       array(
                                                           'type'  => 'textarea',
                                                           'lang'  => true,
                                                           'label' => $this->l('Upper shopping cart text',
                                                                               'advancedeucompliance'),
                                                           'name'  => 'AEUC_SHOPPING_CART_TEXT_BEFORE',
                                                           'desc'  => $this->l('Add a custom text above the shopping cart summary.',
                                                                               'advancedeucompliance'),
                                                       ),
                                                       array(
                                                           'type'  => 'textarea',
                                                           'lang'  => true,
                                                           'label' => $this->l('Lower shopping cart text',
                                                                               'advancedeucompliance'),
                                                           'name'  => 'AEUC_SHOPPING_CART_TEXT_AFTER',
                                                           'desc'  => $this->l('Add a custom text at the bottom of the shopping cart summary.',
                                                                               'advancedeucompliance'),
                                                       ),

                                     ),
                                     'submit' => array(
                                         'title' => $this->l('Save', 'advancedeucompliance'),
                                     ),
        ),
        );
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormLabelsManagerValues()
    {
        $delivery_time_available_values = array();
        $delivery_time_oos_values = array();
        $shopping_cart_text_before_values = array();
        $shopping_cart_text_after_values = array();

        $langs = Language::getLanguages(false, false);

        foreach ($langs as $lang) {
            $tmp_id_lang = (int)$lang['id_lang'];
            $delivery_time_available_values[$tmp_id_lang] = Configuration::get('AEUC_LABEL_DELIVERY_TIME_AVAILABLE', $tmp_id_lang);
            $delivery_time_oos_values[$tmp_id_lang] = Configuration::get('AEUC_LABEL_DELIVERY_TIME_OOS', $tmp_id_lang);
            $shopping_cart_text_before_values[$tmp_id_lang] = Configuration::get('AEUC_SHOPPING_CART_TEXT_BEFORE', $tmp_id_lang);
            $shopping_cart_text_after_values[$tmp_id_lang] = Configuration::get('AEUC_SHOPPING_CART_TEXT_AFTER', $tmp_id_lang);
        }

        return array(
            'AEUC_LABEL_DELIVERY_TIME_AVAILABLE' => $delivery_time_available_values,
            'AEUC_LABEL_DELIVERY_TIME_OOS'       => $delivery_time_oos_values,
            'AEUC_LABEL_SPECIFIC_PRICE'          => Configuration::get('AEUC_LABEL_SPECIFIC_PRICE'),
            'AEUC_LABEL_TAX_INC_EXC'             => Configuration::get('AEUC_LABEL_TAX_INC_EXC'),
            'AEUC_LABEL_WEIGHT'                  => Configuration::get('AEUC_LABEL_WEIGHT'),
            'AEUC_LABEL_REVOCATION_TOS'          => Configuration::get('AEUC_LABEL_REVOCATION_TOS'),
            'AEUC_LABEL_REVOCATION_VP'           => Configuration::get('AEUC_LABEL_REVOCATION_VP'),
            'AEUC_LABEL_SHIPPING_INC_EXC'        => Configuration::get('AEUC_LABEL_SHIPPING_INC_EXC'),
            'AEUC_LABEL_COMBINATION_FROM'        => Configuration::get('AEUC_LABEL_COMBINATION_FROM'),
            'AEUC_SHOPPING_CART_TEXT_BEFORE'     => $shopping_cart_text_before_values,
            'AEUC_SHOPPING_CART_TEXT_AFTER'      => $shopping_cart_text_after_values,
            'PS_PRODUCT_WEIGHT_PRECISION'        => Configuration::get('PS_PRODUCT_WEIGHT_PRECISION')
        );
    }

    /**
     * Create the form that will let user choose all the wording options
     */
    protected function renderFormFeaturesManager()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitAEUC_featuresManager';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
                                . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormFeaturesManagerValues(),
            /* Add values for your inputs */
            'languages'    => $this->context->controller->getLanguages(),
            'id_language'  => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigFormFeaturesManager()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigFormFeaturesManager()
    {
        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Features', 'advancedeucompliance'),
                    'icon'  => 'icon-cogs',
                ),
                'input'  => array(
                    array(
                        'type'    => 'switch',
                        'label'   => $this->l('Enable \'Tell A Friend\' feature', 'advancedeucompliance'),
                        'name'    => 'AEUC_FEAT_TELL_A_FRIEND',
                        'is_bool' => true,
                        'desc'    => $this->l('Make sure you comply with your local legislation before enabling: the emails sent by this feature can be considered as unsolicited commercial emails.',
                                              'advancedeucompliance'),
                        'hint'    => $this->l('If enabled, the \'Send to a Friend\' module allows customers to send to a friend an email with a link to a product\'s page.',
                                              'advancedeucompliance'),
                        'values'  => array(
                            array(
                                'id'    => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled', 'advancedeucompliance')
                            ),
                            array(
                                'id'    => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled', 'advancedeucompliance')
                            )
                        ),
                    ),
                    array(
                        'type'    => 'switch',
                        'label'   => $this->l('Enable \'Reordering\' feature', 'advancedeucompliance'),
                        'hint'    => $this->l('If enabled, the \'Reorder\' option allows customers to reorder in one click from their Order History page.',
                                              'advancedeucompliance'),
                        'name'    => 'AEUC_FEAT_REORDER',
                        'is_bool' => true,
                        'desc'    => $this->l('Make sure you comply with your local legislation before enabling: it can be considered as unsolicited goods.',
                                              'advancedeucompliance'),
                        'values'  => array(
                            array(
                                'id'    => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled', 'advancedeucompliance')
                            ),
                            array(
                                'id'    => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled', 'advancedeucompliance')
                            )
                        ),
                    ),
                    array(
                        'type'    => 'switch',
                        'label'   => $this->l('Enable \'Advanced checkout page\''),
                        'hint'    => $this->l('The advanced checkout page displays the following sections: payment methods, address summary, ToS agreement, cart summary, and an \'Order with Obligation to Pay\' button.',
                                              'advancedeucompliance'),
                        'name'    => 'AEUC_FEAT_ADV_PAYMENT_API',
                        'is_bool' => true,
                        'desc'    => $this->l('To address some of the latest European legal requirements, the advanced checkout page displays additional information (terms of service, payment methods, etc) as a single page.',
                                              'advancedeucompliance'),
                        'values'  => array(
                            array(
                                'id'    => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled', 'advancedeucompliance')
                            ),
                            array(
                                'id'    => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled', 'advancedeucompliance')
                            )
                        ),
                    ),
                    array(
                        'type'    => 'switch',
                        'label'   => $this->l('Proportionate tax for shipping and wrapping',
                                              'advancedeucompliance'),
                        'name'    => 'PS_ATCP_SHIPWRAP',
                        'is_bool' => true,
                        'desc'    => $this->l('When enabled, tax for shipping and wrapping costs will be calculated proportionate to taxes applying to the products in the cart.',
                                              'advancedeucompliance'),
                        'values'  => array(
                            array(
                                'id'    => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled', 'advancedeucompliance')
                            ),
                            array(
                                'id'    => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled', 'advancedeucompliance')
                            )
                        ),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save', 'advancedeucompliance'),
                ),
            ),
        );
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormFeaturesManagerValues()
    {
        return array(
            'AEUC_FEAT_TELL_A_FRIEND'   => Configuration::get('AEUC_FEAT_TELL_A_FRIEND'),
            'AEUC_FEAT_REORDER'         => !Configuration::get('PS_DISALLOW_HISTORY_REORDERING'),
            'AEUC_FEAT_ADV_PAYMENT_API' => Configuration::get('AEUC_FEAT_ADV_PAYMENT_API'),
            'PS_ATCP_SHIPWRAP'          => Configuration::get('PS_ATCP_SHIPWRAP'),
        );
    }

    /**
     * Create the form that will let user manage his legal page trough "CMS" feature
     */
    protected function renderFormLegalContentManager()
    {
        $cms_roles_aeuc = $this->getCMSRoles();
        $cms_repository = $this->entity_manager->getRepository('CMS');
        $cms_role_repository = $this->entity_manager->getRepository('CMSRole');
        $cms_roles = $cms_role_repository->findByName(array_keys($cms_roles_aeuc));
        $cms_roles_assoc = array();
        $id_lang = Context::getContext()->employee->id_lang;
        $id_shop = Context::getContext()->shop->id;

        foreach ($cms_roles as $cms_role) {

            if ((int)$cms_role->id_cms > 0) {
                $cms_entity = $cms_repository->findOne((int)$cms_role->id_cms);
                $assoc_cms_name = $cms_entity->meta_title[(int)$id_lang];
            } else {
                $assoc_cms_name = $this->l('-- Select associated CMS page --', 'advancedeucompliance');
            }

            $cms_roles_assoc[(int)$cms_role->id] = array('id_cms'     => (int)$cms_role->id_cms,
                                                         'page_title' => (string)$assoc_cms_name,
                                                         'role_title' => (string)$cms_roles_aeuc[$cms_role->name]
            );
        }

        $cms_pages = $cms_repository->i10nFindAll($id_lang, $id_shop);
        $fake_object = new stdClass();
        $fake_object->id = 0;
        $fake_object->meta_title = $this->l('-- Select associated CMS page --', 'advancedeucompliance');
        $cms_pages[-1] = $fake_object;
        unset($fake_object);

        $this->context->smarty->assign(array(
                                           'cms_roles_assoc' => $cms_roles_assoc,
                                           'cms_pages'       => $cms_pages,
                                           'form_action'     => $this->context->link->getAdminLink('AdminModules') . '&configure=' . $this->name,
                                           'add_cms_link'    => $this->context->link->getAdminLink('AdminCMS')
                                       ));

        return $this->display(__FILE__, 'views/templates/admin/legal_cms_manager_form.tpl');
    }

    protected function renderFormEmailAttachmentsManager()
    {
        $cms_roles_aeuc = $this->getCMSRoles();
        $cms_role_repository = $this->entity_manager->getRepository('CMSRole');
        $cms_roles_associated = $cms_role_repository->getCMSRolesAssociated();
        $legal_options = array();
        $cleaned_mails_names = array();

        foreach ($cms_roles_associated as $role) {
            $list_id_mail_assoc = AeucCMSRoleEmailEntity::getIdEmailFromCMSRoleId((int)$role->id);
            $clean_list = array();

            foreach ($list_id_mail_assoc as $list_id_mail_assoc) {
                $clean_list[] = $list_id_mail_assoc['id_mail'];
            }

            $legal_options[$role->name] = array(
                'name'               => $cms_roles_aeuc[$role->name],
                'id'                 => $role->id,
                'list_id_mail_assoc' => $clean_list
            );
        }

        foreach (AeucEmailEntity::getAll() as $email) {
            $cleaned_mails_names[] = $email;
        }

        $this->context->smarty->assign(array(
                                           'has_assoc'       => $cms_roles_associated,
                                           'mails_available' => $cleaned_mails_names,
                                           'legal_options'   => $legal_options,
                                           'form_action'     => $this->context->link->getAdminLink('AdminModules') . '&configure=' . $this->name
                                       ));

        // Insert JS in the page
        $this->context->controller->addJS(($this->_path) . 'views/js/email_attachement.js');

        return $this->display(__FILE__, 'views/templates/admin/email_attachments_form.tpl');
    }
}
