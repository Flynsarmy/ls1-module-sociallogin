<?php if ( false ): ?><script><?php endif; ?>

if ( typeof(faga_tooltip) == 'undefined' )
	function faga_tooltip( message )
	{

		jQuery('#content').prepend(
			'<form id="hint_form" action="/admin/flynsarmygastats/settings" method="post" onsubmit="return false;">' +
				'<div class="hint minor_margin">' +
					"<p class='last'>" + message + "</p>" +
					'<a title="Hide this hint" href="#" class="close" onclick="return hide_tip(\'faga_settingsrequired\', this)">Close</a>' +
				'</div>' +
			'</form>'
		);
	}

jQuery(document).ready(function($) {
	faga_tooltip("<?= addslashes($tooltip) ?>");
});