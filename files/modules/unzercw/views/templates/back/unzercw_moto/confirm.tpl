<h2>{lcw s='Mail Order / Telephone Order %s' mod='unzercw' sprintf=$paymentMethodName}</h2>

{if isset($error_message) && !empty($error_message)}
	<p class="payment-error error">
		{$error_message->getBackendMessage()}
	</p>
{/if}



{if $isMotoSupported}
	
	<form action="{$form_target_url}" method="post" class="form-horizontal unzercw-payment-form">
	
		{$hidden_fields}
		
		{if isset($visible_fields) && !empty($visible_fields)}
			<p>{lcw s='You are along the way to create a new order.' mod='unzercw'} 
			{lcw s='With the following form you can debit the customer:' mod='unzercw'}</p>
			<fieldset>
				{$visible_fields}
			</fieldset>
		{else}
			<p>{lcw s='You are along the way to create a new order.' mod='unzercw'}</p>
		{/if}

		<p>
			<input type="submit" class="button btn btn-default" name="submitUnzerCwDebit" value="{lcw s='Debit the Customer' mod='unzercw'}" />
		</p>
	
	</form>
{else}
	<p>{lcw s='The payment method %s does not support mail order / telephone order.' mod='unzercw' sprintf=$paymentMethodName}</p>
{/if}


<p>
	<form action="{$normalFinishUrl}" method="POST">
		{$normalFinishHiddenFields}	
		<input type="submit" class="button btn btn-default" name="submitUnzerCwNormal" value="{lcw s='Continue without debit the customer' mod='unzercw'}" />
	</form>
</p>
