import { useState } from '@wordpress/element';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Notice,
	SelectControl,
	Spinner,
} from '@wordpress/components';
import { deleteProduct, fetchProducts, saveProduct } from './api';
import { ProductModal } from './ProductModal';
import type { Product, ProductFormPayload, ProductStatus } from './types';

const formatCents = ( cents: number, currency = 'USD' ): string =>
	new Intl.NumberFormat( 'en-US', { style: 'currency', currency } ).format( cents / 100 );

const STATUS_COLORS: Record< ProductStatus, string > = {
	published: '#16A34A',
	draft: '#D97706',
	archived: '#DC2626',
};

const formatPriceRange = ( product: Product ): string => {
	if ( product.skus.length === 0 ) {
		return '—';
	}
	const prices = product.skus.map( ( s ) => s.price_cents );
	const min = Math.min( ...prices );
	const max = Math.max( ...prices );
	return min === max ? formatCents( min ) : `${ formatCents( min ) } – ${ formatCents( max ) }`;
};

export const CatalogPage = (): JSX.Element => {
	const queryClient = useQueryClient();
	const [ statusFilter, setStatusFilter ] = useState< ProductStatus | '' >( '' );
	const [ modalProduct, setModalProduct ] = useState< Product | null | undefined >( undefined );
	const [ formError, setFormError ] = useState< string | null >( null );

	const { data, isLoading, error } = useQuery( {
		queryKey: [ 'gec-products', statusFilter ],
		queryFn: () => fetchProducts( statusFilter ),
	} );

	const saveMutation = useMutation( {
		mutationFn: ( payload: ProductFormPayload ) => saveProduct( payload ),
		onSuccess: () => {
			setFormError( null );
			setModalProduct( undefined );
			void queryClient.invalidateQueries( { queryKey: [ 'gec-products' ] } );
		},
		onError: ( err: unknown ) => {
			const msg =
				err && typeof err === 'object' && 'message' in err
					? String( ( err as { message: unknown } ).message )
					: 'Failed to save product.';
			setFormError( msg );
		},
	} );

	const deleteMutation = useMutation( {
		mutationFn: ( id: number ) => deleteProduct( id ),
		onSuccess: () => {
			void queryClient.invalidateQueries( { queryKey: [ 'gec-products' ] } );
		},
	} );

	const products = data?.items ?? [];

	return (
		<div>
			<Card style={ { border: '1px solid #E2E8F0', backgroundColor: '#FFFFFF' } }>
				<CardHeader>
					<div style={ { display: 'flex', justifyContent: 'space-between', alignItems: 'center', width: '100%' } }>
						<div>
							<strong style={ { fontSize: '16px', color: '#0F172A' } }>
								Product Catalog &amp; SKUs
							</strong>
							<span style={ { marginLeft: '12px', color: '#475569', fontSize: '13px' } }>
								{ data ? `${ data.total } total` : '' }
							</span>
						</div>
						<div style={ { display: 'flex', gap: '12px', alignItems: 'center' } }>
							<SelectControl
								label="Filter"
								hideLabelFromVision
								value={ statusFilter }
								options={ [
									{ label: 'All Statuses', value: '' },
									{ label: 'Published', value: 'published' },
									{ label: 'Draft', value: 'draft' },
									{ label: 'Archived', value: 'archived' },
								] }
								onChange={ ( val ) => setStatusFilter( val as ProductStatus | '' ) }
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
							<Button
								variant="primary"
								onClick={ () => {
									setFormError( null );
									setModalProduct( null );
								} }
							>
								+ Add Product
							</Button>
						</div>
					</div>
				</CardHeader>
				<CardBody>
					{ error && (
						<Notice status="error" isDismissible={ false }>
							Failed to load products from <code>/ge-commerce/v1/products</code>.
						</Notice>
					) }

					{ isLoading && (
						<div style={ { padding: '24px', textAlign: 'center' } }>
							<Spinner />
						</div>
					) }

					{ ! isLoading && ! error && products.length === 0 && (
						<div style={ { padding: '32px', textAlign: 'center', color: '#475569' } }>
							<p style={ { margin: '0 0 12px' } }>No products found in custom catalog tables.</p>
							<Button
								variant="secondary"
								onClick={ () => {
									setFormError( null );
									setModalProduct( null );
								} }
							>
								Create your first product
							</Button>
						</div>
					) }

					{ ! isLoading && products.length > 0 && (
						<table style={ { width: '100%', borderCollapse: 'collapse', textAlign: 'left' } }>
							<thead>
								<tr style={ { borderBottom: '1px solid #E2E8F0', color: '#475569', fontSize: '12px' } }>
									<th style={ { padding: '10px 8px' } }>Product</th>
									<th style={ { padding: '10px 8px' } }>Status</th>
									<th style={ { padding: '10px 8px' } }>SKUs</th>
									<th style={ { padding: '10px 8px' } }>Price Range</th>
									<th style={ { padding: '10px 8px' } }>Available Stock</th>
									<th style={ { padding: '10px 8px', textAlign: 'right' } }>Actions</th>
								</tr>
							</thead>
							<tbody>
								{ products.map( ( product ) => {
									const totalStock = product.skus.reduce(
										( sum, s ) => sum + s.available_qty,
										0
									);
									return (
										<tr key={ product.id } style={ { borderBottom: '1px solid #F1F5F9' } }>
											<td style={ { padding: '12px 8px' } }>
												<div style={ { fontWeight: 600, color: '#0F172A' } }>{ product.title }</div>
												<div style={ { fontSize: '12px', color: '#64748B' } }>/{ product.slug }</div>
											</td>
											<td style={ { padding: '12px 8px' } }>
												<span
													style={ {
														display: 'inline-block',
														padding: '2px 8px',
														borderRadius: '9999px',
														fontSize: '11px',
														fontWeight: 600,
														color: '#FFFFFF',
														backgroundColor: STATUS_COLORS[ product.status ],
														textTransform: 'uppercase',
													} }
												>
													{ product.status }
												</span>
											</td>
											<td style={ { padding: '12px 8px', color: '#334155' } }>
												{ product.skus.length }
											</td>
											<td style={ { padding: '12px 8px', color: '#0F172A', fontWeight: 500 } }>
												{ formatPriceRange( product ) }
											</td>
											<td style={ { padding: '12px 8px', color: '#334155' } }>{ totalStock }</td>
											<td style={ { padding: '12px 8px', textAlign: 'right' } }>
												<div style={ { display: 'inline-flex', gap: '6px' } }>
													<Button
														variant="secondary"
														size="small"
														onClick={ () => {
															setFormError( null );
															setModalProduct( product );
														} }
													>
														Edit
													</Button>
													<Button
														variant="tertiary"
														isDestructive
														size="small"
														disabled={ deleteMutation.isPending }
														onClick={ () => deleteMutation.mutate( product.id ) }
													>
														Delete
													</Button>
												</div>
											</td>
										</tr>
									);
								} ) }
							</tbody>
						</table>
					) }
				</CardBody>
			</Card>

			{ modalProduct !== undefined && (
				<ProductModal
					product={ modalProduct }
					isSaving={ saveMutation.isPending }
					errorMessage={ formError }
					onClose={ () => setModalProduct( undefined ) }
					onSubmit={ ( payload ) => saveMutation.mutate( payload ) }
				/>
			) }
		</div>
	);
};
