{if isset($paymentMethodMessage) && !empty($paymentMethodMessage)}
	<p class="payment-method-message">{$paymentMethodMessage}</p>
{/if}

{if isset($paymentInformation) && !empty($paymentInformation)}
	<div class="unzercw-invoice-payment-information unzercw-payment-return-table" id="unzercw-invoice-payment-information">
		<h4>{$paymentInformationTitle}</h4>
		{$paymentInformation nofilter}
	</div>
{/if}