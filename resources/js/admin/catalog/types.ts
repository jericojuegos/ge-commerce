export type ProductStatus = 'draft' | 'published' | 'archived';

export interface Sku {
	id?: number | null;
	product_id?: number | null;
	sku_code: string;
	attributes: Record< string, string >;
	price_cents: number;
	currency?: string;
	available_qty: number;
	reserved_qty?: number;
}

export interface Product {
	id: number;
	slug: string;
	title: string;
	description: string;
	status: ProductStatus;
	skus: Sku[];
	created_at?: string | null;
	updated_at?: string | null;
}

export interface ProductFormPayload {
	id?: number | null;
	slug: string;
	title: string;
	description: string;
	status: ProductStatus;
	skus: Array< {
		id?: number | null;
		sku_code: string;
		attributes: Record< string, string >;
		price_cents: number;
		available_qty: number;
		reserved_qty?: number;
	} >;
}

export interface ProductListResponse {
	ok: boolean;
	data: {
		items: Product[];
		total: number;
	};
}

export interface ProductItemResponse {
	ok: boolean;
	data: Product;
}
