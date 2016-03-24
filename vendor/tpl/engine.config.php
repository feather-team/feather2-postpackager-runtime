<?php
$config = require(__DIR__ . '/engine.user.php');
$config['systemPlugins']['autoload_static'] = array(
	'domain' => isset($config['staticDomain']) ? $config['staticDomain'] : null,
	'combo' => array(
		'level' => #level#,
		'cssOnlySameBase' =>  #cssOnlySameBase#,
		'maxUrlLength' => #maxUrlLength#
	)
);

return $config;