
{extends file='page.tpl'}

{block name='page_content'}
   {capture name=path}{lcw s='Payment' mod='unzercw'}{/capture}

	<h1 class="page-heading">{lcw s='Payment' mod='unzercw'}</h1>

	<div class="unzercw-iframe">{$iframe nofilter}</div>
{/block}



