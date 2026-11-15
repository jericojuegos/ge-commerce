import { useState } from '@wordpress/element';
import {
	Button,
	Modal,
	Notice,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import type { Coupon, CouponFormPayload, DiscountType } from './types';

interface CouponModalProps {
	coupon: Coupon | null;
	isSaving: boolean;
	errorMessage: string | null;
	onClose: () => void;
	onSubmit: ( payload: CouponFormPayload ) => void;
}

export const CouponModal = ( {
	coupon,
	isSaving,
	errorMessage,
	onClose,
	onSubmit,
}: CouponModalProps ): JSX.Element => {
	const [ code, setCode ] = useState( coupon?.code ?? '' );
	const [ discountType, setDiscountType ] = useState< DiscountType >( coupon?.discount_type ?? 'fixed_cents' );
	const [ amountValue, setAmountValue ] = useState( String( coupon?.amount_value ?? 1000 ) );
	const [ minOrderCents, setMinOrderCents ] = useState( String( coupon?.min_order_cents ?? 0 ) );
	const [ usageLimit, setUsageLimit ] = useState(
		coupon && coupon.usage_limit !== null ? String( coupon.usage_limit ) : ''
	);
	const [ expiresAt, setExpiresAt ] = useState( coupon?.expires_at ?? '' );

	const handleSubmit = ( e: React.FormEvent ): void => {
		e.preventDefault();
		const parsedLimit = usageLimit.trim() !== '' ? Number.parseInt( usageLimit, 10 ) : null;
		onSubmit( {
			id: coupon?.id ?? null,
			code: code.trim().toUpperCase(),
			discount_type: discountType,
			amount_value: Number.parseInt( amountValue, 10 ) || 0,
			min_order_cents: Number.parseInt( minOrderCents, 10 ) || 0,
			usage_limit: Number.isNaN( parsedLimit as number ) ? null : parsedLimit,
			expires_at: expiresAt.trim() !== '' ? expiresAt.trim() : null,
		} );
	};

	return (
		<Modal
			title={ coupon ? `Edit Coupon: ${ coupon.code }` : 'New Discount Coupon' }
			onRequestClose={ onClose }
			style={ { maxWidth: '560px', width: '100%' } }
		>
			<form onSubmit={ handleSubmit }>
				{ errorMessage && (
					<div style={ { marginBottom: '16px' } }>
						<Notice status="error" isDismissible={ false }>
							{ errorMessage }
						</Notice>
					</div>
				) }

				<div style={ { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px' } }>
					<TextControl
						label="Coupon Code"
						value={ code }
						onChange={ ( val ) => setCode( val.toUpperCase() ) }
						placeholder="SAVE20"
						required
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
					<SelectControl
						label="Discount Type"
						value={ discountType }
						options={ [
							{ label: 'Fixed Amount (cents)', value: 'fixed_cents' },
							{ label: 'Percentage (basis points, 1000 = 10%)', value: 'percent_bps' },
						] }
						onChange={ ( val ) => setDiscountType( val as DiscountType ) }
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</div>

				<div style={ { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px', marginTop: '12px' } }>
					<TextControl
						label={ discountType === 'fixed_cents' ? 'Discount Amount (cents)' : 'Rate (basis points, max 10000)' }
						type="number"
						min={ 0 }
						max={ discountType === 'percent_bps' ? 10000 : undefined }
						value={ amountValue }
						onChange={ setAmountValue }
						help={ discountType === 'fixed_cents' ? 'e.g. 1000 = $10.00' : 'e.g. 1500 = 15.00%' }
						required
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
					<TextControl
						label="Minimum Order (cents)"
						type="number"
						min={ 0 }
						value={ minOrderCents }
						onChange={ setMinOrderCents }
						help="0 for no minimum"
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</div>

				<div style={ { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px', marginTop: '12px' } }>
					<TextControl
						label="Usage Limit (optional)"
						type="number"
						min={ 1 }
						value={ usageLimit }
						onChange={ setUsageLimit }
						placeholder="Unlimited"
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
					<TextControl
						label="Expires At UTC (optional)"
						value={ expiresAt }
						onChange={ setExpiresAt }
						placeholder="2027-12-31 23:59:59"
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</div>

				<div style={ { display: 'flex', justifyContent: 'flex-end', gap: '8px', marginTop: '20px' } }>
					<Button variant="secondary" type="button" onClick={ onClose } disabled={ isSaving }>
						Cancel
					</Button>
					<Button variant="primary" type="submit" isBusy={ isSaving } disabled={ isSaving }>
						{ isSaving ? 'Saving...' : 'Save Coupon' }
					</Button>
				</div>
			</form>
		</Modal>
	);
};
