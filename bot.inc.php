<?php

if (!defined('BOT_TOKEN') {
	throw new Exception('BOT_TOKEN not defined.');
}

define('BOT_API_URL', 'https://api.telegram.org/bot'.BOT_TOKEN.'/');


function exec_curl_request($handle) {
	$response = curl_exec($handle);

	if ($response === false) {
		$errno = curl_errno($handle);
		$error = curl_error($handle);
		throw new Exception("Curl returned error $errno: $error.");
		curl_close($handle);
		return false;
	}

	$http_code = intval(curl_getinfo($handle, CURLINFO_HTTP_CODE));
	curl_close($handle);

	if ($http_code >= 500) {
		// do not wat to DDOS server if something goes wrong
		sleep(10);
		return false;
	} else if ($http_code != 200) {
		$response = json_decode($response, true);
		throw new Exception("Request has failed with error {$response['error_code']}: {$response['description']}.");
		if ($http_code == 401) {
			throw new Exception('Invalid access token provided');
		}
		return false;
	} else {
		$response = json_decode($response, true);
		if (isset($response['description'])) {
			throw new Exception("Request was successfull: {$response['description']}.");
		}
		$response = $response['result'];
	}

	return $response;
}

function apiRequest($method, $parameters) {
	if (!is_string($method)) {
		throw new Exception("Method name must be a string.");
		return false;
	}

	if (!$parameters) {
		$parameters = array();
	} else if (!is_array($parameters)) {
		throw new Exception("Parameters must be an array.");
		return false;
	}

	foreach ($parameters as $key => &$val) {
		// encoding to JSON array parameters, for example reply_markup
		if (!is_numeric($val) && !is_string($val)) {
			$val = json_encode($val);
		}
	}
	$url = BOT_API_URL.$method.'?'.http_build_query($parameters);

	if (defined('BOT_LOG')) {
		$parameters["method"] = $method;
		$myfile = file_put_contents(BOT_LOG, "\n\n".BOT_NAME." - GET: ".json_encode($parameters, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL, FILE_APPEND);
	}

	$handle = curl_init($url);
	curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
	curl_setopt($handle, CURLOPT_TIMEOUT, 60);

	return exec_curl_request($handle);
}

function apiRequestJson($method, $parameters) {
	if (!is_string($method)) {
		throw new Exception("Method name must be a string.");
		return false;
	}

	if (!$parameters) {
		$parameters = array();
	} else if (!is_array($parameters)) {
		throw new Exception("Parameters must be an array.");
		return false;
	}

	$parameters["method"] = $method;
	if (defined('BOT_LOG')) $myfile = file_put_contents(BOT_LOG, "\n\n".BOT_NAME." - POST: ".json_encode($parameters, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL, FILE_APPEND);

	$handle = curl_init(BOT_API_URL);
	curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
	curl_setopt($handle, CURLOPT_TIMEOUT, 60);
	curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($parameters));
	curl_setopt($handle, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));

	return exec_curl_request($handle);
}

function apiRequestWebhook($method, $parameters) {
	if (!is_string($method)) {
		throw new Exception("Method name must be a string.");
		return false;
	}

	if (!$parameters) {
		$parameters = array();
	} else if (!is_array($parameters)) {
		throw new Exception("Parameters must be an array.");
		return false;
	}

	$parameters["method"] = $method;
	if (defined('BOT_LOG')) $myfile = file_put_contents(BOT_LOG, "\n\n".BOT_NAME." - WebHook: ".json_encode($parameters, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL, FILE_APPEND);

	header("Content-Type: application/json");
	echo json_encode($parameters);
	return true;
}


function processMessage($message) { // process incoming message
	if (isset($message['text'])) { // incoming text message
		$text = $message['text'];

		if (strpos($text, "/") === 0) {
			$command = explode(" ", $text);
			$command_bot = explode("@", $command[0]);
			if ($command_bot[1] == BOT_NAME || $command_bot[1] == "") $command[0] = $command_bot[0];
			$cmd = substr($command[0], 1);
			if (function_exists('process_cmd_'.$cmd)) {
				$f = 'process_cmd_'.$cmd;
				return $f($message);
			}
		} else if (isset($message['reply_to_message'])) {
			$cmd = preg_replace('/[^a-zA-Z]/', '', $message['reply_to_message']['text']);
			if (function_exists('process_replay_'.$cmd)) {
				$f = 'process_replay_'.$cmd;
				return $f($message);
			}
		}
	}
	$message_types = array(
		"text",
		"audio",
		"document",
		"game",
		"photo",
		"sticker",
		"video",
		"voice",
		"video_note",
		"contact",
		"location",
	);
	foreach($message_types as $mt) {
		if (isset($message[$mt]) && function_exists('process_'.$mt)) {
			$f = 'process_'.$mt;
			return $f($message);
		}
	}
	if (function_exists('process_default')) {
		$f = 'process_default';
		return $f($message);
	}
	return false;
}

function processInline($query) { // process incoming inline query
	if (function_exists('process_inline')) {
		$f = 'process_inline';
		return $f($query);
	}
}

function processCallback($query) { // process incoming callback
	if (isset($query['data'])) {
		$text = $query['data'];
		if (strpos($text, "/") === 0) {
			$command = explode(" ", $text);
			$cmd = substr($command[0], 1);
			if (function_exists('process_callback_'.$cmd)) {
				$f = 'process_callback_'.$cmd;
				return $f($query);
			}
		}
	}
	else if (isset($query['game_short_name'])) {
		$game = $query['game_short_name'];
		if (function_exists('process_game_'.$game)) {
			$f = 'process_game_'.$game;
			return $f($query);
		}
	}
}


if (php_sapi_name() == 'cli') {
	// if run from console, set or delete webhook
	switch(strToLower($argv[1])) {
	case 'set':
		apiRequest('setWebhook', array('url' => WEBHOOK_URL));
		break;
	case 'delete':
		apiRequest('deleteWebhook', array());
		break;
	case 'info':
	default:
		print_r(apiRequest('getWebhookInfo', array()));
	}
	exit;
}


$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update) exit; // receive wrong update, must not happen

if (defined('BOT_LOG')) $myfile = file_put_contents(BOT_LOG, "\n\n".BOT_NAME.": ".json_encode($update, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL , FILE_APPEND);


if (isset($update["message"])) {
	processMessage($update["message"]);
}
else if (isset($update["edited_message"])) {
	#processEditedMessage($update["edited_message"]);
}
else if (isset($update["channel_post"])) {
	#processChannelPost($update["channel_post"]);
}
else if (isset($update["edited_channel_post"])) {
	#processEditedChannelPost($update["edited_channel_post"]);
}
else if (isset($update["inline_query"])) {
	processInline($update["inline_query"]);
}
else if (isset($update["callback_query"])) {
	processCallback($update["callback_query"]);
}

?>
