<?php
/**
 * Requests Trap
 * v2.01
 * 10.04.2023
 * Delta
 * sergey.it@delta-ltd.ru
 *
 * .htaccess
 * php_value auto_prepend_file /.../_buran/reqst-trap.php
 */

if (
	isset($_POST)
	&& is_array($_POST)
	&& count($_POST)
	// && ! strpos($_SERVER['REQUEST_URI'], '/kontakty/') === false
	&& ! in_array($_SERVER['REQUEST_URI'],array(
		'/kontakty/',
		'/bitrix/components/bitrix/iblock.vote/ajax.php',
		'/bitrix/templates/mamont/ajax/basket.php',
	))
) {
	$droot = dirname(__FILE__);
	$ip = $_SERVER['REMOTE_ADDR'];
	$ip_e = explode('.',$ip);
	$dir = '/reqst-trap-log/'.$ip_e[0].'/';
	if ( ! @file_exists($droot.$dir)) @mkdir($droot.$dir,0777,true);
	$fh = @fopen($droot.$dir.$ip,'ab');
	if ($fh) {
		@fwrite($fh, "\n".'= '.date('d.m.Y, H:i:s').' ='."\n");
		@fwrite($fh, "\t".'+ '.$_SERVER['REQUEST_URI']."\n");
		foreach ($_POST AS $k => $v) {
			@fwrite($fh, "\t| ". $k .' => ');
			@fwrite($fh, (is_array($v) ? print_r($v,1) : $v) ."\n");
		}
		if (isset($_FILES)) {
			foreach ($_FILES AS $k => $v) {
				@fwrite($fh, "\t| ". $k .' => [file]'."\n");
			}
		}
		@fclose($fh);
	}
	if (0) {
		function delta_buran_reqst_trap($res)
		{
			$droot = dirname(__FILE__);
			$ip = $_SERVER['REMOTE_ADDR'];
			$ip_e = explode('.',$ip);
			$dir = '/reqst-trap-log/'.$ip_e[0].'/';
			$fh = @fopen($droot.$dir.$ip,'ab');
			if ($fh) {
				@fwrite($fh, "\n".'========== '.date('d.m.Y, H:i:s').' ='."\n");
				$res_f = is_array($res) ? serialize($res) : $res;
				@fwrite($fh, "\t".'+ '.$res_f.'=========='."\n");
				@fclose($fh);
			}
			return false;
		}
		ob_start('delta_buran_reqst_trap');
	}
}
