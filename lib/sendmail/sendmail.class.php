<?php

ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

/*
 * Buran SendMail
 */

// namespace Buran\SendMail;


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require $_SERVER['DOCUMENT_ROOT']. '/_buran/lib/phpmailer/Exception.php';
require $_SERVER['DOCUMENT_ROOT']. '/_buran/lib/phpmailer/PHPMailer.php';
require $_SERVER['DOCUMENT_ROOT']. '/_buran/lib/phpmailer/SMTP.php';


//if ( ! define('INCLUDED')) die();

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

	public function send ($to, $subject, $message){

		require $_SERVER['DOCUMENT_ROOT']. '/_buran/lib/sendmail/config.php';

		// Настройки
		$mail = new PHPMailer(true);

		//$mail->SMTPDebug = 2;    
		$mail->isSMTP();
		$mail->CharSet = 'UTF-8'; 
		$mail->Host = $smtp_host; 
		$mail->SMTPAuth = true; 
		$mail->Username = $smtp_username; 
		$mail->Password = $smtp_password; 
		$mail->SMTPSecure ='ssl'; 
		$mail->Port = 465;
		$mail->setFrom($smtp_username, $from_name); 
		$mail->addAddress($to); // Email получателя

		
		// Письмо
		$mail->isHTML(true); 
		$mail->Subject = $subject; 
		$mail->Body = $message;

		// var_dump ($mail); exit;

		if(!$mail->send()) {
			// echo 'Mailer Error: ' . $mail->ErrorInfo;
			return false;
		} else {
		 	return true;
		}


	}
}
