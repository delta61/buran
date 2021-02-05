<?php
/*
 * Buran SendMail
 */

namespace Buran\SendMail;

if ( ! definde('INCLUDED')) die();

// ----------------------------------------------------------

class SendMail
{
	private $formid;

	// ----------------------------------------------------

	public function __construct($formid='')
	{
		$formid = preg_replace("/[^a-z0-9\-]/",'',$formid);
		$this->formid = $formid;
	}
}
