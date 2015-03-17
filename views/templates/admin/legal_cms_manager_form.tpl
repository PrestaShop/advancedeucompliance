{*
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
*}

<form id="module_form_3" class="defaultForm form-horizontal" action="{$form_action}" method="POST" enctype="multipart/form-data" novalidate>
    <input type="hidden" name="submitAEUC_legalContentManager" value="1">
    <div class="panel">
        <div class="panel-heading">
            <i class="icon-cogs"></i>
            {l s='Legal content management' mod='advancedeucompliance'}
        </div>
        <p>
            {l s='Here you can associate different legal content to supported legal options' mod='advancedeucompliance'}
        </p>

        <div class="form-wrapper">

                {foreach from=$legal_options key=key_parent item=legal_option}
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            {$legal_option}
                        </label>

                        <div class="col-lg-9">
                            <select class="form-control fixed-width-xxl " name="{$key_parent}" id="{$key_parent}">
                            {foreach from=$cms_pages key=key_child item=cms_page}
                                <option value="{$cms_page['id_cms']}">{$cms_page['meta_title']}</option>
                            {/foreach}
                            </select>
                        </div>
                    </div>
                {/foreach}
        </div>

        <div class="panel-footer">
            <button type="submit" value="1" id="module_form_submit_btn_1" name="submitAEUC_legalContentManager_btn" class="btn btn-default pull-right">
                <i class="process-icon-save"></i>  {l s='Save' mod='advancedeucompliance'}
            </button>
            <a href="{$add_cms_link|escape:'html':'UTF-8'}" class="btn btn-default" name="submitAEUC_addCMS">
                <i class="process-icon-plus"></i> {l s='Add new CMS Page' mod='advancedeucompliance'}
            </a>
        </div>

    </div>


</form>
