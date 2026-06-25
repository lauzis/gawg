(function () {
	'use strict';

	function initForm( wrap ) {
		var form    = wrap.querySelector( '.gawg-form' );
		var success = wrap.querySelector( '.gawg-form-success' );
		var message = wrap.querySelector( '.gawg-form-message' );

		if ( ! form ) {
			return;
		}

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();

			var emailEl = form.querySelector( '[name="gawg_email"]' );
			var rulesEl = form.querySelector( '[name="gawg_rules"]' );
			var uuidEl  = form.querySelector( '[name="gawg_uuid"]' );
			var nonceEl = form.querySelector( '[name="_gawg_nonce"]' );
			var btn     = form.querySelector( '[type="submit"]' );

			clearMessage( message );

			if ( ! emailEl || '' === emailEl.value.trim() ) {
				showMessage( message, gawgFormConfig.i18n.invalidEmail, 'error' );
				return;
			}

			if ( rulesEl && ! rulesEl.checked ) {
				showMessage( message, gawgFormConfig.i18n.acceptRules, 'error' );
				return;
			}

			var data = new FormData();
			data.append( 'action',      gawgFormConfig.action );
			data.append( '_gawg_nonce', nonceEl ? nonceEl.value : '' );
			data.append( 'gawg_uuid',   uuidEl  ? uuidEl.value  : '' );
			data.append( 'gawg_email',  emailEl.value.trim() );

			btn.disabled = true;

			fetch( gawgFormConfig.ajaxUrl, { method: 'POST', body: data } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( response ) {
					if ( response.success ) {
						form.style.display    = 'none';
						success.style.display = '';
					} else {
						var msg = response.data && response.data.message
							? response.data.message
							: gawgFormConfig.i18n.networkError;
						showMessage( message, msg, 'error' );
						btn.disabled = false;
					}
				} )
				.catch( function () {
					showMessage( message, gawgFormConfig.i18n.networkError, 'error' );
					btn.disabled = false;
				} );
		} );
	}

	function showMessage( el, text, type ) {
		el.textContent = text;
		el.className   = 'gawg-form-message gawg-form-message--' + type;
	}

	function clearMessage( el ) {
		el.textContent = '';
		el.className   = 'gawg-form-message';
	}

	document.querySelectorAll( '.gawg-form-wrap' ).forEach( initForm );
} )();
