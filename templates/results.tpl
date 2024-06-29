<p>
	Simple XML completed. Log displayed below.
</p>
<table class="log_table">
	{foreach from=$content item=$row}
		<tr>
			{foreach from=$row item=$col}
				<td>{$col|escape}</td>
			{/foreach}
		</tr>
	{/foreach}
</table>
<style>
.log_table td { white-space: pre }
</style>