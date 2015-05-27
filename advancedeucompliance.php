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
	private $repository_manager;

	/* Constants used for LEGAL/CMS Management */
	// TODO: Remove this once in DB
	const LEGAL_NO_ASSOC		= 'NO_ASSOC';
	const LEGAL_NOTICE			= 'LEGAL_NOTICE';
	const LEGAL_CONDITIONS 		= 'LEGAL_CONDITIONS';
	const LEGAL_REVOCATION 		= 'LEGAL_REVOCATION';
	const LEGAL_REVOCATION_FORM = 'LEGAL_REVOCATION_FORM';
	const LEGAL_PRIVACY 		= 'LEGAL_PRIVACY';
	const LEGAL_ENVIRONMENTAL 	= 'LEGAL_ENVIRONMENTAL';
	const LEGAL_SHIP_PAY 		= 'LEGAL_SHIP_PAY';
	/* End of LEGAL/CMS Constants declarations */

	public function __construct(RepositoryManager $repository_manager)
	{
		$this->name = 'advancedeucompliance';
		$this->tab = 'administration';
		$this->version = '1.0.0';
		$this->author = 'PrestaShop';
		$this->need_instance = 0;
		$this->bootstrap = true;

		parent::__construct();

		$this->repository_manager = $repository_manager;

		$this->displayName = $this->l('Advanced EU Compliance');
		$this->description = $this->l('This module provides compliancy with applicable e-commerce law for European merchants');
		$this->confirmUninstall = $this->l('Are you sure you want to uninstall this module?');
	}

	/**
	 * Don't forget to create update methods if needed:
	 * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
	 */
	public function install()
	{
		return parent::install() &&
				$this->loadTables() &&
				$this->createConfig();
	}

	public function uninstall()
	{
		return parent::uninstall() &&
				$this->dropConfig();
	}

	public function createConfig()
	{
		// @TODO: Create config from localization pack ? (ATM everythings goeas to TRUE)
		return Configuration::updateValue('AEUC_FEAT_TELL_A_FRIEND', true) &&
				Configuration::updateValue('AEUC_FEAT_REORDER', true) &&
				Configuration::updateValue('AEUC_LABEL_DELIVERY_TIME', true) &&
				Configuration::updateValue('AEUC_LABEL_SPECIFIC_PRICE', true) &&
				Configuration::updateValue('AEUC_LABEL_TAX_INC_EXC', true) &&
				Configuration::updateValue('AEUC_LABEL_WEIGHT', true);
	}

	public function loadTables()
	{
		// Fillin CMS ROLE, temporary hard values (should be parsed from localization pack later)
		$roles_array = $this->getCMSRoles();
		$roles = array_keys($roles_array);
		$cms_role_repository = $this->repository_manager->getRepository('CMSRole');

		foreach ($roles as $role)
		{

			if (!$cms_role_repository->getRoleByName($role))
			{
				$cms_role = $cms_role_repository->createNewRecord();
				$cms_role->id_cms = 0; // No assoc at this time
				$cms_role->name = $role;
				$cms_role->save();
			}
		}


		return true;
	}


	public function dropConfig()
	{
		return Configuration::deleteByName('AEUC_FEAT_TELL_A_FRIEND') &&
				Configuration::deleteByName('AEUC_FEAT_REORDER') &&
				Configuration::deleteByName('AEUC_LABEL_DELIVERY_TIME') &&
				Configuration::deleteByName('AEUC_LABEL_SPECIFIC_PRICE') &&
				Configuration::deleteByName('AEUC_LABEL_TAX_INC_EXC') &&
				Configuration::deleteByName('AEUC_LABEL_WEIGHT');
	}

	/**
	 * Load the configuration form
	 */
	public function getContent()
	{
		/**
		 * If values have been submitted in the form, process.
		 */
		$success_band = $this->_postProcess();
		$this->context->smarty->assign('module_dir', $this->_path);

		// Render all required form for each 'part'
		$formLabelsManager = $this->renderFormLabelsManager();
		$formFeaturesManager = $this->renderFormFeaturesManager();
		$formLegalContentManager = $this->renderFormLegalContentManager();
		$formEmailAttachmentsManager = $this->renderFormEmailAttachmentsManager();

		return 	$success_band.
				$formLabelsManager.
				$formFeaturesManager.
				$formLegalContentManager.
				$formEmailAttachmentsManager;
	}

	/**
	 * Save form data.
	 */
	protected function _postProcess()
	{
		$has_processed_something = false;

		if (Tools::isSubmit('submitAEUC_labelsManager')) {
			$this->_postProcessLabelsManager();
			$has_processed_something = true;
		}

		if (Tools::isSubmit('submitAEUC_featuresManager')) {
			$this->_postProcessFeaturesManager();
			$has_processed_something = true;
		}

		if (Tools::isSubmit('submitAEUC_legalContentManager')) {
			$this->_postProcessLegalContentManager();
			$has_processed_something = true;
		}

		if ($has_processed_something)
			return $this->displayConfirmation($this->l('Settings saved successfully!'));
		else
			return '';
	}

	protected function _postProcessLabelsManager()
	{
		$post_keys = array_keys($this->getConfigFormLabelsManagerValues());

		foreach ($post_keys as $key)
		{
			if (Tools::isSubmit($key))
				Configuration::updateValue($key, Tools::getValue($key));
		}
	}

	protected function _postProcessFeaturesManager()
	{
		$post_keys = array_keys($this->getConfigFormFeaturesManagerValues());

		foreach ($post_keys as $key)
		{
			if (Tools::isSubmit($key))
				Configuration::updateValue($key, Tools::getValue($key));
		}
	}

	protected function _postProcessLegalContentManager()
	{

		$posted_values = Tools::getAllValues();
		$cms_role_repository = $this->repository_manager->getRepository('CMSRole');

		foreach ($posted_values as $key_name => $assoc_cms_id)
		{
			if (strpos($key_name, 'CMSROLE_') !== false)
			{
				$exploded_key_name = explode('_', $key_name);
				$cms_role = $cms_role_repository->getRecordById((int)$exploded_key_name[1]);
				$cms_role->id_cms = (int)$assoc_cms_id;
				$cms_role->update();
			}
		}
		unset($cms_role);
	}


	// @TODO: To be moved to the core ?
	protected function getCMSRoles()
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
						'name' => 'AEUC_LABEL_DELIVERY_TIME',
						'is_bool' => true,
						'desc' => $this->l('Option to display the estimated delivery time for products'),
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
						'name' => 'AEUC_LABEL_SPECIFIC_PRICE',
						'is_bool' => true,
						'desc' => $this->l('Option to display a label for products with specific price'),
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
						'name' => 'AEUC_LABEL_TAX_INC_EXC',
						'is_bool' => true,
						'desc' => $this->l('Option to display a tax included/excluded label next to product\'s price'),
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
						'name' => 'AEUC_LABEL_WEIGHT',
						'is_bool' => true,
						'desc' => $this->l('Option to display product\'s weight on product\'s details (if available)'),
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
			'AEUC_LABEL_DELIVERY_TIME' => Configuration::get('AEUC_LABEL_DELIVERY_TIME'),
			'AEUC_LABEL_SPECIFIC_PRICE' => Configuration::get('AEUC_LABEL_SPECIFIC_PRICE'),
			'AEUC_LABEL_TAX_INC_EXC' => Configuration::get('AEUC_LABEL_TAX_INC_EXC'),
			'AEUC_LABEL_WEIGHT' => Configuration::get('AEUC_LABEL_WEIGHT'),
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
						'name' => 'AEUC_FEAT_TELL_A_FRIEND',
						'is_bool' => true,
						'desc' => $this->l('Option to enable the "Tell A Friend" feature'),
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
						'name' => 'AEUC_FEAT_REORDER',
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
			'AEUC_FEAT_TELL_A_FRIEND' => Configuration::get('AEUC_FEAT_TELL_A_FRIEND'),
			'AEUC_FEAT_REORDER' => Configuration::get('AEUC_FEAT_REORDER'),
		);
	}

	/**
	 * Create the form that will let user manage his legal page trough "CMS" feature
	 */
	protected function renderFormLegalContentManager()
	{
		$cms_repository = $this->repository_manager->getRepository('CMS');
		$cms_role_repository = $this->repository_manager->getRepository('CMSRole');
		$cms_roles = $cms_role_repository->getCMSRolesWhereNamesIn(array_keys($this->getCMSRoles()));
		$cms_roles_assoc = array();

		foreach ($cms_roles as $cms_role)
		{
			if ((int)$cms_role['id_cms'] != 0)
			{
				$cms_entity = $cms_repository->getRecordById((int)$cms_role['id_cms'], true);
				$assoc_cms_name = $cms_entity->name;
			}
			else
				$assoc_cms_name = $this->l('No association (means disabled)');

			$cms_roles_assoc[(int)$cms_role['id_cms_role']] = array('id_cms' => (int)$cms_role['id_cms'],
																	'page_title' => (string)$assoc_cms_name,
																	'role_title' => (string)$cms_role['name']);
		}

		$cms_pages = $cms_repository->getCMSPagesList();
		array_unshift($cms_pages, array('id_cms' => 0, 'meta_title' => $this->l('No association (means disabled)')));

		$this->context->smarty->assign(array(
			'cms_roles_assoc' => $cms_roles_assoc,
			'cms_pages' => $cms_pages,
			'form_action' => '#',
			'add_cms_link' => $this->context->link->getAdminLink('AdminCMS')
		));
		$content = $this->context->smarty->fetch($this->local_path.'views/templates/admin/legal_cms_manager_form.tpl');
		return $content;
	}




	protected function renderFormEmailAttachmentsManager()
	{
		$this->context->smarty->assign(array(
			'mails_available' => $this->getAvailableMails(),
			'legal_options' => $this->getCMSRoles()
		));
		$content = $this->context->smarty->fetch($this->local_path.'views/templates/admin/email_attachments_form.tpl');
		return $content;
	}



	/**
	 * THIS SECTION CONTAINS ALL THE METHODS THAT SHOULD BE MOVED INTO THE CORE OF PRESTASHOP. BUT LATER ! :)
	 */

	// @TODO: Dont know yet where to copy/past this one, any idea ?
	public function getAvailableMails($lang = null, $dir = null)
	{
		if (is_null($lang))
			$iso_lang = Language::getIsoById((int)Configuration::get('PS_LANG_DEFAULT'));
		else
			$iso_lang = $lang;

		if (is_null($dir))
			$mail_directory = _PS_MAIL_DIR_.$iso_lang.DIRECTORY_SEPARATOR;
		else
			$mail_directory = $dir;

		if (!file_exists($mail_directory))
			return null;

		// @TODO: Make scanned directory dynamic ?
		$mail_directory = $this->getDirContentRecursive(_PS_MAIL_DIR_.$iso_lang.DIRECTORY_SEPARATOR);
		// Prestashop Mail should only be at root level
		$mail_directory = $mail_directory['root'];
		$clean_mail_list = array();

		// Remove duplicate .html / .txt / .tpl
		foreach ($mail_directory as $mail) {
			$exploded_filename = explode('.', $mail, 3);
			// Avoid badly named mail templates
			if (is_array($exploded_filename) && count($exploded_filename) == 2) {
				$clean_filename = (string)$exploded_filename[0];
				if (!in_array($clean_filename, $clean_mail_list)) {
					$clean_mail_list[] = $clean_filename;
				}
			}
		}
		return $clean_mail_list;
	}

	// @TODO: To put into Tools (return content of current) ?
	public function getDirContentRecursive($dir, $is_iterating = false)
	{
		if (!file_exists($dir) || !is_dir($dir))
			return false;

		$content_dir_scanned = scandir($dir);
		$content_list = array();

		if (!$content_dir_scanned)
			return false;

		foreach ($content_dir_scanned as $entry)
		{
			if ($entry != '.' && $entry != '..') {
				if (is_dir($dir . DIRECTORY_SEPARATOR . $entry)) {
					$recurse_iteration = $this->getDirContentRecursive($dir . DIRECTORY_SEPARATOR . $entry, true);
					if ($recurse_iteration)
						$content_list[$entry] = $recurse_iteration;
				} else {
					if ($is_iterating)
						$content_list[] = $entry;
					else
						$content_list['root'][] = $entry;
				}
			}
		}

		return $content_list;
	}




}
