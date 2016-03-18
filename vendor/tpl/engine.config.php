<?php
$config = require(__DIR__ . '/engine.user.php');
$config['systemPlugins']['autoload_static'] = array(
	'domain' => isset($config['staticDomain']) ? $config['staticDomain'] : null,
	'combo' => #combo#,
	'comboCssOnlySameBase' => #comboCssOnlySameBase#
);

return $config;