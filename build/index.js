( function ( wp ) {
	const { createElement: el, createRoot, useState } = wp.element;
	const { Card, CardBody, CardHeader, Button, Notice } = wp.components;

	const formatCents = ( cents, currency = 'USD' ) => {
		return new Intl.NumberFormat( 'en-US', {
			style: 'currency',
			currency,
		} ).format( cents / 100 );
	};

	const AdminApp = () => {
		const [ activeTab, setActiveTab ] = useState( 'catalog' );

		return el(
			'div',
			{
				className: 'gec-admin-shell',
				style: {
					backgroundColor: '#F8FAFC',
					color: '#0F172A',
					padding: '24px',
					minHeight: 'calc(100vh - 100px)',
					boxSizing: 'border-box',
				},
			},
			el(
				'header',
				{
					style: {
						display: 'flex',
						justifyContent: 'space-between',
						alignItems: 'center',
						borderBottom: '1px solid #E2E8F0',
						paddingBottom: '16px',
						marginBottom: '24px',
					},
				},
				el(
					'div',
					null,
					el(
						'h1',
						{ style: { margin: 0, fontSize: '24px', fontWeight: 700, color: '#0F172A' } },
						'GE Commerce'
					),
					el(
						'p',
						{ style: { margin: '4px 0 0', color: '#475569', fontSize: '13px' } },
						'Hexagonal Custom-Table Store Engine (wp_gec_*)'
					)
				),
				el(
					'div',
					{ style: { display: 'flex', gap: '8px' } },
					el(
						Button,
						{
							variant: activeTab === 'catalog' ? 'primary' : 'secondary',
							onClick: () => setActiveTab( 'catalog' ),
						},
						'Catalog & SKUs'
					),
					el(
						Button,
						{
							variant: activeTab === 'orders' ? 'primary' : 'secondary',
							onClick: () => setActiveTab( 'orders' ),
						},
						'Orders'
					),
					el(
						Button,
						{
							variant: activeTab === 'settings' ? 'primary' : 'secondary',
							onClick: () => setActiveTab( 'settings' ),
						},
						'Gateways & AI Copilot'
					)
				)
			),
			el(
				Notice,
				{ status: 'info', isDismissible: false },
				'GE Commerce Core initialized. Sample minor-unit formatting: ',
				el( 'strong', null, formatCents( 2999, 'USD' ) ),
				' (2999 cents).'
			),
			el(
				'div',
				{ style: { marginTop: '20px' } },
				el(
					Card,
					{ style: { border: '1px solid #E2E8F0', backgroundColor: '#FFFFFF' } },
					el(
						CardHeader,
						null,
						el(
							'strong',
							{ style: { fontSize: '16px', color: '#0F172A' } },
							activeTab === 'catalog'
								? 'Product Catalog & SKUs'
								: activeTab === 'orders'
								? 'Order Management & Stock Reservations'
								: 'Payment Gateways & Gemini AI Copilot'
						)
					),
					el(
						CardBody,
						null,
						el(
							'p',
							{ style: { margin: 0, color: '#334155' } },
							'Active view: ',
							el( 'code', null, activeTab ),
							'. Ready for Wave 1–4 feature modules.'
						)
					)
				)
			)
		);
	};

	const mountNode = document.getElementById( 'ge-commerce-admin-root' );
	if ( mountNode && createRoot ) {
		createRoot( mountNode ).render( el( AdminApp ) );
	}
} )( window.wp );
