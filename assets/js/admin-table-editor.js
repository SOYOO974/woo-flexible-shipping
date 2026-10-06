jQuery( document ).ready( function( $ ) {
	'use strict';

	function initTableEditors() {
		$( '.wfs-table-editor-container' ).each( function() {
			var $container = $( this );
			if ( $container.data( 'wfs-initialized' ) ) {
				return;
			}
			$container.data( 'wfs-initialized', true );

			var fieldId   = $container.attr( 'data-field-id' );
			var $hidden   = $( '#' + $.escapeSelector( fieldId ) );
			var $tbody    = $container.find( '.wfs-rules-tbody' );
			var $addBtn   = $container.find( '.wfs-add-rule-btn' );

			var rawValue  = $hidden.val() || '[]';
			var rules     = [];

			try {
				rules = JSON.parse( rawValue );
			} catch ( e ) {
				rules = [];
			}

			if ( ! Array.isArray( rules ) ) {
				rules = [];
			}

			// Render existing rules.
			$tbody.empty();
			if ( rules.length > 0 ) {
				$.each( rules, function( idx, rule ) {
					renderRow( $tbody, rule );
				} );
			}

			updateJson( $container, $tbody, $hidden );

			// Add Rule button click handler.
			$addBtn.off( 'click' ).on( 'click', function( e ) {
				e.preventDefault();
				var newRule = {
					condition: 'item',
					min: '',
					max: '',
					cost_per_order: '0.00',
					additional_cost: '0.00',
					per_value: '1'
				};
				renderRow( $tbody, newRule );
				updateJson( $container, $tbody, $hidden );
			} );

			// Dynamic row input change / delete handlers.
			$tbody.off( 'change input', 'input, select' ).on( 'change input', 'input, select', function() {
				validateRows( $tbody );
				updateJson( $container, $tbody, $hidden );
			} );

			$tbody.off( 'click', '.wfs-delete-rule-btn' ).on( 'click', '.wfs-delete-rule-btn', function( e ) {
				e.preventDefault();
				if ( confirm( wfs_i18n.confirm_delete || 'Delete this rule?' ) ) {
					$( this ).closest( 'tr' ).remove();
					updateJson( $container, $tbody, $hidden );
				}
			} );
		} );
	}

	function renderRow( $tbody, rule ) {
		var cond = rule.condition || rule.condition_id || 'item';
		var min  = ( rule.min !== undefined && rule.min !== null ) ? rule.min : '';
		var max  = ( rule.max !== undefined && rule.max !== null ) ? rule.max : '';
		var cost = ( rule.cost_per_order !== undefined ) ? rule.cost_per_order : '0.00';
		var addCost = '0.00';
		var perVal  = '1';

		if ( rule.additional_cost !== undefined ) {
			addCost = rule.additional_cost;
		} else if ( rule.additional_costs && rule.additional_costs.length > 0 ) {
			addCost = rule.additional_costs[0].additional_cost || '0.00';
			perVal  = rule.additional_costs[0].per_value || '1';
		}

		if ( rule.per_value !== undefined ) {
			perVal = rule.per_value;
		}

		var html = '<tr class="wfs-rule-row">' +
			'<td>' +
				'<select class="wfs-input-condition widefat">' +
					'<option value="item"' + ( cond === 'item' ? ' selected' : '' ) + '>' + ( wfs_i18n.item_label || 'Item Count' ) + '</option>' +
					'<option value="weight"' + ( cond === 'weight' ? ' selected' : '' ) + '>' + ( wfs_i18n.weight_label || 'Weight' ) + '</option>' +
					'<option value="price"' + ( cond === 'price' ? ' selected' : '' ) + '>' + ( wfs_i18n.price_label || 'Subtotal Price' ) + '</option>' +
				'</select>' +
			'</td>' +
			'<td><input type="number" step="any" class="wfs-input-min widefat" placeholder="0" value="' + escAttr( min ) + '" /></td>' +
			'<td><input type="number" step="any" class="wfs-input-max widefat" placeholder="∞" value="' + escAttr( max ) + '" /></td>' +
			'<td><input type="number" step="0.01" class="wfs-input-cost widefat" placeholder="0.00" value="' + escAttr( cost ) + '" /></td>' +
			'<td><input type="number" step="0.01" class="wfs-input-additional widefat" placeholder="0.00" value="' + escAttr( addCost ) + '" /></td>' +
			'<td><input type="number" step="any" class="wfs-input-per-val widefat" placeholder="1" value="' + escAttr( perVal ) + '" /></td>' +
			'<td style="text-align: center;">' +
				'<button type="button" class="button wfs-delete-rule-btn" title="Delete Rule">&times;</button>' +
			'</td>' +
		'</tr>';

		$tbody.append( html );
	}

	function validateRows( $tbody ) {
		$tbody.find( 'tr.wfs-rule-row' ).each( function() {
			var $row = $( this );
			var minVal = parseFloat( $row.find( '.wfs-input-min' ).val() );
			var maxVal = parseFloat( $row.find( '.wfs-input-max' ).val() );

			if ( ! isNaN( minVal ) && ! isNaN( maxVal ) && minVal > maxVal ) {
				$row.find( '.wfs-input-min, .wfs-input-max' ).css( 'border-color', '#dc3232' );
			} else {
				$row.find( '.wfs-input-min, .wfs-input-max' ).css( 'border-color', '' );
			}
		} );
	}

	function updateJson( $container, $tbody, $hidden ) {
		var rules = [];
		var $rows = $tbody.find( 'tr.wfs-rule-row' );

		$rows.each( function() {
			var $row = $( this );
			var min  = $row.find( '.wfs-input-min' ).val();
			var max  = $row.find( '.wfs-input-max' ).val();

			rules.push( {
				condition: $row.find( '.wfs-input-condition' ).val(),
				min: min !== '' ? parseFloat( min ) : '',
				max: max !== '' ? parseFloat( max ) : '',
				cost_per_order: parseFloat( $row.find( '.wfs-input-cost' ).val() || 0 ),
				additional_cost: parseFloat( $row.find( '.wfs-input-additional' ).val() || 0 ),
				per_value: parseFloat( $row.find( '.wfs-input-per-val' ).val() || 1 )
			} );
		} );

		$hidden.val( JSON.stringify( rules ) );
		$container.find( '.wfs-rule-count' ).text( $rows.length );
	}

	function escAttr( str ) {
		if ( str === null || str === undefined ) return '';
		return String( str ).replace( /"/g, '&quot;' );
	}

	function initTaxStatusToggle() {
		var $taxSelect = $( 'select[name*="tax_status"]' );
		if ( ! $taxSelect.length ) return;

		function updateToggle() {
			var val = $taxSelect.val();
			var $includeTaxRow = $( 'select[name*="prices_include_tax"]' ).closest( 'tr' );
			if ( 'taxable' === val ) {
				$includeTaxRow.show();
			} else {
				$includeTaxRow.hide();
			}
		}

		$taxSelect.off( 'change.wfsTax' ).on( 'change.wfsTax', updateToggle );
		updateToggle();
	}

	// Initialize on page load.
	initTableEditors();
	initTaxStatusToggle();

	$( document ).ajaxComplete( function() {
		initTableEditors();
		initTaxStatusToggle();
	} );
} );
