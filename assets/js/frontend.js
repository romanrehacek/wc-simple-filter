/**
 * WC Simple Filter — Frontend JavaScript
 *
 * Responsibilities (Phase 2a — display only):
 *  1. Sidebar: collapsible filter sections (toggle open/close).
 *  2. Horizontal: pill-button open/close of filter dropdown panels.
 *  3. "View more / View less" for checkbox and radio lists.
 *  4. jQuery UI Slider initialisation (display only).
 *  5. Active chips bar demo simulation (horizontal layout, demoChips flag).
 *
 * No form submission or URL manipulation in Phase 2a.
 *
 * @package WC_Simple_Filter
 */

/* global jQuery, WC_SF_Frontend */
( function ( $ ) {
	'use strict';

	// Config injected via wp_localize_script().
	var cfg = ( typeof WC_SF_Frontend !== 'undefined' ) ? WC_SF_Frontend : {};

	/* =========================================================================
	   1. Sidebar — collapsible sections
	   ========================================================================= */

	/**
	 * Toggle a collapsible filter body.
	 *
	 * @param {HTMLElement} btn   The .wcsf__filter-toggle button.
	 * @param {boolean}     [force] If supplied, true = open, false = close.
	 */
	function toggleSidebarFilter( btn, force ) {
		var expanded = btn.getAttribute( 'aria-expanded' ) === 'true';
		var open     = ( typeof force !== 'undefined' ) ? force : ! expanded;
		var bodyId   = btn.getAttribute( 'aria-controls' );
		var body     = document.getElementById( bodyId );
		var wrapper  = btn.closest( '.wcsf__filter' );

		if ( ! body || ! wrapper ) {
			return;
		}

		btn.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		body.setAttribute( 'aria-hidden', open ? 'false' : 'true' );

		if ( open ) {
			wrapper.classList.remove( 'wcsf__filter--collapsed' );
			body.style.display = '';
		} else {
			wrapper.classList.add( 'wcsf__filter--collapsed' );
			body.style.display = 'none';
		}
	}

	/**
	 * Initialise sidebar collapsible behaviour.
	 *
	 * @param {HTMLElement} root  .wcsf--sidebar element.
	 */
	function initSidebar( root ) {
		root.querySelectorAll( '.wcsf__filter-toggle' ).forEach( function ( btn ) {
			// Sync initial visual state with aria-expanded attribute.
			var isExpanded = btn.getAttribute( 'aria-expanded' ) === 'true';
			var bodyId     = btn.getAttribute( 'aria-controls' );
			var body       = document.getElementById( bodyId );

			if ( body && ! isExpanded ) {
				body.style.display = 'none';
			}

			btn.addEventListener( 'click', function () {
				toggleSidebarFilter( this );
			} );
		} );
	}

	/* =========================================================================
	   2. Horizontal — pill dropdown panels
	   ========================================================================= */

	/**
	 * Close all open filter panels inside a horizontal wrapper.
	 *
	 * @param {HTMLElement} root    .wcsf--horizontal element.
	 * @param {HTMLElement} [except] Optional pill to skip (keep open).
	 */
	function closeAllPanels( root, except ) {
		root.querySelectorAll( '.wcsf__toggle-pill[aria-expanded="true"]' ).forEach( function ( pill ) {
			if ( pill !== except ) {
				var filterId = pill.getAttribute( 'data-filter-id' );
				var panel    = root.querySelector( '.wcsf__filter[data-filter-id="' + filterId + '"]' );

				pill.setAttribute( 'aria-expanded', 'false' );
				if ( panel ) {
					panel.classList.remove( 'wcsf__filter--open' );
				}
			}
		} );
	}

	/**
	 * Initialise horizontal pill-toggle behaviour.
	 *
	 * @param {HTMLElement} root  .wcsf--horizontal element.
	 */
	function initHorizontal( root ) {
		root.querySelectorAll( '.wcsf__toggle-pill' ).forEach( function ( pill ) {
			pill.addEventListener( 'click', function ( e ) {
				// Stop propagation so the document handler does not also run
				// and accidentally close the panel we just opened.
				e.stopPropagation();

				var filterId = this.getAttribute( 'data-filter-id' );
				var panel    = root.querySelector( '.wcsf__filter[data-filter-id="' + filterId + '"]' );
				var isOpen   = this.getAttribute( 'aria-expanded' ) === 'true';

				// Close all others first.
				closeAllPanels( root, this );

				// Toggle this one.
				if ( isOpen ) {
					this.setAttribute( 'aria-expanded', 'false' );
					if ( panel ) {
						panel.classList.remove( 'wcsf__filter--open' );
					}
				} else {
					this.setAttribute( 'aria-expanded', 'true' );
					if ( panel ) {
						panel.classList.add( 'wcsf__filter--open' );

						// Position the panel under the clicked pill (desktop only).
						positionPanel( panel, this );
					}
				}
			} );
		} );

		// Close panels when clicking outside the root OR outside any open panel.
		// We must NOT close when the user clicks inside an open filter panel
		// (e.g. ticking a checkbox), even though that click bubbles to document.
		document.addEventListener( 'click', function ( e ) {
			// If the click target is inside ANY filter panel, don't close.
			if ( e.target.closest( '.wcsf__filter' ) ) {
				return;
			}
			// If the click is inside the root but outside all panels (e.g. on active-bar,
			// toggle-bar, reset btn), close all panels.
			if ( root.contains( e.target ) ) {
				closeAllPanels( root );
			} else {
				// Click outside root entirely.
				closeAllPanels( root );
			}
		} );

		// Close panels on Escape key.
		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' ) {
				closeAllPanels( root );
			}
		} );
	}

	/**
	 * Position a panel horizontally under its pill button.
	 * Panels are inside .wcsf__filters which sits right below .wcsf__toggle-bar.
	 * We just need to align the panel's left edge with the pill's left edge.
	 *
	 * @param {HTMLElement} panel  .wcsf__filter element.
	 * @param {HTMLElement} pill   .wcsf__toggle-pill button.
	 */
	function positionPanel( panel, pill ) {
		// Only apply on wider viewports (pills are visible).
		if ( window.innerWidth <= 600 ) {
			panel.style.left = '';
			return;
		}

		var filtersEl  = panel.parentElement;   // .wcsf__filters
		var wcsfRoot   = filtersEl ? filtersEl.parentElement : null; // .wcsf

		if ( ! filtersEl || ! wcsfRoot ) {
			return;
		}

		var pillRect   = pill.getBoundingClientRect();
		var rootRect   = wcsfRoot.getBoundingClientRect();
		var leftOffset = pillRect.left - rootRect.left;
		var panelWidth = panel.offsetWidth || 220;

		// Prevent overflow on the right edge.
		var maxLeft = wcsfRoot.offsetWidth - panelWidth - 4;
		panel.style.left = Math.max( 0, Math.min( leftOffset, maxLeft ) ) + 'px';
	}

	/* =========================================================================
	   3. View more / View less
	   ========================================================================= */

	/**
	 * Initialise "View more / View less" buttons.
	 *
	 * @param {HTMLElement} root  .wcsf element.
	 */
	function initViewMore( root ) {
		root.querySelectorAll( '.wcsf__view-more-link' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var list        = this.previousElementSibling; // ul.wcsf__checkbox-list or .wcsf__radio-list
				var isExpanded  = this.getAttribute( 'aria-expanded' ) === 'true';
				var visible     = parseInt( this.getAttribute( 'data-visible' ), 10 );
				var total       = parseInt( this.getAttribute( 'data-total' ), 10 );
				var hiddenClass = '';

				if ( ! list ) {
					return;
				}

				if ( list.classList.contains( 'wcsf__checkbox-list' ) ) {
					hiddenClass = 'wcsf__checkbox-item--hidden';
				} else if ( list.classList.contains( 'wcsf__radio-list' ) ) {
					hiddenClass = 'wcsf__radio-item--hidden';
				}

				if ( ! hiddenClass ) {
					return;
				}

				if ( isExpanded ) {
					// Collapse: hide items beyond visible count.
					list.querySelectorAll( 'li' ).forEach( function ( li, idx ) {
						if ( idx >= visible ) {
							li.classList.add( hiddenClass );
						}
					} );
					this.setAttribute( 'aria-expanded', 'false' );
					this.textContent = wcsfViewMoreLabel( total - visible );
				} else {
					// Expand: show all items.
					list.querySelectorAll( 'li' ).forEach( function ( li ) {
						li.classList.remove( hiddenClass );
					} );
					this.setAttribute( 'aria-expanded', 'true' );
					this.textContent = cfg.i18n ? cfg.i18n.viewLess : 'Zobraziť menej';
				}
			} );
		} );
	}

	/**
	 * Build localised "Zobraziť viac (N)" label.
	 *
	 * @param  {number} count
	 * @return {string}
	 */
	function wcsfViewMoreLabel( count ) {
		if ( cfg.i18n && cfg.i18n.viewMore ) {
			return cfg.i18n.viewMore + ' (' + count + ')';
		}
		return 'Zobraziť viac (' + count + ')';
	}

	/* =========================================================================
	   4. jQuery UI Slider (display only — Phase 2a)
	   ========================================================================= */

	/**
	 * Initialise all range sliders on the page.
	 *
	 * @param {HTMLElement} root  .wcsf element.
	 */
	function initSliders( root ) {
		if ( typeof $.fn.slider === 'undefined' ) {
			return;
		}

		root.querySelectorAll( '.wcsf__slider-wrapper' ).forEach( function ( wrapper ) {
			var sliderEl = wrapper.querySelector( '.wcsf__slider' );

			if ( ! sliderEl ) {
				return;
			}

			var min  = parseFloat( wrapper.getAttribute( 'data-min' ) ) || 0;
			var max  = parseFloat( wrapper.getAttribute( 'data-max' ) ) || 1000;
			var step = parseFloat( wrapper.getAttribute( 'data-step' ) ) || 1;

			var $minVal = $( wrapper ).find( '.wcsf__slider-value--min' );
			var $maxVal = $( wrapper ).find( '.wcsf__slider-value--max' );
			var $minIn  = $( wrapper ).find( '.wcsf__slider-input--min' );
			var $maxIn  = $( wrapper ).find( '.wcsf__slider-input--max' );

			$( sliderEl ).slider( {
				range:  true,
				min:    min,
				max:    max,
				step:   step,
				values: [ min, max ],
				// Phase 2a: display only — no slide callback needed.
				// Phase 2b: uncomment and wire up to filter query.
				slide: function ( event, ui ) {
					// Display values (read-only in Phase 2a).
					$minVal.text( ui.values[ 0 ] );
					$maxVal.text( ui.values[ 1 ] );
					$minIn.val( ui.values[ 0 ] );
					$maxIn.val( ui.values[ 1 ] );
				},
			} );
		} );
	}

	/* =========================================================================
	   5. Active chips bar — Phase 2a demo simulation
	   =========================================================================
	   In Phase 2b this will be driven by real URL/state.
	   For now: inject a few hardcoded demo chips so the layout can be reviewed.
	   Demo chips are only injected when the data-demo attribute is present on
	   the .wcsf element, or when WC_SF_Frontend.demoChips is truthy.
	   ========================================================================= */

	/**
	 * Build one chip element.
	 *
	 * @param  {string} label      Human-readable chip label.
	 * @param  {string} filterType Filter type slug.
	 * @param  {string} value      Value slug.
	 * @return {HTMLElement}
	 */
	function buildChip( label, filterType, value ) {
		var chip = document.createElement( 'span' );
		chip.className = 'wcsf__active-chip';
		chip.setAttribute( 'data-filter-type', filterType );
		chip.setAttribute( 'data-value', value );

		var labelEl = document.createElement( 'span' );
		labelEl.className = 'wcsf__active-chip-label';
		labelEl.textContent = label;

		var removeBtn = document.createElement( 'button' );
		removeBtn.type = 'button';
		removeBtn.className = 'wcsf__active-chip-remove';
		removeBtn.setAttribute( 'aria-label', ( cfg.i18n && cfg.i18n.removeFilter ? cfg.i18n.removeFilter : 'Odstrániť filter' ) + ': ' + label );
		removeBtn.innerHTML = '<span aria-hidden="true">&times;</span>';

		removeBtn.addEventListener( 'click', function ( e ) {
			e.stopPropagation();
			// Capture bar reference before removing chip from DOM.
			var bar       = chip.closest ? chip.closest( '.wcsf__active-bar' ) : null;
			var chipsArea = bar ? bar.querySelector( '.wcsf__active-bar-chips' ) : null;

			chip.parentNode && chip.parentNode.removeChild( chip );

			// After removing a chip, hide the bar if no chips left.
			if ( chipsArea && ! chipsArea.children.length ) {
				bar.classList.remove( 'wcsf__active-bar--visible' );
			}
		} );

		chip.appendChild( labelEl );
		chip.appendChild( removeBtn );
		return chip;
	}

	/**
	 * Inject demo chips into a horizontal layout's active bar.
	 * Only runs when WC_SF_Frontend.demoChips === true.
	 *
	 * @param {HTMLElement} root  .wcsf--horizontal element.
	 */
	function initDemoChips( root ) {
		if ( ! cfg.demoChips ) {
			return;
		}

		var bar       = root.querySelector( '.wcsf__active-bar' );
		var chipsArea = bar ? bar.querySelector( '.wcsf__active-bar-chips' ) : null;

		if ( ! bar || ! chipsArea ) {
			return;
		}

		var demos = [
			{ label: 'Značka: Nike',   filterType: 'brand',     value: 'nike'    },
			{ label: 'Farba: Červená', filterType: 'attribute', value: 'cervena' },
			{ label: 'Výpredaj',       filterType: 'sale',      value: 'sale'    },
		];

		demos.forEach( function ( demo ) {
			chipsArea.appendChild( buildChip( demo.label, demo.filterType, demo.value ) );
		} );

		bar.classList.add( 'wcsf__active-bar--visible' );
	}

	/* =========================================================================
	   Boot — wait for DOM ready
	   ========================================================================= */

	$( function () {
		var roots = document.querySelectorAll( '.wcsf' );

		if ( ! roots.length ) {
			return;
		}

		roots.forEach( function ( root ) {
			var layout = root.getAttribute( 'data-layout' );

			if ( layout === 'sidebar' ) {
				initSidebar( root );
			} else if ( layout === 'horizontal' ) {
				initHorizontal( root );
				initDemoChips( root );
			}

			initViewMore( root );
			initSliders( root );
		} );
	} );

}( jQuery ) );
