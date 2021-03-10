{if !empty($errorMessage) }
	<div class="alert alert-danger">
		{$errorMessage}
	</div>
{/if}
	
<div class="message-block-wrapper">
	<div id="ordermsg" class="form-group">
		<label>{l s='If you would like to add a comment about your order, please write it in the field below.'}</label>
		<textarea class="form-control" cols="60" rows="6" name="customerMessage">{$customerMessage}</textarea>
	</div>
</div>