<div class="box">
    <p class="carrier_title">{l s='Terms of service'}</p>
    <p class="checkbox">
        <input type="checkbox" name="cgv" id="cgv" value="1" {if $checkedTOS}checked="checked"{/if} />
        <label for="cgv">{l s='I agree to the terms of service and the terms of revocation and will adhere to them unconditionally.'}</label>
        <a href="{$link_conditions|escape:'html':'UTF-8'}" class="iframe" rel="nofollow">{l s='(Read the Terms of Service)'}</a>
        <a href="{$link_revocations|escape:'html':'UTF-8'}" class="iframe" rel="nofollow">{l s='(Read the Terms of Revocation)'}</a>
    </p>
</div>
<script type="text/javascript">
    $(document).ready(function(){
        if (!!$.prototype.fancybox)
            $("a.iframe").fancybox({
                'type': 'iframe',
                'width': 600,
                'height': 600
            });
    })
</script>