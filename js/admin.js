/**
 * Just Spectacular Theme — admin helpers.
 *
 * 1. Quick-paste <style>/<script> buttons for meta box / theme options textareas.
 * 2. Stops Ctrl/Cmd+Z (and Shift variant) inside our textareas from being
 *    captured by the block editor's global undo shortcut, so the browser's
 *    native per-field undo stack handles it instead.
 */
( function () {
	'use strict';

	function insertAtCursor( textarea, before, after ) {
		textarea.focus();

		var start = textarea.selectionStart;
		var end = textarea.selectionEnd;
		var inserted = before + after;

		var usedExecCommand = false;
		try {
			usedExecCommand = document.execCommand && document.execCommand( 'insertText', false, inserted );
		} catch ( e ) {
			usedExecCommand = false;
		}

		if ( ! usedExecCommand ) {
			var value = textarea.value;
			textarea.value = value.slice( 0, start ) + inserted + value.slice( end );
		}

		var caretPos = start + before.length;
		textarea.selectionStart = textarea.selectionEnd = caretPos;
	}

	document.addEventListener( 'click', function ( event ) {
		var btn = event.target.closest( '.jst-quick-tag-btn' );
		if ( ! btn ) {
			return;
		}
		event.preventDefault();

		var targetId = btn.getAttribute( 'data-target' );
		var tag = btn.getAttribute( 'data-tag' );
		var textarea = document.getElementById( targetId );
		if ( ! textarea ) {
			return;
		}

		if ( 'style' === tag ) {
			insertAtCursor( textarea, '<style>\n', '\n</style>' );
		} else if ( 'script' === tag ) {
			insertAtCursor( textarea, '<script>\n', '\n</script>' );
		}
	} );

	document.addEventListener(
		'keydown',
		function ( event ) {
			var isUndoCombo = ( event.ctrlKey || event.metaKey ) && ( 'z' === event.key || 'Z' === event.key );
			if ( ! isUndoCombo ) {
				return;
			}

			var target = event.target;
			if ( target && target.classList && target.classList.contains( 'jst-metabox-field' ) ) {
				event.stopPropagation();
			}
		},
		true
	);
} )();
