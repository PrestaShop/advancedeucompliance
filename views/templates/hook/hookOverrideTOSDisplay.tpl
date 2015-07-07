{**
 * 2007-2015 PrestaShop
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
 *  @author 	PrestaShop SA <contact@prestashop.com>
 *  @copyright  2007-2015 PrestaShop SA
 *  @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 *}

<div class="row">
    <div class="col-xs-12 col-md-12">

        {if $has_tos_override_opt}
            <h2>{l s='Terms and Conditions' mod='advancedeucompliance'}</h2>
            <div class="tnc_box">
                <p class="checkbox">
                    <input type="checkbox" name="cgv" id="cgv" value="1" {if isset($checkedTOS) && $checkedTOS}checked="checked"{/if}/>
                    {if isset($link_conditions) && $link_conditions}
                        <label for="cgv">{l s='I agree to the' mod='advancedeucompliance'}</label> <a href="{$link_conditions|escape:'html':'UTF-8'}" class="iframe" rel="nofollow">{l s='terms of service'  mod='advancedeucompliance'}</a>
                    {/if}
                    {if isset($link_revocations) && $link_revocations}
                        <label for="cgv">{l s='and'  mod='advancedeucompliance'}</label> <a href="{$link_revocations|escape:'html':'UTF-8'}" class="iframe" rel="nofollow">{l s='terms of revocation' mod='advancedeucompliance'}</a>
                    {/if}
                    <label for="cgv">{l s='adhere to them unconditionally.'  mod='advancedeucompliance'}</label>
                </p>
            </div>
        {else}
            <h2>{l s='Terms and Conditions' mod='advancedeucompliance'}</h2>
            <div class="box">
                <p class="checkbox">
                    <input type="checkbox" name="cgv" id="cgv" value="1" {if $checkedTOS}checked="checked"{/if} />
                    <label for="cgv">{l s='I agree to the terms of service and will adhere to them unconditionally.'  mod='advancedeucompliance'}</label>
                    <a href="{$link_conditions|escape:'html':'UTF-8'}" class="iframe" rel="nofollow">{l s='(Read the Terms of Service).' mod='advancedeucompliance'}</a>
                </p>
            </div>
        {/if}


        {if $has_virtual_product}
            <div class="tnc_box">
                <p class="checkbox">
                    <input type="checkbox" name="revocation_vp_terms_agreed" id="revocation_vp_terms_agreed" value="1"/>
                    <label for="revocation_vp_terms_agreed">{l s='I agree that the digital products in my cart can not be returned or refunded due to the nature of such products.' mod='advancedeucompliance'}</label>
                </p>
            </div>
        {/if}

    </div>
</div>
