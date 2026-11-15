import { useEffect, useState } from '@wordpress/element';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Notice,
	SelectControl,
	Spinner,
	TextControl,
} from '@wordpress/components';
import {
	deleteCoupon,
	fetchCoupons,
	fetchZones,
	runQuotePreview,
	saveCoupon,
	saveZones,
} from './api';
import { CouponModal } from './CouponModal';
import type {
	CartTotals,
	Coupon,
	CouponFormPayload,
	ShippingZone,
	TaxZone,
} from './types';

const formatCents = ( cents: number, currency = 'USD' ): string =>
	new Intl.NumberFormat( 'en-US', { style: 'currency', currency } ).format( cents / 100 );

const formatBps = ( bps: number ): string => `${ ( bps / 100 ).toFixed( 2 ) }%`;

const getCouponStatus = ( coupon: Coupon ): { label: string; color: string } => {
	if ( coupon.expires_at ) {
		const expTime = Date.parse( coupon.expires_at.replace( ' ', 'T' ) + 'Z' );
		if ( ! Number.isNaN( expTime ) && expTime <= Date.now() ) {
			return { label: 'Expired', color: '#DC2626' };
		}
	}
	if ( coupon.usage_limit !== null && coupon.used_count >= coupon.usage_limit ) {
		return { label: 'Limit Reached', color: '#D97706' };
	}
	return { label: 'Active', color: '#16A34A' };
};

export const CouponsAndTaxPage = (): JSX.Element => {
	const queryClient = useQueryClient();
	const [ modalCoupon, setModalCoupon ] = useState< Coupon | null | undefined >( undefined );
	const [ couponFormError, setCouponFormError ] = useState< string | null >( null );

	// Zones draft state
	const [ taxZones, setTaxZones ] = useState< TaxZone[] >( [] );
	const [ shippingZones, setShippingZones ] = useState< ShippingZone[] >( [] );
	const [ zonesNotice, setZonesNotice ] = useState< { status: 'success' | 'error'; message: string } | null >( null );

	// Quote Simulator state
	const [ simSubtotal, setSimSubtotal ] = useState( '4999' );
	const [ simCoupon, setSimCoupon ] = useState( '' );
	const [ simCountry, setSimCountry ] = useState( 'US' );
	const [ simRegion, setSimRegion ] = useState( 'CA' );
	const [ simMethod, setSimMethod ] = useState< 'standard' | 'express' >( 'standard' );
	const [ simResult, setSimResult ] = useState< CartTotals | null >( null );
	const [ simError, setSimError ] = useState< string | null >( null );

	const couponsQuery = useQuery( {
		queryKey: [ 'gec-coupons' ],
		queryFn: fetchCoupons,
	} );

	const zonesQuery = useQuery( {
		queryKey: [ 'gec-zones' ],
		queryFn: fetchZones,
	} );

	useEffect( () => {
		if ( zonesQuery.data ) {
			setTaxZones( zonesQuery.data.tax_zones );
			setShippingZones( zonesQuery.data.shipping_zones );
		}
	}, [ zonesQuery.data ] );

	const saveCouponMutation = useMutation( {
		mutationFn: ( payload: CouponFormPayload ) => saveCoupon( payload ),
		onSuccess: () => {
			setCouponFormError( null );
			setModalCoupon( undefined );
			void queryClient.invalidateQueries( { queryKey: [ 'gec-coupons' ] } );
		},
		onError: ( err: unknown ) => {
			const msg =
				err && typeof err === 'object' && 'message' in err
					? String( ( err as { message: unknown } ).message )
					: 'Failed to save coupon.';
			setCouponFormError( msg );
		},
	} );

	const deleteCouponMutation = useMutation( {
		mutationFn: ( id: number ) => deleteCoupon( id ),
		onSuccess: () => {
			void queryClient.invalidateQueries( { queryKey: [ 'gec-coupons' ] } );
		},
	} );

	const saveZonesMutation = useMutation( {
		mutationFn: () => saveZones( { tax_zones: taxZones, shipping_zones: shippingZones } ),
		onSuccess: ( saved ) => {
			setTaxZones( saved.tax_zones );
			setShippingZones( saved.shipping_zones );
			setZonesNotice( { status: 'success', message: 'Tax & shipping zones saved.' } );
			void queryClient.invalidateQueries( { queryKey: [ 'gec-zones' ] } );
		},
		onError: ( err: unknown ) => {
			const msg =
				err && typeof err === 'object' && 'message' in err
					? String( ( err as { message: unknown } ).message )
					: 'Failed to save zones.';
			setZonesNotice( { status: 'error', message: msg } );
		},
	} );

	const quoteMutation = useMutation( {
		mutationFn: () =>
			runQuotePreview( {
				subtotal_cents: Number.parseInt( simSubtotal, 10 ) || 0,
				coupon_code: simCoupon.trim() !== '' ? simCoupon.trim().toUpperCase() : null,
				country: simCountry.trim().toUpperCase() || 'US',
				region: simRegion.trim().toUpperCase() || '*',
				shipping_method: simMethod,
			} ),
		onSuccess: ( totals ) => {
			setSimError( null );
			setSimResult( totals );
		},
		onError: ( err: unknown ) => {
			const msg =
				err && typeof err === 'object' && 'message' in err
					? String( ( err as { message: unknown } ).message )
					: 'Quote simulation failed.';
			setSimError( msg );
		},
	} );

	const coupons = couponsQuery.data?.items ?? [];

	return (
		<div style={ { display: 'flex', flexDirection: 'column', gap: '20px' } }>
			<Card style={ { border: '1px solid #E2E8F0', backgroundColor: '#FFFFFF' } }>
				<CardHeader>
					<div style={ { display: 'flex', justifyContent: 'space-between', alignItems: 'center', width: '100%' } }>
						<div>
							<strong style={ { fontSize: '16px', color: '#0F172A' } }>
								Discount Coupons
							</strong>
							<span style={ { marginLeft: '12px', color: '#475569', fontSize: '13px' } }>
								{ couponsQuery.data ? `${ couponsQuery.data.total } total` : '' }
							</span>
						</div>
						<Button
							variant="primary"
							onClick={ () => {
								setCouponFormError( null );
								setModalCoupon( null );
							} }
						>
							+ Add Coupon
						</Button>
					</div>
				</CardHeader>
				<CardBody>
					{ couponsQuery.error && (
						<Notice status="error" isDismissible={ false }>
							Failed to load coupons from <code>/ge-commerce/v1/coupons</code>.
						</Notice>
					) }

					{ couponsQuery.isLoading && (
						<div style={ { padding: '24px', textAlign: 'center' } }>
							<Spinner />
						</div>
					) }

					{ ! couponsQuery.isLoading && ! couponsQuery.error && coupons.length === 0 && (
						<div style={ { padding: '28px', textAlign: 'center', color: '#475569' } }>
							<p style={ { margin: '0 0 12px' } }>No discount coupons configured yet.</p>
							<Button
								variant="secondary"
								onClick={ () => {
									setCouponFormError( null );
									setModalCoupon( null );
								} }
							>
								Create your first coupon
							</Button>
						</div>
					) }

					{ ! couponsQuery.isLoading && coupons.length > 0 && (
						<table style={ { width: '100%', borderCollapse: 'collapse', textAlign: 'left' } }>
							<thead>
								<tr style={ { borderBottom: '1px solid #E2E8F0', color: '#475569', fontSize: '12px' } }>
									<th style={ { padding: '10px 8px' } }>Code</th>
									<th style={ { padding: '10px 8px' } }>Discount</th>
									<th style={ { padding: '10px 8px' } }>Min Order</th>
									<th style={ { padding: '10px 8px' } }>Usage</th>
									<th style={ { padding: '10px 8px' } }>Expires (UTC)</th>
									<th style={ { padding: '10px 8px' } }>Status</th>
									<th style={ { padding: '10px 8px', textAlign: 'right' } }>Actions</th>
								</tr>
							</thead>
							<tbody>
								{ coupons.map( ( coupon ) => {
									const status = getCouponStatus( coupon );
									return (
										<tr key={ coupon.id } style={ { borderBottom: '1px solid #F1F5F9' } }>
											<td style={ { padding: '12px 8px', fontFamily: 'monospace', fontWeight: 700, color: '#0F172A' } }>
												{ coupon.code }
											</td>
											<td style={ { padding: '12px 8px', color: '#0F172A', fontWeight: 500 } }>
												{ coupon.discount_type === 'fixed_cents'
													? formatCents( coupon.amount_value )
													: `${ formatBps( coupon.amount_value ) } (${ coupon.amount_value } bps)` }
											</td>
											<td style={ { padding: '12px 8px', color: '#334155' } }>
												{ coupon.min_order_cents > 0 ? formatCents( coupon.min_order_cents ) : 'None' }
											</td>
											<td style={ { padding: '12px 8px', color: '#334155' } }>
												{ coupon.used_count } / { coupon.usage_limit ?? '∞' }
											</td>
											<td style={ { padding: '12px 8px', color: '#64748B', fontSize: '12px' } }>
												{ coupon.expires_at ?? 'Never' }
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
														backgroundColor: status.color,
														textTransform: 'uppercase',
													} }
												>
													{ status.label }
												</span>
											</td>
											<td style={ { padding: '12px 8px', textAlign: 'right' } }>
												<div style={ { display: 'inline-flex', gap: '6px' } }>
													<Button
														variant="secondary"
														size="small"
														onClick={ () => {
															setCouponFormError( null );
															setModalCoupon( coupon );
														} }
													>
														Edit
													</Button>
													<Button
														variant="tertiary"
														isDestructive
														size="small"
														disabled={ deleteCouponMutation.isPending }
														onClick={ () => deleteCouponMutation.mutate( coupon.id ) }
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

			<div style={ { display: 'grid', gridTemplateColumns: '1.4fr 1fr', gap: '20px', alignItems: 'start' } }>
				<Card style={ { border: '1px solid #E2E8F0', backgroundColor: '#FFFFFF' } }>
					<CardHeader>
						<div style={ { display: 'flex', justifyContent: 'space-between', alignItems: 'center', width: '100%' } }>
							<strong style={ { fontSize: '15px', color: '#0F172A' } }>
								Tax &amp; Shipping Zones (Basis Points &amp; Cents)
							</strong>
							<Button
								variant="primary"
								size="small"
								isBusy={ saveZonesMutation.isPending }
								disabled={ saveZonesMutation.isPending }
								onClick={ () => saveZonesMutation.mutate() }
							>
								Save Zones
							</Button>
						</div>
					</CardHeader>
					<CardBody>
						{ zonesNotice && (
							<div style={ { marginBottom: '12px' } }>
								<Notice status={ zonesNotice.status } onRemove={ () => setZonesNotice( null ) }>
									{ zonesNotice.message }
								</Notice>
							</div>
						) }

						<div style={ { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '8px' } }>
							<strong style={ { fontSize: '13px', color: '#334155' } }>Tax Zones</strong>
							<Button
								variant="secondary"
								size="small"
								onClick={ () =>
									setTaxZones( ( prev ) => [
										...prev,
										{ country: 'US', region: '*', rate_bps: 0, label: 'New Tax Zone' },
									] )
								}
							>
								+ Tax Zone
							</Button>
						</div>

						{ taxZones.map( ( zone, idx ) => (
							<div
								key={ idx }
								style={ {
									display: 'grid',
									gridTemplateColumns: '70px 70px 95px 1fr auto',
									gap: '6px',
									alignItems: 'end',
									marginBottom: '8px',
								} }
							>
								<TextControl
									label={ idx === 0 ? 'Country' : undefined }
									value={ zone.country }
									onChange={ ( val ) =>
										setTaxZones( ( prev ) =>
											prev.map( ( z, i ) => ( i === idx ? { ...z, country: val.toUpperCase() } : z ) )
										)
									}
									__next40pxDefaultSize
									__nextHasNoMarginBottom
								/>
								<TextControl
									label={ idx === 0 ? 'Region' : undefined }
									value={ zone.region }
									onChange={ ( val ) =>
										setTaxZones( ( prev ) =>
											prev.map( ( z, i ) => ( i === idx ? { ...z, region: val.toUpperCase() } : z ) )
										)
									}
									__next40pxDefaultSize
									__nextHasNoMarginBottom
								/>
								<TextControl
									label={ idx === 0 ? 'Rate (bps)' : undefined }
									type="number"
									min={ 0 }
									max={ 10000 }
									value={ String( zone.rate_bps ) }
									onChange={ ( val ) =>
										setTaxZones( ( prev ) =>
											prev.map( ( z, i ) =>
												i === idx ? { ...z, rate_bps: Number.parseInt( val, 10 ) || 0 } : z
											)
										)
									}
									__next40pxDefaultSize
									__nextHasNoMarginBottom
								/>
								<TextControl
									label={ idx === 0 ? 'Label' : undefined }
									value={ zone.label }
									onChange={ ( val ) =>
										setTaxZones( ( prev ) =>
											prev.map( ( z, i ) => ( i === idx ? { ...z, label: val } : z ) )
										)
									}
									__next40pxDefaultSize
									__nextHasNoMarginBottom
								/>
								<Button
									variant="tertiary"
									isDestructive
									size="small"
									disabled={ taxZones.length <= 1 }
									onClick={ () => setTaxZones( ( prev ) => prev.filter( ( _, i ) => i !== idx ) ) }
								>
									×
								</Button>
							</div>
						) ) }

						<div
							style={ {
								display: 'flex',
								justifyContent: 'space-between',
								alignItems: 'center',
								marginTop: '18px',
								marginBottom: '8px',
								paddingTop: '14px',
								borderTop: '1px solid #E2E8F0',
							} }
						>
							<strong style={ { fontSize: '13px', color: '#334155' } }>Shipping Zones</strong>
							<Button
								variant="secondary"
								size="small"
								onClick={ () =>
									setShippingZones( ( prev ) => [
										...prev,
										{
											country: 'US',
											region: '*',
											method: 'standard',
											flat_rate_cents: 599,
											free_over_cents: null,
											label: 'Standard Shipping',
										},
									] )
								}
							>
								+ Shipping Zone
							</Button>
						</div>

						{ shippingZones.map( ( zone, idx ) => (
							<div
								key={ idx }
								style={ {
									display: 'grid',
									gridTemplateColumns: '65px 65px 95px 90px 90px auto',
									gap: '6px',
									alignItems: 'end',
									marginBottom: '8px',
								} }
							>
								<TextControl
									label={ idx === 0 ? 'Country' : undefined }
									value={ zone.country }
									onChange={ ( val ) =>
										setShippingZones( ( prev ) =>
											prev.map( ( z, i ) => ( i === idx ? { ...z, country: val.toUpperCase() } : z ) )
										)
									}
									__next40pxDefaultSize
									__nextHasNoMarginBottom
								/>
								<TextControl
									label={ idx === 0 ? 'Region' : undefined }
									value={ zone.region }
									onChange={ ( val ) =>
										setShippingZones( ( prev ) =>
											prev.map( ( z, i ) => ( i === idx ? { ...z, region: val.toUpperCase() } : z ) )
										)
									}
									__next40pxDefaultSize
									__nextHasNoMarginBottom
								/>
								<SelectControl
									label={ idx === 0 ? 'Method' : undefined }
									value={ ( zone.method === 'express' ? 'express' : 'standard' ) as 'standard' | 'express' }
									options={ [
										{ label: 'standard', value: 'standard' },
										{ label: 'express', value: 'express' },
									] }
									onChange={ ( val ) =>
										setShippingZones( ( prev ) =>
											prev.map( ( z, i ) => ( i === idx ? { ...z, method: val } : z ) )
										)
									}
									__next40pxDefaultSize
									__nextHasNoMarginBottom
								/>
								<TextControl
									label={ idx === 0 ? 'Rate (c)' : undefined }
									type="number"
									min={ 0 }
									value={ String( zone.flat_rate_cents ) }
									onChange={ ( val ) =>
										setShippingZones( ( prev ) =>
											prev.map( ( z, i ) =>
												i === idx ? { ...z, flat_rate_cents: Number.parseInt( val, 10 ) || 0 } : z
											)
										)
									}
									__next40pxDefaultSize
									__nextHasNoMarginBottom
								/>
								<TextControl
									label={ idx === 0 ? 'Free >=(c)' : undefined }
									type="number"
									min={ 0 }
									value={ zone.free_over_cents !== null ? String( zone.free_over_cents ) : '' }
									placeholder="None"
									onChange={ ( val ) =>
										setShippingZones( ( prev ) =>
											prev.map( ( z, i ) =>
												i === idx
													? {
															...z,
															free_over_cents:
																val.trim() !== '' ? Number.parseInt( val, 10 ) || 0 : null,
													  }
													: z
											)
										)
									}
									__next40pxDefaultSize
									__nextHasNoMarginBottom
								/>
								<Button
									variant="tertiary"
									isDestructive
									size="small"
									disabled={ shippingZones.length <= 1 }
									onClick={ () => setShippingZones( ( prev ) => prev.filter( ( _, i ) => i !== idx ) ) }
								>
									×
								</Button>
							</div>
						) ) }
					</CardBody>
				</Card>

				<Card style={ { border: '1px solid #E2E8F0', backgroundColor: '#FFFFFF' } }>
					<CardHeader>
						<strong style={ { fontSize: '15px', color: '#0F172A' } }>
							Live Quote Simulator
						</strong>
					</CardHeader>
					<CardBody>
						{ simError && (
							<div style={ { marginBottom: '12px' } }>
								<Notice status="error" isDismissible={ false }>
									{ simError }
								</Notice>
							</div>
						) }

						<div style={ { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '10px' } }>
							<TextControl
								label="Subtotal (cents)"
								type="number"
								min={ 0 }
								value={ simSubtotal }
								onChange={ setSimSubtotal }
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
							<TextControl
								label="Coupon Code"
								value={ simCoupon }
								onChange={ ( val ) => setSimCoupon( val.toUpperCase() ) }
								placeholder="Optional"
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
							<TextControl
								label="Country"
								value={ simCountry }
								onChange={ ( val ) => setSimCountry( val.toUpperCase() ) }
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
							<TextControl
								label="Region / State"
								value={ simRegion }
								onChange={ ( val ) => setSimRegion( val.toUpperCase() ) }
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
						</div>

						<div style={ { marginTop: '10px', display: 'flex', gap: '10px', alignItems: 'end' } }>
							<div style={ { flex: 1 } }>
								<SelectControl
									label="Shipping Method"
									value={ simMethod }
									options={ [
										{ label: 'Standard', value: 'standard' },
										{ label: 'Express', value: 'express' },
									] }
									onChange={ ( val ) => setSimMethod( val as 'standard' | 'express' ) }
									__next40pxDefaultSize
									__nextHasNoMarginBottom
								/>
							</div>
							<Button
								variant="primary"
								isBusy={ quoteMutation.isPending }
								disabled={ quoteMutation.isPending }
								onClick={ () => quoteMutation.mutate() }
							>
								Calculate Quote
							</Button>
						</div>

						{ simResult && (
							<div
								style={ {
									marginTop: '16px',
									padding: '12px',
									backgroundColor: '#F8FAFC',
									border: '1px solid #E2E8F0',
									borderRadius: '6px',
									fontSize: '13px',
								} }
							>
								{ simResult.coupon_warning && (
									<div style={ { marginBottom: '8px', color: '#DC2626', fontWeight: 500 } }>
										Coupon warning: { simResult.coupon_warning }
									</div>
								) }
								<div style={ { display: 'flex', justifyContent: 'space-between', marginBottom: '4px' } }>
									<span>Subtotal</span>
									<strong>{ formatCents( simResult.subtotal_cents ) }</strong>
								</div>
								<div style={ { display: 'flex', justifyContent: 'space-between', marginBottom: '4px', color: '#16A34A' } }>
									<span>Discount { simResult.applied_coupon ? `(${ simResult.applied_coupon })` : '' }</span>
									<strong>-{ formatCents( simResult.discount_cents ) }</strong>
								</div>
								<div style={ { display: 'flex', justifyContent: 'space-between', marginBottom: '4px' } }>
									<span>Tax ({ simResult.tax_label } · { formatBps( simResult.tax_rate_bps ) })</span>
									<strong>{ formatCents( simResult.tax_cents ) }</strong>
								</div>
								<div style={ { display: 'flex', justifyContent: 'space-between', marginBottom: '8px' } }>
									<span>Shipping ({ simResult.shipping_label })</span>
									<strong>{ formatCents( simResult.shipping_cents ) }</strong>
								</div>
								<div
									style={ {
										display: 'flex',
										justifyContent: 'space-between',
										paddingTop: '8px',
										borderTop: '1px solid #CBD5E1',
										fontSize: '15px',
										color: '#0F172A',
									} }
								>
									<strong>Total</strong>
									<strong>{ formatCents( simResult.total_cents ) }</strong>
								</div>
							</div>
						) }
					</CardBody>
				</Card>
			</div>

			{ modalCoupon !== undefined && (
				<CouponModal
					coupon={ modalCoupon }
					isSaving={ saveCouponMutation.isPending }
					errorMessage={ couponFormError }
					onClose={ () => setModalCoupon( undefined ) }
					onSubmit={ ( payload ) => saveCouponMutation.mutate( payload ) }
				/>
			) }
		</div>
	);
};
