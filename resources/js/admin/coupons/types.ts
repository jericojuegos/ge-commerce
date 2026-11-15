export type DiscountType = 'fixed_cents' | 'percent_bps';

export interface Coupon {
	id: number;
	code: string;
	discount_type: DiscountType;
	amount_value: number;
	min_order_cents: number;
	usage_limit: number | null;
	used_count: number;
	expires_at: string | null;
}

export interface CouponFormPayload {
	id?: number | null;
	code: string;
	discount_type: DiscountType;
	amount_value: number;
	min_order_cents: number;
	usage_limit: number | null;
	expires_at: string | null;
}

export interface TaxZone {
	country: string;
	region: string;
	rate_bps: number;
	label: string;
}

export interface ShippingZone {
	country: string;
	region: string;
	method: string;
	flat_rate_cents: number;
	free_over_cents: number | null;
	label: string;
}

export interface ZonesPayload {
	tax_zones: TaxZone[];
	shipping_zones: ShippingZone[];
}

export interface CartTotals {
	subtotal_cents: number;
	discount_cents: number;
	taxable_subtotal_cents: number;
	tax_cents: number;
	shipping_cents: number;
	total_cents: number;
	currency: string;
	tax_label: string;
	tax_rate_bps: number;
	shipping_label: string;
	applied_coupon: string | null;
	coupon_warning: string | null;
}

export interface QuotePreviewPayload {
	subtotal_cents: number;
	coupon_code: string | null;
	country: string;
	region: string;
	shipping_method: string;
}
