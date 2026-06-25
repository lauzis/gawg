<?php
defined( 'ABSPATH' ) || exit;

class GAWG {

	public static function init() {
		GAWG_Giveaway::init();
		GAWG_Admin::init();
	}
}
