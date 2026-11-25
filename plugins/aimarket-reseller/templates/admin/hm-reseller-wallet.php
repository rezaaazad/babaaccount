<?php
defined('ABSPATH') || exit;

(new \HooshMarket\Reseller\WordPress\AdminPage(\HooshMarket\Reseller\WordPress\Plugin::services()))->renderWallet();
