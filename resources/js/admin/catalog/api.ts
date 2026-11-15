import apiFetch from '@wordpress/api-fetch';
import type {
	Product,
	ProductFormPayload,
	ProductItemResponse,
	ProductListResponse,
	ProductStatus,
} from './types';

export async function fetchProducts( status?: ProductStatus | '' ): Promise< { items: Product[]; total: number } > {
	const query = status ? `?status=${ encodeURIComponent( status ) }&per_page=50` : '?per_page=50';
	const res = await apiFetch< ProductListResponse >( {
		path: `/ge-commerce/v1/products${ query }`,
	} );
	return res.data;
}

export async function saveProduct( payload: ProductFormPayload ): Promise< Product > {
	const isEdit = Boolean( payload.id );
	const res = await apiFetch< ProductItemResponse >( {
		path: isEdit ? `/ge-commerce/v1/products/${ payload.id }` : '/ge-commerce/v1/products',
		method: isEdit ? 'PUT' : 'POST',
		data: payload,
	} );
	return res.data;
}

export async function deleteProduct( id: number ): Promise< void > {
	await apiFetch( {
		path: `/ge-commerce/v1/products/${ id }`,
		method: 'DELETE',
	} );
}
