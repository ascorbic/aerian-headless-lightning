<?php

# Docksal DB connection settings.
$databases['default']['default'] = array (
  'database' => 'default',
  'username' => 'user',
  'password' => 'user',
  'host' => 'db',
  'driver' => 'mysql',
);

# Workaround for permission issues with NFS shares in Vagrant.
$settings['file_chmod_directory'] = 0777;
$settings['file_chmod_file'] = 0666;

$settings['hash_salt'] = 'AVKNvbnN2FnrCTRJCJ3cEaEWvx7y2uWpfxUdF';

// Local dev caching
$settings['cache']['bins']['render'] = 'cache.backend.null';
$settings['cache']['bins']['dynamic_page_cache'] = 'cache.backend.null';
$settings['cache']['bins']['page'] = 'cache.backend.null';
$settings['container_yamls'][] = DRUPAL_ROOT . '/sites/development.services.yml';
