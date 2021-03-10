	{if !empty($confirmationError) }
		<div class="alert alert-danger">
			{$confirmationError}
		</div>
	{/if}
	
	
	<!-- Addresses -->
	<div class="adresses_bloc">
		<div class="row">
			{if isset($address_delivery)}
			<div class="col-xs-12 col-sm-6"{if $virtual_cart} style="display:none;"{/if}>
				<ul class="address alternate_item box">
					<li><h3 class="page-subheading">{l s='Delivery address'} ({$address_delivery->alias})</h3></li>
					{foreach from=$dlv_adr_fields name=dlv_loop item=field_item}
						{if $field_item eq "company" && isset($address_delivery->company)}<li class="address_company">{$address_delivery->company|escape:'html':'UTF-8'}</li>
						{elseif $field_item eq "address2" && $address_delivery->address2}<li class="address_address2">{$address_delivery->address2|escape:'html':'UTF-8'}</li>
						{elseif $field_item eq "phone_mobile" && $address_delivery->phone_mobile}<li class="address_phone_mobile">{$address_delivery->phone_mobile|escape:'html':'UTF-8'}</li>
						{else}
								{assign var=address_words value=" "|explode:$field_item}
								<li>{foreach from=$address_words item=word_item name="word_loop"}{if !$smarty.foreach.word_loop.first} {/if}<span class="address_{$word_item|replace:',':''}">{$deliveryAddressFormatedValues[$word_item|replace:',':'']|escape:'html':'UTF-8'}</span>{/foreach}</li>
						{/if}
					{/foreach}
				</ul>
			</div>
			{/if}
			{if isset($address_invoice)}
			<div class="col-xs-12 col-sm-6">
				<ul class="address item {if $virtual_cart}full_width{/if} box">
					<li><h3 class="page-subheading">{l s='Invoice address'} ({$address_invoice->alias})</h3></li>
					{foreach from=$inv_adr_fields name=inv_loop item=field_item}
						{if $field_item eq "company" && isset($address_invoice->company)}<li class="address_company">{$address_invoice->company|escape:'html':'UTF-8'}</li>
						{elseif $field_item eq "address2" && $address_invoice->address2}<li class="address_address2">{$address_invoice->address2|escape:'html':'UTF-8'}</li>
						{elseif $field_item eq "phone_mobile" && $address_invoice->phone_mobile}<li class="address_phone_mobile">{$address_invoice->phone_mobile|escape:'html':'UTF-8'}</li>
						{else}
								{assign var=address_words value=" "|explode:$field_item}
								<li>{foreach from=$address_words item=word_item name="word_loop"}{if !$smarty.foreach.word_loop.first} {/if}<span class="address_{$word_item|replace:',':''}">{$invoiceAddressFormatedValues[$word_item|replace:',':'']|escape:'html':'UTF-8'}</span>{/foreach}</li>
						{/if}
					{/foreach}
				</ul>
			</div>
			{/if}
		</div>
	</div>
	
	<!-- Product Listing -->
	{include file='checkout/_partials/cart-summary.tpl' cart = $cart}
	<!-- end order-detail-content -->
	
	
	<div class="cw-external-checkout-gtc">
		{if $conditions AND $cms_id}
			<p class="carrier_title">{l s='Terms of service'}</p>
			<p class="checkbox">
				<input type="checkbox" name="cgv" id="cgv" value="1" {if $checkedTOS}checked="checked"{/if} />
				<label for="cgv">{l s='I agree to the terms of service and will adhere to them unconditionally.'}</label>
				<a href="{$link_conditions|escape:'html':'UTF-8'}" class="iframe" rel="nofollow">{l s='(Read the Terms of Service)'}</a>
			</p>
		{/if}
	</div>
	
	<p class="cart_navigation clearfix">
		<button type="submit" name="processCarrier" class="button btn btn-default standard-checkout button-medium"> 
			<span>
				{lcw s='Confirm Order' mod='unzercw'}
				<i class="icon-chevron-right right"></i>
			</span>
		</button>
	</p>
	
	
	