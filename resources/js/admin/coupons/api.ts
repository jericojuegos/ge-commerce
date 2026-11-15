import apiFetch from '@wordpress/api-fetch';
import type {
	CartTotals,
	Coupon,
	CouponFormPayload,
	QuotePreviewPayload,
	ZonesPayload,
} from './types';

interface ApiResponse< T > {
	ok: boolean;
	data: T;
}

export async function fetchCoupons(): Promise< { items: Coupon[]; total: number } > {
	const res = await apiFetch< ApiResponse< { items: Coupon[]; total: number } > >( {
		path: '/ge-commerce/v1/coupons?per_page=50',
	} );
	return res.data;
}

export async function saveCoupon( payload: CouponFormPayload ): Promise< Coupon > {
	const isEdit = Boolean( payload.id );
	const res = await apiFetch< ApiResponse< Coupon > >( {
		path: isEdit ? `/ge-commerce/v1/coupons/${ payload.id }` : '/ge-commerce/v1/coupons',
		method: isEdit ? 'PUT' : 'POST',
		data: payload,
	} );
	return res.data;
}

export async function deleteCoupon( id: number ): Promise< void > {
	await apiFetch( {
		path: `/ge-commerce/v1/coupons/${ id }`,
		method: 'DELETE',
	} );
}

export async function fetchZones(): Promise< ZonesPayload > {
	const res = await apiFetch< ApiResponse< ZonesPayload > >( {
		path: '/ge-commerce/v1/tax-shipping-zones',
	} );
	return res.data;
}

export async function saveZones( payload: ZonesPayload ): Promise< ZonesPayload > {
	const res = await apiFetch< ApiResponse< ZonesPayload > >( {
		path: '/ge-commerce/v1/tax-shipping-zones',
		method: 'PUT',
		data: payload,
	} );
	return res.data;
}

export async function runQuotePreview( payload: QuotePreviewPayload ): Promise< CartTotals > {
	const res = await apiFetch< ApiResponse< CartTotals > >( {
		path: '/ge-commerce/v1/cart/quote-preview',
		method: 'POST',
		data: payload,
	} );
	return res.data;
}
