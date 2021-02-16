<?php
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

		// Настройки
		$mail = new PHPMailer(true);

		//$mail->SMTPDebug = 2;    
		$mail->isSMTP();
		$mail->CharSet = 'UTF-8'; 
		$mail->Host = 'smtp.yandex.ru'; 
		$mail->SMTPAuth = true; 
		$mail->Username = 'prof.podarki@yandex.ru'; // Ваш логин в Яндексе. Именно логин, без @yandex.ru
		$mail->Password = '6qj51tXTW!Jnz3'; // Ваш пароль
		$mail->SMTPSecure ='ssl'; 
		$mail->Port = 465;
		$mail->setFrom('prof.podarki@yandex.ru'); // Ваш Email
		$mail->addAddress($to); // Email получателя

		
		// Письмо
		$mail->isHTML(true); 
		$mail->Subject = $subject; // Заголовок письма
		$mail->Body = $message; // Текст письма
		// Результат
		if(!$mail->send()) {
			// echo 'Mailer Error: ' . $mail->ErrorInfo;
			return false;
		} else {
		 	return true;
		}


	}
}
