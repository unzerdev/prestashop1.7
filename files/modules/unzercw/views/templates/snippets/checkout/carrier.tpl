<div class="order_carrier_content unzercw-external-checkout-carrier-box box">
	{if !empty($shippingMethodSelectionError) }
		<div class="alert alert-danger">
			{$shippingMethodSelectionError}
		</div>
	{/if}
	{if isset($virtual_cart) && $virtual_cart}
		<p>{lcw s='No shipping method needed.' mod='unzercw'}</p>
		<input id="input_virtual_carrier" class="hidden" type="hidden" name="id_carrier" value="0" />
	{else}
		<div id="HOOK_BEFORECARRIER">
			{if isset($carriers) && isset($HOOK_BEFORECARRIER)}
				{$HOOK_BEFORECARRIER}
			{/if}
		</div>
		{if isset($isVirtualCart) && $isVirtualCart}
			<p class="alert alert-warning">{l s='No carrier is needed for this order.'}</p>
		{else}
			{if $recyclablePackAllowed}
				<div class="checkbox">
					<label for="recyclable">
						<input type="checkbox" name="recyclable" id="recyclable" value="1" {if $recyclable == 1}checked="checked"{/if} />
						{l s='I would like to receive my order in recycled packaging.'}.
					</label>
				</div>
			{/if}
			<div class="delivery_options_address">
				{if isset($delivery_option_list)}
					{foreach $delivery_option_list as $id_address => $option_list}
						<p class="carrier_title">
							{if isset($address_collection[$id_address])}
								{l s='Choose a shipping option for this address:'} {$address_collection[$id_address]->alias}
							{else}
								{l s='Choose a shipping option'}
							{/if}
						</p>
						<div class="delivery_options">
							{foreach $option_list as $key => $option}
								<div class="delivery_option {if ($option@index % 2)}alternate_{/if}item">
									<div>
										<table class="resume table table-bordered{if !$option.unique_carrier} not-displayable{/if}">
											<tr>
												<td class="delivery_option_radio">
													<input id="delivery_option_{$id_address|intval}_{$option@index}" class="delivery_option_radio" type="radio" name="delivery_option[{$id_address|intval}]" data-key="{$key}" data-id_address="{$id_address|intval}" value="{$key}"{if isset($delivery_option[$id_address]) && $delivery_option[$id_address] == $key} checked="checked"{/if} />
												</td>
												<td class="delivery_option_logo">
													{foreach $option.carrier_list as $carrier}
														{if $carrier.logo}
															<img src="{$carrier.logo|escape:'htmlall':'UTF-8'}" alt="{$carrier.instance->name|escape:'htmlall':'UTF-8'}"/>
														{else if !$option.unique_carrier}
															{$carrier.instance->name|escape:'htmlall':'UTF-8'}
															{if !$carrier@last} - {/if}
														{/if}
													{/foreach}
												</td>
												<td>
													{if $option.unique_carrier}
														{foreach $option.carrier_list as $carrier}
															<strong>{$carrier.instance->name|escape:'htmlall':'UTF-8'}</strong>
														{/foreach}
														{if isset($carrier.instance->delay[$cookie->id_lang])}
															{$carrier.instance->delay[$cookie->id_lang]|escape:'htmlall':'UTF-8'}
														{/if}
													{/if}
													{if count($option_list) > 1}
														{if $option.is_best_grade}
															{if $option.is_best_price}
																{l s='The best price and speed'}
															{else}
																{l s='The fastest'}
															{/if}
														{else}
															{if $option.is_best_price}
																{l s='The best price'}
															{/if}
														{/if}
													{/if}
												</td>
												<td class="delivery_option_price">
													<div class="delivery_option_price">
														{if $option.total_price_with_tax && !$option.is_free && (!isset($free_shipping) || (isset($free_shipping) && !$free_shipping))}
															{if $use_taxes == 1}
																{if $priceDisplay == 1}
																	{convertPrice price=$option.total_price_without_tax}{if $display_tax_label} {l s='(tax excl.)'}{/if}
																{else}
																	{convertPrice price=$option.total_price_with_tax}{if $display_tax_label} {l s='(tax incl.)'}{/if}
																{/if}
															{else}
																{convertPrice price=$option.total_price_without_tax}
															{/if}
														{else}
															{l s='Free'}
														{/if}
													</div>
												</td>
											</tr>
										</table>
										{if !$option.unique_carrier}
											<table class="delivery_option_carrier{if isset($delivery_option[$id_address]) && $delivery_option[$id_address] == $key} selected{/if} resume table table-bordered{if $option.unique_carrier} not-displayable{/if}">
												<tr>
													{if !$option.unique_carrier}
														<td rowspan="{$option.carrier_list|@count}" class="delivery_option_radio first_item">
															<input id="delivery_option_{$id_address|intval}_{$option@index}" class="delivery_option_radio" type="radio" name="delivery_option[{$id_address|intval}]" data-key="{$key}" data-id_address="{$id_address|intval}" value="{$key}"{if isset($delivery_option[$id_address]) && $delivery_option[$id_address] == $key} checked="checked"{/if} />
														</td>
													{/if}
													{assign var="first" value=current($option.carrier_list)}
													<td class="delivery_option_logo{if $first.product_list[0].carrier_list[0] eq 0} not-displayable{/if}">
														{if $first.logo}
															<img src="{$first.logo|escape:'htmlall':'UTF-8'}" alt="{$first.instance->name|escape:'htmlall':'UTF-8'}"/>
														{else if !$option.unique_carrier}
															{$first.instance->name|escape:'htmlall':'UTF-8'}
														{/if}
													</td>
													<td class="{if $option.unique_carrier}first_item{/if}{if $first.product_list[0].carrier_list[0] eq 0} not-displayable{/if}">
														<input type="hidden" value="{$first.instance->id|intval}" name="id_carrier" />
														{if isset($first.instance->delay[$cookie->id_lang])}
															<i class="icon-info-sign"></i>{$first.instance->delay[$cookie->id_lang]|escape:'htmlall':'UTF-8'}
															{if count($first.product_list) <= 1}
																({l s='Product concerned:'}
															{else}
																({l s='Products concerned:'}
															{/if}
															{foreach $first.product_list as $product}
																{if $product@index == 4}
																	<acronym title="
																{/if}
																{strip}
																	{if $product@index >= 4}
																		{$product.name|escape:'htmlall':'UTF-8'}
																		{if isset($product.attributes) && $product.attributes}
																			{$product.attributes|escape:'htmlall':'UTF-8'}
																		{/if}
																		{if !$product@last}
																			,&nbsp;
																		{else}
																			">&hellip;</acronym>)
																		{/if}
																	{else}
																		{$product.name|escape:'htmlall':'UTF-8'}
																		{if isset($product.attributes) && $product.attributes}
																			{$product.attributes|escape:'htmlall':'UTF-8'}
																		{/if}
																		{if !$product@last}
																			,&nbsp;
																		{else}
																			)
																		{/if}
																	{/if}
																{strip}
															{/foreach}
														{/if}
													</td>
													<td rowspan="{$option.carrier_list|@count}" class="delivery_option_price">
														<div class="delivery_option_price">
															{if $option.total_price_with_tax && !$option.is_free && (!isset($free_shipping) || (isset($free_shipping) && !$free_shipping))}
																{if $use_taxes == 1}
																	{if $priceDisplay == 1}
																		{convertPrice price=$option.total_price_without_tax}{if $display_tax_label} {l s='(tax excl.)'}{/if}
																	{else}
																		{convertPrice price=$option.total_price_with_tax}{if $display_tax_label} {l s='(tax incl.)'}{/if}
																	{/if}
																{else}
																	{convertPrice price=$option.total_price_without_tax}
																{/if}
															{else}
																{l s='Free'}
															{/if}
														</div>
													</td>
												</tr>
												<tr>
													<td class="delivery_option_logo{if $carrier.product_list[0].carrier_list[0] eq 0} not-displayable{/if}">
														{foreach $option.carrier_list as $carrier}
															{if $carrier@iteration != 1}
																{if $carrier.logo}
																	<img src="{$carrier.logo|escape:'htmlall':'UTF-8'}" alt="{$carrier.instance->name|escape:'htmlall':'UTF-8'}"/>
																{else if !$option.unique_carrier}
																	{$carrier.instance->name|escape:'htmlall':'UTF-8'}
																{/if}
															{/if}
														{/foreach}
													</td>
													<td class="{if $option.unique_carrier} first_item{/if}{if $carrier.product_list[0].carrier_list[0] eq 0} not-displayable{/if}">
														<input type="hidden" value="{$first.instance->id|intval}" name="id_carrier" />
														{if isset($carrier.instance->delay[$cookie->id_lang])}
															<i class="icon-info-sign"></i>
															{$first.instance->delay[$cookie->id_lang]|escape:'htmlall':'UTF-8'}
															{if count($carrier.product_list) <= 1}
																({l s='Product concerned:'}
															{else}
																({l s='Products concerned:'}
															{/if}
															{foreach $carrier.product_list as $product}
																{if $product@index == 4}
																	<acronym title="
																{/if}
																{strip}
																	{if $product@index >= 4}
																		{$product.name|escape:'htmlall':'UTF-8'}
																		{if isset($product.attributes) && $product.attributes}
																			{$product.attributes|escape:'htmlall':'UTF-8'}
																		{/if}
																		{if !$product@last}
																			,&nbsp;
																		{else}
																			">&hellip;</acronym>)
																		{/if}
																	{else}
																		{$product.name|escape:'htmlall':'UTF-8'}
																		{if isset($product.attributes) && $product.attributes}
																			{$product.attributes|escape:'htmlall':'UTF-8'}
																		{/if}
																		{if !$product@last}
																			,&nbsp;
																		{else}
																			)
																		{/if}
																	{/if}
																{strip}
															{/foreach}
														{/if}
													</td>
												</tr>
											</table>
										{/if}
									</div>
								</div> <!-- end delivery_option -->
							{/foreach}
						</div> <!-- end delivery_options -->
						<div class="hook_extracarrier" id="HOOK_EXTRACARRIER_{$id_address}">
							{if isset($HOOK_EXTRACARRIER_ADDR) &&  isset($HOOK_EXTRACARRIER_ADDR.$id_address)}{$HOOK_EXTRACARRIER_ADDR.$id_address}{/if}
						</div>
					{foreachelse}
						<p class="alert alert-warning" id="noCarrierWarning">
							{foreach $cart->getDeliveryAddressesWithoutCarriers(true) as $address}
								{if empty($address->alias)}
									{l s='No carriers available.'}
								{else}
									{l s='No carriers available for the address "%s".' sprintf=$address->alias}
								{/if}
								{if !$address@last}
									<br />
								{/if}
							{foreachelse}
								{l s='No carriers available.'}
							{/foreach}
						</p>
					{/foreach}
				{/if}
			</div> <!-- end delivery_options_address -->
			<div id="extra_carrier" style="display: none;"></div>
			{if $giftAllowed}
				<p class="carrier_title">{l s='Gift'}</p>
				<p class="checkbox gift">
					<input type="checkbox" name="gift" id="gift" value="1" {if $cart->gift == 1}checked="checked"{/if} />
					<label for="gift">
						{l s='I would like my order to be gift wrapped.'}
						{if $gift_wrapping_price > 0}
							&nbsp;<i>({l s='Additional cost of'}
							<span class="price" id="gift-price">
								{if $priceDisplay == 1}
									{convertPrice price=$total_wrapping_tax_exc_cost}
								{else}
									{convertPrice price=$total_wrapping_cost}
								{/if}
							</span>
							{if $use_taxes && $display_tax_label}
								{if $priceDisplay == 1}
									{l s='(tax excl.)'}
								{else}
									{l s='(tax incl.)'}
								{/if}
							{/if})
							</i>
						{/if}
					</label>
				</p>
				<p id="gift_div">
					<label for="gift_message">{l s='If you\'d like, you can add a note to the gift:'}</label>
					<textarea rows="2" cols="120" id="gift_message" class="form-control" name="gift_message">{$cart->gift_message|escape:'html':'UTF-8'}</textarea>
				</p>
			{/if}
		{/if}
	{/if}
</div>
<p class="cart_navigation clearfix">
	{if isset($virtual_cart) && $virtual_cart || (isset($delivery_option_list) && !empty($delivery_option_list))}
		<button type="submit" name="processCarrier" id="shipping-method-submit-button" class="button btn btn-default standard-checkout button-medium">
			<span>
				{lcw s='Save Shipping Method' mod='unzercw'}
				<i class="icon-chevron-right right"></i>
			</span>
		</button>
	{/if}
</p>
{literal}
<script type="text/javascript">
<!-- 
var unzercwJqueryLoaded = function() {
    jQuery('#shipping-method-submit-button').hide();
    jQuery('.unzercw-external-checkout-carrier-box').each(function() {
            var box = $(this);
            box.find("input[type='radio']").change(function() {
                    jQuery('#shipping-method-submit-button').click();
            });
    });
}
if (document.readyState == "complete") { unzercwJqueryLoaded(); }
else if (window.addEventListener) { window.addEventListener("load", unzercwJqueryLoaded, false); }
else if (window.attachEvent) { window.attachEvent("onload", unzercwJqueryLoaded); }
else { window.onload = unzercwJqueryLoaded; }
-->
</script>
{/literal}