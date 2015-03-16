<?php
/**
* 2007-2014 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
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
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2014 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_'))
	exit;

class Advancedeucompliance extends Module
{
	/* Class members */
	protected $config_form = false;

	/* Constants used for LEGAL/CMS Management */
	const LEGAL_NO_ASSOC		= 'NO_ASSOC';
	const LEGAL_NOTICE			= 'LEGAL_NOTICE';
	const LEGAL_CONDITIONS 		= 'LEGAL_CONDITIONS';
	const LEGAL_REVOCATION 		= 'LEGAL_REVOCATION';
	const LEGAL_REVOCATION_FORM = 'LEGAL_REVOCATION_FORM';
	const LEGAL_PRIVACY 		= 'LEGAL_PRIVACY';
	const LEGAL_ENVIRONMENTAL 	= 'LEGAL_ENVIRONMENTAL';
	const LEGAL_SHIP_PAY 		= 'LEGAL_SHIP_PAY';
	/* End of LEGAL/CMS Constants declarations */

	public function __construct()
	{
		$this->name = 'advancedeucompliance';
		$this->tab = 'administration';
		$this->version = '1.0.0';
		$this->author = 'PrestaShop';
		$this->need_instance = 0;
		$this->bootstrap = true;

		parent::__construct();

		$this->displayName = $this->l('Advanced EU Compliance');
		$this->description = $this->l('This module will help European merchants to get compliant with their countries e-commerce laws');
		$this->confirmUninstall = $this->l('Are you sure you cant to uninstall this module ?');
	}

	/**
	 * Don't forget to create update methods if needed:
	 * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
	 */
	public function install()
	{
		return parent::install();
	}

	public function uninstall()
	{
		return parent::uninstall();
	}

	/**
	 * Load the configuration form
	 */
	public function getContent()
	{
		/**
		 * If values have been submitted in the form, process.
		 */
		$this->_postProcess();
		$this->context->smarty->assign('module_dir', $this->_path);

		// Render all required form for each 'part'
		$formLabelsManager = $this->renderFormLabelsManager();
		$formFeaturesManager = $this->renderFormFeaturesManager();
		$formLegalContentManager = $this->renderFormLegalContentManager();
		$formEmailAttachmentsManager = $this->renderFormEmailAttachmentsManager();

		return $formLabelsManager.
				$formFeaturesManager.
				$formLegalContentManager.
				$formEmailAttachmentsManager;
	}

	/**
	 * Save form data.
	 */
	protected function _postProcess()
	{
		$form_values = $this->getConfigFormLabelsManagerValues();

		foreach (array_keys($form_values) as $key)
			Configuration::updateValue($key, Tools::getValue($key));
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
		$helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
			.'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');

		$helper->tpl_vars = array(
			'fields_value' => $this->getConfigFormLabelsManagerValues(), /* Add values for your inputs */
			'languages' => $this->context->controller->getLanguages(),
			'id_language' => $this->context->language->id,
		);

		return $helper->generateForm(array($this->getConfigFormLabelsManager()));
	}

	/**
	 * Create the structure of your form.
	 */
	protected function getConfigFormLabelsManager()
	{
		return array(
			'form' => array(
				'legend' => array(
				'title' => $this->l('Labeling Management'),
				'icon' => 'icon-tags',
				),
				'input' => array(
					array(
						'type' => 'switch',
						'label' => $this->l('Display delivery time label'),
						'name' => 'ADVANCEDEUCOMPLIANCE_DELIVERY_TIME_LABEL_OPT',
						'is_bool' => true,
						'desc' => $this->l('Whether to display estimated delivery time on products'),
						'values' => array(
							array(
								'id' => 'active_on',
								'value' => true,
								'label' => $this->l('Enabled')
							),
							array(
								'id' => 'active_off',
								'value' => false,
								'label' => $this->l('Disabled')
							)
						),
					),
					array(
						'type' => 'switch',
						'label' => $this->l('Display specific price label'),
						'name' => 'ADVANCEDEUCOMPLIANCE_SPECIFIC_PRICE_LABEL_OPT',
						'is_bool' => true,
						'desc' => $this->l('Whether to display a label before products with specific price'),
						'values' => array(
							array(
								'id' => 'active_on',
								'value' => true,
								'label' => $this->l('Enabled')
							),
							array(
								'id' => 'active_off',
								'value' => false,
								'label' => $this->l('Disabled')
							)
						),
					),
					array(
						'type' => 'switch',
						'label' => $this->l('Display Tax "Inc./Excl." label'),
						'name' => 'ADVANCEDEUCOMPLIANCE_TAX_INC_EXC_LABEL_OPT',
						'is_bool' => true,
						'desc' => $this->l('Whether to display tax included/excluded label next to product\'s price'),
						'values' => array(
							array(
								'id' => 'active_on',
								'value' => true,
								'label' => $this->l('Enabled')
							),
							array(
								'id' => 'active_off',
								'value' => false,
								'label' => $this->l('Disabled')
							)
						),
					),
					array(
						'type' => 'switch',
						'label' => $this->l('Display product weight label'),
						'name' => 'ADVANCEDEUCOMPLIANCE_WEIGHT_LABEL_OPT',
						'is_bool' => true,
						'desc' => $this->l('Whether to display product\'s weight on product\'s sheet (when available)'),
						'values' => array(
							array(
								'id' => 'active_on',
								'value' => true,
								'label' => $this->l('Enabled')
							),
							array(
								'id' => 'active_off',
								'value' => false,
								'label' => $this->l('Disabled')
							)
						),
					),
				),
				'submit' => array(
					'title' => $this->l('Save'),
				),
			),
		);
	}

	/**
	 * Set values for the inputs.
	 */
	protected function getConfigFormLabelsManagerValues()
	{
		return array(
			'ADVANCEDEUCOMPLIANCE_DELIVERY_TIME_LABEL_OPT' => Configuration::get('ADVANCEDEUCOMPLIANCE_DELIVERY_TIME_LABEL_OPT', true),
			'ADVANCEDEUCOMPLIANCE_SPECIFIC_PRICE_LABEL_OPT' => Configuration::get('ADVANCEDEUCOMPLIANCE_SPECIFIC_PRICE_LABEL_OPT', true),
			'ADVANCEDEUCOMPLIANCE_TAX_INC_EXC_LABEL_OPT' => Configuration::get('ADVANCEDEUCOMPLIANCE_TAX_INC_EXC_LABEL_OPT', true),
			'ADVANCEDEUCOMPLIANCE_WEIGHT_LABEL_OPT' => Configuration::get('ADVANCEDEUCOMPLIANCE_WEIGHT_LABEL_OPT', true),
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
			.'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');

		$helper->tpl_vars = array(
			'fields_value' => $this->getConfigFormFeaturesManagerValues(), /* Add values for your inputs */
			'languages' => $this->context->controller->getLanguages(),
			'id_language' => $this->context->language->id,
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
					'title' => $this->l('Features Management'),
					'icon' => 'icon-cogs',
				),
				'input' => array(
					array(
						'type' => 'switch',
						'label' => $this->l('Enable/Disable "Tell A Friend" feature'),
						'name' => 'ADVANCEDEUCOMPLIANCE_TELL_A_FRIEND_OPT',
						'is_bool' => true,
						'desc' => $this->l('Whether to enable "Tell A Friend" feature'),
						'values' => array(
							array(
								'id' => 'active_on',
								'value' => true,
								'label' => $this->l('Enabled')
							),
							array(
								'id' => 'active_off',
								'value' => false,
								'label' => $this->l('Disabled')
							)
						),
					),
					array(
						'type' => 'switch',
						'label' => $this->l('Enable/Disable "Reorder" feature'),
						'name' => 'ADVANCEDEUCOMPLIANCE_REORDER_OPT',
						'is_bool' => true,
						'desc' => $this->l('Whether to enable "Reorder" feature'),
						'values' => array(
							array(
								'id' => 'active_on',
								'value' => true,
								'label' => $this->l('Enabled')
							),
							array(
								'id' => 'active_off',
								'value' => false,
								'label' => $this->l('Disabled')
							)
						),
					),
				),
				'submit' => array(
					'title' => $this->l('Save'),
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
			'ADVANCEDEUCOMPLIANCE_TELL_A_FRIEND_OPT' => Configuration::get('ADVANCEDEUCOMPLIANCE_TELL_A_FRIEND_OPT', true),
			'ADVANCEDEUCOMPLIANCE_REORDER_OPT' => Configuration::get('ADVANCEDEUCOMPLIANCE_REORDER_OPT', true),
		);
	}

	/**
	 * Create the form that will let user manage his legal page trough "CMS" feature
	 */
	protected function renderFormLegalContentManager()
	{
		$legal_options = $this->getLegalOptions();
		// @TODO: Check for empty result on static call and check if result is array before unshifting
		$cms_pages = CMS::listCms();
		array_unshift($cms_pages, array('id_cms' => -1, 'meta_title' => $this->l('No association (means disabled)')));
		$this->context->smarty->assign(array(
			'cms_pages' => $cms_pages,
			'legal_options' => $legal_options,
			'form_action' => '#',
		));
		$content = $this->context->smarty->fetch($this->local_path.'views/templates/admin/legal_cms_manager_form.tpl');
		return $content;
	}


	// @TODO: To be moved to the core ?
	protected function getLegalOptions()
	{
		return array(
			Advancedeucompliance::LEGAL_NOTICE 			=> $this->l('Legal notice'),
			Advancedeucompliance::LEGAL_CONDITIONS 		=> $this->l('Conditions'),
			Advancedeucompliance::LEGAL_REVOCATION 		=> $this->l('Revocation'),
			Advancedeucompliance::LEGAL_REVOCATION_FORM => $this->l('Revocation Form'),
			Advancedeucompliance::LEGAL_PRIVACY 		=> $this->l('Privacy'),
			Advancedeucompliance::LEGAL_ENVIRONMENTAL 	=> $this->l('Environmental'),
			Advancedeucompliance::LEGAL_SHIP_PAY		=> $this->l('Shipping and payment')
		);
	}

	protected function renderFormEmailAttachmentsManager()
	{
		die(var_dump($this->getAvailableMails()));
		$content = $this->context->smarty->fetch($this->local_path.'views/templates/admin/email_attachments_form.tpl');
		return $content;
	}



	/**
	 * THIS SECTION CONTAINS ALL THE METHODS THAT SHOULD BE MOVED INTO THE CORE OF PRESTASHOP. BUT LATER ! :)
	 */

	// Dont know yet where to copy/past this one, any idea ?
	public function getAvailableMails($lang = null, $dir = null)
	{
		if (is_null($iso_lang))
			$iso_lang = Language::getIsoById((int)Configuration::get('PS_LANG_DEFAULT'));
		else
			$iso_lang = $lang;

		if (is_null($dir))
			$mail_directory = _PS_MAIL_DIR_.$default_shop_iso_lang.DIRECTORY_SEPARATOR;
		else
			$mail_directory = $dir;

		if (!file_exists($mail_directory))
			return null;


		return $mail_directory;
	}


	// To put into Tools (return content of current
	public function getDirContent($dir)
	{
		if (!file_exists($dir) || !is_dir($dir))
			return null;

		$dir_content_array = scandir($dir);

		return $dir_content_array;
	}

	// To put into Tools (return content of current
	public function getDirContentRecursive($dir, $content_list)
	{
		if (!file_exists($dir) || !is_dir($dir))
			return null;

		$dir_content_array = array_map()scandir($dir);

		return $content_list;
	}


}
