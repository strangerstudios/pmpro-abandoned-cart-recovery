/**
 * Resume Checkout Bar — front-end controller.
 *
 * Renders one of three views into #pmproacr-resume-root based on session state:
 *   - Popup (first non-checkout page after a saved cart appears)
 *   - Sticky bottom bar (after popup is dismissed, or popup already shown this session)
 *   - Dismiss confirmation modal (when the bar's close button is clicked)
 */
( function () {
	'use strict';

	if ( typeof window.pmproacrResume === 'undefined' ) {
		return;
	}

	var cfg          = window.pmproacrResume;
	var POPUP_FLAG   = 'pmproacrResumePopupShown';
	var DISMISS_FLAG = 'pmproacrResumeDismissed:' + ( cfg.cartToken || 'global' );

	function isDismissedForThisCart() {
		try {
			return window.localStorage.getItem( DISMISS_FLAG ) === '1';
		} catch ( err ) {
			return document.cookie.indexOf( 'pmproacr_resume_dismissed=' + ( cfg.cartToken || '' ) ) !== -1;
		}
	}

	function markDismissedForThisCart() {
		try {
			window.localStorage.setItem( DISMISS_FLAG, '1' );
		} catch ( err ) {
			document.cookie = 'pmproacr_resume_dismissed=' + ( cfg.cartToken || '' ) + ';path=/;max-age=' + ( 60 * 60 * 24 * 30 );
		}
	}

	function popupShownThisSession() {
		try {
			return window.sessionStorage.getItem( POPUP_FLAG ) === '1';
		} catch ( err ) {
			return false;
		}
	}

	function markPopupShown() {
		try {
			window.sessionStorage.setItem( POPUP_FLAG, '1' );
		} catch ( err ) { /* swallow */ }
	}

	function createEl( tag, attrs, children ) {
		var el = document.createElement( tag );
		if ( attrs ) {
			Object.keys( attrs ).forEach( function ( key ) {
				if ( key === 'className' ) {
					el.className = attrs[ key ];
				} else if ( key === 'html' ) {
					el.innerHTML = attrs[ key ];
				} else {
					el.setAttribute( key, attrs[ key ] );
				}
			} );
		}
		( children || [] ).forEach( function ( child ) {
			if ( typeof child === 'string' ) {
				el.appendChild( document.createTextNode( child ) );
			} else if ( child ) {
				el.appendChild( child );
			}
		} );
		return el;
	}

	function renderBar( root ) {
		root.innerHTML = '';
		var bar = createEl( 'div', { className: 'pmproacr-resume-bar', role: 'region', 'aria-label': cfg.barText } );

		var label = createEl( 'span', { className: 'pmproacr-resume-bar__text' }, [ cfg.barText ] );

		var resume = createEl( 'a', {
			className: 'pmproacr-resume-bar__button',
			href: cfg.resumeUrl
		}, [ cfg.barButtonLabel ] );

		var close = createEl( 'button', {
			type: 'button',
			className: 'pmproacr-resume-bar__close',
			'aria-label': cfg.closeLabel
		} );
		close.innerHTML = '&times;';
		close.addEventListener( 'click', function () {
			renderConfirm( root );
		} );

		bar.appendChild( label );
		bar.appendChild( resume );
		bar.appendChild( close );
		root.appendChild( bar );

		// Add a body class so themes can pad their footer if needed.
		document.body.classList.add( 'has-pmproacr-resume-bar' );
	}

	function renderPopup( root ) {
		root.innerHTML = '';

		var overlay = createEl( 'div', { className: 'pmproacr-resume-overlay', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'pmproacr-resume-popup-headline' } );

		var modal = createEl( 'div', { className: 'pmproacr-resume-modal' } );

		var headline = createEl( 'h2', {
			id: 'pmproacr-resume-popup-headline',
			className: 'pmproacr-resume-modal__headline'
		}, [ cfg.popupHeadline ] );

		var body = createEl( 'p', { className: 'pmproacr-resume-modal__body' }, [ cfg.popupBody ] );

		var actions = createEl( 'div', { className: 'pmproacr-resume-modal__actions' } );

		var gotIt = createEl( 'button', {
			type: 'button',
			className: 'pmproacr-resume-modal__primary'
		}, [ cfg.gotItLabel ] );
		gotIt.addEventListener( 'click', function () {
			markPopupShown();
			renderBar( root );
		} );

		var dismiss = createEl( 'button', {
			type: 'button',
			className: 'pmproacr-resume-modal__secondary'
		}, [ cfg.dismissLabel ] );
		dismiss.addEventListener( 'click', function () {
			markPopupShown();
			renderConfirm( root );
		} );

		actions.appendChild( gotIt );
		actions.appendChild( dismiss );

		modal.appendChild( headline );
		modal.appendChild( body );
		modal.appendChild( actions );
		overlay.appendChild( modal );
		root.appendChild( overlay );

		// Clicking outside the modal also moves to the bar (treat as "got it").
		overlay.addEventListener( 'click', function ( event ) {
			if ( event.target === overlay ) {
				markPopupShown();
				renderBar( root );
			}
		} );

		// Esc key = same as clicking outside.
		document.addEventListener( 'keydown', function escHandler( event ) {
			if ( event.key === 'Escape' ) {
				document.removeEventListener( 'keydown', escHandler );
				markPopupShown();
				renderBar( root );
			}
		} );
	}

	function renderConfirm( root ) {
		root.innerHTML = '';

		var overlay = createEl( 'div', { className: 'pmproacr-resume-overlay', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'pmproacr-resume-confirm-headline' } );

		var modal = createEl( 'div', { className: 'pmproacr-resume-modal' } );

		var headline = createEl( 'h2', {
			id: 'pmproacr-resume-confirm-headline',
			className: 'pmproacr-resume-modal__headline'
		}, [ cfg.confirmHeadline ] );

		var body = createEl( 'p', { className: 'pmproacr-resume-modal__body', html: cfg.confirmBody } );

		var actions = createEl( 'div', { className: 'pmproacr-resume-modal__actions' } );

		var cancel = createEl( 'button', {
			type: 'button',
			className: 'pmproacr-resume-modal__secondary'
		}, [ cfg.confirmCancel ] );
		cancel.addEventListener( 'click', function () {
			renderBar( root );
		} );

		var confirm = createEl( 'button', {
			type: 'button',
			className: 'pmproacr-resume-modal__primary pmproacr-resume-modal__primary--danger'
		}, [ cfg.confirmConfirm ] );
		confirm.addEventListener( 'click', function () {
			markDismissedForThisCart();
			root.innerHTML = '';
			document.body.classList.remove( 'has-pmproacr-resume-bar' );
		} );

		actions.appendChild( cancel );
		actions.appendChild( confirm );

		modal.appendChild( headline );
		modal.appendChild( body );
		modal.appendChild( actions );
		overlay.appendChild( modal );
		root.appendChild( overlay );

		overlay.addEventListener( 'click', function ( event ) {
			if ( event.target === overlay ) {
				renderBar( root );
			}
		} );
	}

	function init() {
		var root = document.getElementById( 'pmproacr-resume-root' );
		if ( ! root || ! cfg.resumeUrl ) {
			return;
		}

		if ( isDismissedForThisCart() ) {
			return;
		}

		if ( popupShownThisSession() ) {
			renderBar( root );
		} else {
			renderPopup( root );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
