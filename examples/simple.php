<?php

define('BOT_TOKEN', "123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11");
define('WEBHOOK_URL', "https://example.com/simple.php");

require_once('../bot.inc.php');

function process_default($message) {
	apiRequestWebhook("sendMessage", array('chat_id' => $chat_id, 'text' => "gerat!"));
}

?>
