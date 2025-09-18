<?php
return [
  // When false, no legacy PIN strategies are used; only central manager validation applies
  'enable_legacy_pin_fallback' => false,
  // Log directory and file
  'log_dir' => __DIR__ . DIRECTORY_SEPARATOR . 'logs',
  'log_file' => 'auth.log',
  // Masking settings
  'mask_pin' => true,
];
