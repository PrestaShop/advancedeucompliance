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

{* @TODO: Create content depending on LEGAL CONTENT MANAGEMENT mapped CMS page *}


<form id="module_form_4" class="defaultForm form-horizontal" action="{$form_action}" method="POST" enctype="multipart/form-data" novalidate>
    <input type="hidden" name="submitAEUC_emailAttachmentsManager" value="1">
    <div class="panel">
        <div class="panel-heading">
            <i class="icon-envelope"></i>
            {l s='Email attachments associations' mod='advancedeucompliance'}
        </div>
        <p>
            {l s='Here you can choose which files has to be attached for a given email' mod='advancedeucompliance'}
        </p>

        <div class="form-wrapper">

            <table class="table accesses">
                <thead>
                <tr>
                    <th><span class="title_box">{l s='Email templates'}</span></th>

                    {* @TODO: Loop over legal content mapped to a cms page *}
                    <th class="center fixed-width-xs"><span class="title_box">{l s='A legal content'}</span></th>


                </tr>
                </thead>
                <tbody>
                <tr>
                    <th></th>
                    <th class="center"><input type="checkbox" class="all_get get " /></th>

                </tr>


                </tbody>
            </table>

        </div>

        <div class="panel-footer">
            <button type="submit" value="1" id="module_form_submit_btn_1" name="submitAEUC_emailAttachmentsManager_btn" class="btn btn-default pull-right">
                <i class="process-icon-save"></i>  {l s='Save' mod='advancedeucompliance'}
            </button>

        </div>

    </div>


</form>


