<?php
defined( 'ABSPATH' ) || exit;

class GAWG {

	public static function init() {
		GAWG_Participant::init();
		GAWG_Giveaway::init();
		GAWG_Admin::init();
		GAWG_Form::init();
	}
}
