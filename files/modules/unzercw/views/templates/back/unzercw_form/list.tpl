<table class="table " cellpadding="0" cellspacing="0" style="width: 100%; margin-bottom:10px;">
	<thead>
		<tr class="nodrag nodrop">
			<th>{lcw s='Item' mod='unzercw'}</th>
			<th class="center" width="70px">{lcw s='Action' mod='unzercw'}</th>
		</tr>
	</thead>
	<tbody>
		{foreach from=$forms item=form}
			<tr class=" row_hover">
				<td>{$form->getTitle()}</td>
				<td class="center">
					<a href="{$link->getAdminLink('AdminUnzerCwForm')|escape:'htmlall':'UTF-8'}&form={$form->getMachineName()}" title="{lcw s='View' mod='unzercw'}">
						<img src="../img/admin/details.gif" alt="{lcw s='View' mod='unzercw'}">
					</a>
				</td>
			</tr>
		{/foreach}
	</tbody>
	
	
</table>


<h2>{lcw s='Cron Job' mod='unzercw'}</h2>
<p>
	{lcw s='In some situations it is required to setup a cron job invoking the URL listed below to executed scheduled tasks.' mod='unzercw'}
</p>
<p>
	<strong>{lcw s='Cron Job URL' mod='unzercw'}:</strong> {$cronUrl}
</p>