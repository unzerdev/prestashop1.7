<div class="unzercw-alias-pane unzercw-alias-form" data-ajax-action="{$aliasUrl}">
	{if isset($aliasTransactions) && count($aliasTransactions) > 0 && isset($selectedAlias) && !empty($selectedAlias) && $selectedAlias != 'new'}
		<div class="form-group">
			<label for="unzercw_alias" class="control-label col-sm-4">{lcw s='Use stored Card' mod='unzercw'}</label>
			<div class="col-sm-8">
				<select name="unzercw_alias" id="unzercw_alias" class="form-control">
					{foreach item=transaction from=$aliasTransactions}
						<option 
						{if isset($selectedAlias) && $selectedAlias == $transaction->getTransactionId()}
							selected="selected" 
						{/if}
						value="{$transaction->getTransactionId()}">{$transaction->getAliasForDisplay()}</option>
					{/foreach}
				</select>
			</div>
		</div>
	{/if}
	
	{if !isset($selectedAlias) || empty($selectedAlias) || $selectedAlias == 'new'}
		<div class="form-group">
			<div class="">
				<div class="checkbox">
					<label>
						<input type="hidden" name="unzercw_create_new_alias_present" value="active" />
						<input type="checkbox" name="unzercw_create_new_alias" value="on"
						{if $selectedAlias == 'new'} checked="checked" {/if}
						 /> {lcw s='Store card information' mod='unzercw'}
					</label>
				</div>
			</div>
		</div>
	{/if}
	
	<div class="form-group">
		
		{if isset($selectedAlias) && !empty($selectedAlias) && $selectedAlias != 'new'}
			<input type="submit" name="unzercw_alias_use_new_card" class="btn btn-default" value="{lcw s='Use new card' mod='unzercw'}" />
		{elseif isset($aliasTransactions) && count($aliasTransactions) > 0 && (!isset($selectedAlias) || empty($selectedAlias) || $selectedAlias == 'new')}
			<input type="submit" name="unzercw_alias_use_stored_card" class="btn btn-default" value="{lcw s='Use stored card' mod='unzercw'}" />
		{/if}
	</div>
</div>