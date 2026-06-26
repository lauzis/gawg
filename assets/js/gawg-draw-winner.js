(function () {
	var giveawaySelect   = document.getElementById( 'gawg-giveaway-select' );
	var shuffleCountInput = document.getElementById( 'gawg-shuffle-count' );
	var shuffleDelayInput = document.getElementById( 'gawg-shuffle-delay' );
	var shuffleBtn        = document.getElementById( 'gawg-shuffle-btn' );
	var participantsWrap  = document.getElementById( 'gawg-participants-wrap' );
	var participantsList  = document.getElementById( 'gawg-participants-list' );
	var highlightedEl     = document.getElementById( 'gawg-highlighted-participant' );
	var winnerWrap        = document.getElementById( 'gawg-winner-wrap' );
	var winnerDisplay     = document.getElementById( 'gawg-winner-display' );
	var statusEl          = document.getElementById( 'gawg-status' );

	var participants = [];

	giveawaySelect.addEventListener( 'change', function () {
		var termId = giveawaySelect.value;

		participantsWrap.style.display = 'none';
		winnerWrap.style.display       = 'none';
		shuffleBtn.disabled            = true;
		participants                   = [];
		participantsList.innerHTML     = '';
		highlightedEl.textContent      = '';
		statusEl.textContent           = '';

		if ( ! termId ) {
			return;
		}

		statusEl.textContent = gawgDrawWinner.i18n.loading;

		var data = new FormData();
		data.append( 'action',  'gawg_load_participants' );
		data.append( 'nonce',   gawgDrawWinner.nonce );
		data.append( 'term_id', termId );

		fetch( gawgDrawWinner.ajaxUrl, { method: 'POST', body: data } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( response ) {
				statusEl.textContent = '';
				if ( ! response.success ) {
					statusEl.textContent = response.data || gawgDrawWinner.i18n.networkError;
					return;
				}
				participants = response.data;
				if ( ! participants.length ) {
					statusEl.textContent = gawgDrawWinner.i18n.noParticipants;
					return;
				}
				renderParticipantList();
				participantsWrap.style.display = '';
				shuffleBtn.disabled            = false;
			} )
			.catch( function () {
				statusEl.textContent = gawgDrawWinner.i18n.networkError;
			} );
	} );

	function renderParticipantList() {
		participantsList.innerHTML = '';
		participants.forEach( function ( p ) {
			var li = document.createElement( 'li' );
			li.textContent = p.email;
			li.dataset.id  = p.id;
			li.style.padding = '2px 4px';
			participantsList.appendChild( li );
		} );
	}

	function highlightParticipant( index ) {
		var items = participantsList.querySelectorAll( 'li' );
		items.forEach( function ( li ) { li.style.background = ''; } );
		if ( items[ index ] ) {
			items[ index ].style.background = '#fffbcc';
			items[ index ].scrollIntoView( { block: 'nearest' } );
		}
		highlightedEl.textContent = participants[ index ] ? participants[ index ].email : '';
	}

	shuffleBtn.addEventListener( 'click', function () {
		var termId = giveawaySelect.value;
		if ( ! termId ) {
			statusEl.textContent = gawgDrawWinner.i18n.selectGiveaway;
			return;
		}
		if ( ! participants.length ) {
			statusEl.textContent = gawgDrawWinner.i18n.noParticipants;
			return;
		}

		var shuffleCount = parseInt( shuffleCountInput.value, 10 ) || 10;
		var shuffleDelay = parseInt( shuffleDelayInput.value, 10 ) || 100;

		shuffleBtn.disabled            = true;
		winnerWrap.style.display       = 'none';
		statusEl.textContent           = gawgDrawWinner.i18n.shuffling;

		var step     = 0;
		var interval = setInterval( function () {
			var idx = Math.floor( Math.random() * participants.length );
			highlightParticipant( idx );
			step++;
			if ( step >= shuffleCount ) {
				clearInterval( interval );
				pickWinner( termId );
			}
		}, shuffleDelay );
	} );

	function pickWinner( termId ) {
		var data = new FormData();
		data.append( 'action',  'gawg_pick_winner' );
		data.append( 'nonce',   gawgDrawWinner.nonce );
		data.append( 'term_id', termId );

		fetch( gawgDrawWinner.ajaxUrl, { method: 'POST', body: data } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( response ) {
				statusEl.textContent = '';
				if ( ! response.success ) {
					statusEl.textContent = response.data || gawgDrawWinner.i18n.networkError;
					shuffleBtn.disabled  = false;
					return;
				}
				var winnerEmail          = response.data.winner_email;
				winnerDisplay.textContent = winnerEmail;
				winnerWrap.style.display  = '';
				highlightedEl.textContent = winnerEmail;
			} )
			.catch( function () {
				statusEl.textContent = gawgDrawWinner.i18n.networkError;
				shuffleBtn.disabled  = false;
			} );
	}
} )();
