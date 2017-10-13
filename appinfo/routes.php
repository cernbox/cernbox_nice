<?php

return [
    'routes' => [
	   ['name' => 'page#create_home_dir', 'url' => '/createhomedir/{username}', 'verb' => 'POST'],
		['name' => 'page#check_home_dir', 'url' => '/checkhomedir/{username}', 'verb' => 'GET'],
    ]
];
