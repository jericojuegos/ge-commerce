import { createRoot, useState } from '@wordpress/element';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { Card, CardBody, CardHeader, Button } from '@wordpress/components';
import { CatalogPage } from './catalog/CatalogPage';

const queryClient = new QueryClient();

export const formatCents = ( cents: number, currency = 'USD' ): string => {
	return new Intl.NumberFormat( 'en-US', {
		style: 'currency',
		currency,
	} ).format( cents / 100 );
};

export const AdminApp = (): JSX.Element => {
	const [ activeTab, setActiveTab ] = useState< 'catalog' | 'orders' | 'settings' >( 'catalog' );

	return (
		<div
			className="gec-admin-shell"
			style={ {
				backgroundColor: '#F8FAFC',
				color: '#0F172A',
				padding: '24px',
				minHeight: 'calc(100vh - 100px)',
				boxSizing: 'border-box',
			} }
		>
			<header
				style={ {
					display: 'flex',
					justifyContent: 'space-between',
					alignItems: 'center',
					borderBottom: '1px solid #E2E8F0',
					paddingBottom: '16px',
					marginBottom: '24px',
				} }
			>
				<div>
					<h1 style={ { margin: 0, fontSize: '24px', fontWeight: 700, color: '#0F172A' } }>
						GE Commerce
					</h1>
					<p style={ { margin: '4px 0 0', color: '#475569', fontSize: '13px' } }>
						Hexagonal Custom-Table Store Engine (wp_gec_*)
					</p>
				</div>
				<div style={ { display: 'flex', gap: '8px' } }>
					<Button
						variant={ activeTab === 'catalog' ? 'primary' : 'secondary' }
						onClick={ () => setActiveTab( 'catalog' ) }
					>
						Catalog & SKUs
					</Button>
					<Button
						variant={ activeTab === 'orders' ? 'primary' : 'secondary' }
						onClick={ () => setActiveTab( 'orders' ) }
					>
						Orders
					</Button>
					<Button
						variant={ activeTab === 'settings' ? 'primary' : 'secondary' }
						onClick={ () => setActiveTab( 'settings' ) }
					>
						Gateways & AI Copilot
					</Button>
				</div>
			</header>

			<div style={ { marginTop: '20px' } }>
				{ activeTab === 'catalog' ? (
					<CatalogPage />
				) : (
					<Card style={ { border: '1px solid #E2E8F0', backgroundColor: '#FFFFFF' } }>
						<CardHeader>
							<strong style={ { fontSize: '16px', color: '#0F172A' } }>
								{ activeTab === 'orders' && 'Order Management & Stock Reservations' }
								{ activeTab === 'settings' && 'Payment Gateways & Gemini AI Copilot' }
							</strong>
						</CardHeader>
						<CardBody>
							<p style={ { margin: 0, color: '#334155' } }>
								Active view: <code>{ activeTab }</code>. Ready for Wave 3–4 feature modules.
							</p>
						</CardBody>
					</Card>
				) }
			</div>
		</div>
	);
};

const mountNode = document.getElementById( 'ge-commerce-admin-root' );
if ( mountNode ) {
	const root = createRoot( mountNode );
	root.render(
		<QueryClientProvider client={ queryClient }>
			<AdminApp />
		</QueryClientProvider>
	);
}
