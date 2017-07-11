<?
	$template = System_EmailTemplate::create();
	$template->is_system = false;
	$template->subject = "Confirm attachment of new login provider";
	$template->code = "flynsarmysociallogin_associate_provider";
	$template->description = "An email confirmation sent when a customer associates a login provider such as Twitter with their existing LemonStand account.";
	$template->content = '<p>
		Hey {customer_first_name}, to start logging in with {flynsarmysociallogin_provider_name},
		please confirm your email address by clicking the link below:<br/>
		<a href="{flynsarmysociallogin_associate_url}">{flynsarmysociallogin_associate_url}</a>
	</p>';
	$template->save();
?>