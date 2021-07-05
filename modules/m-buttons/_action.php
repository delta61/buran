<?php

require $_SERVER['DOCUMENT_ROOT'] . '/_buran/modules/m-buttons/config.php';




if (isset($_REQUEST['showform'])) {
    $s = file_get_contents($_SERVER['DOCUMENT_ROOT']. '/_buran/lib/sendmail/form.html');

    echo $s;
}

if (isset($_REQUEST['submitform'])) {
    require_once ($_SERVER['DOCUMENT_ROOT']. '/_buran/lib/sendmail/sendmail.class.php');

    $sendmail = new SendMail;
    $res = $sendmail->send($mail_to, 'Заказ обратного звонка', "Имя: $_REQUEST[input1]<br>Телефон: $_REQUEST[input2]");

    if ($res) {
        echo "<h5>Ваш запрос успешно отправлен!</h5>";
    }

}


