
<h2>{lcw s='Refund Transaction' mod='unzercw'}</h2>

<p>{lcw s='You are along the way to refund the order %s.' mod='unzercw' sprintf=$orderId} 
{lcw s='Do you want to send this order also the?' mod='unzercw'}</p>

<p>{lcw s='Amount to refund:' mod='unzercw'} {$refundAmount} {$transaction->getCurrencyCode()}</p>

{if !$transaction->isRefundClosable()}
	<p><strong>{lcw s='This is the last refund possible on this transaction. This payment method does not support any further refunds.' mod='unzercw'}</strong></p>
{/if}

<form action="{$targetUrl}" method="POST">
<p>
	{$hiddenFields}	
	<a class="button" href="{$backUrl}">{lcw s='Cancel' mod='unzercw'}</a>
	<input type="submit" class="button" name="submitUnzerCwRefundNormal" value="{lcw s='No' mod='unzercw'}" />
	<input type="submit" class="button" name="submitUnzerCwRefundAuto" value="{lcw s='Yes' mod='unzercw'}" />
</p>
</form>