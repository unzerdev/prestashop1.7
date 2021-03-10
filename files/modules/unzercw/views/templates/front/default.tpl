{extends "$layout"}
{block name="content"}
<section id='main' class='page-content card'>
	<div class='card-block'>
    	{capture name=path}{$content_title}{/capture}

    	<h2>{$content_title}</h2>

    	{$main_content}
	</div>
</section>
{/block}