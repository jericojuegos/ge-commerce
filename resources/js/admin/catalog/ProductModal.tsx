import { useState } from '@wordpress/element';
import {
	Button,
	Modal,
	Notice,
	SelectControl,
	TextareaControl,
	TextControl,
} from '@wordpress/components';
import type { Product, ProductFormPayload, ProductStatus } from './types';

interface SkuDraft {
	id?: number | null;
	sku_code: string;
	attributesText: string;
	price_cents: string;
	available_qty: string;
	reserved_qty: number;
}

interface ProductModalProps {
	product: Product | null;
	isSaving: boolean;
	errorMessage: string | null;
	onClose: () => void;
	onSubmit: ( payload: ProductFormPayload ) => void;
}

const slugify = ( value: string ): string =>
	value
		.toLowerCase()
		.trim()
		.replace( /[^a-z0-9]+/g, '-' )
		.replace( /^-+|-+$/g, '' );

const formatAttributes = ( attrs: Record< string, string > ): string =>
	Object.entries( attrs )
		.map( ( [ k, v ] ) => `${ k }=${ v }` )
		.join( ', ' );

const parseAttributes = ( text: string ): Record< string, string > => {
	const result: Record< string, string > = {};
	text.split( ',' )
		.map( ( part ) => part.trim() )
		.filter( Boolean )
		.forEach( ( pair ) => {
			const idx = pair.indexOf( '=' );
			if ( idx > 0 ) {
				const k = pair.slice( 0, idx ).trim();
				const v = pair.slice( idx + 1 ).trim();
				if ( k && v ) {
					result[ k ] = v;
				}
			}
		} );
	return result;
};

export const ProductModal = ( {
	product,
	isSaving,
	errorMessage,
	onClose,
	onSubmit,
}: ProductModalProps ): JSX.Element => {
	const [ title, setTitle ] = useState( product?.title ?? '' );
	const [ slug, setSlug ] = useState( product?.slug ?? '' );
	const [ slugTouched, setSlugTouched ] = useState( Boolean( product ) );
	const [ description, setDescription ] = useState( product?.description ?? '' );
	const [ status, setStatus ] = useState< ProductStatus >( product?.status ?? 'draft' );
	const [ skus, setSkus ] = useState< SkuDraft[] >( () =>
		product && product.skus.length > 0
			? product.skus.map( ( s ) => ( {
					id: s.id ?? null,
					sku_code: s.sku_code,
					attributesText: formatAttributes( s.attributes ),
					price_cents: String( s.price_cents ),
					available_qty: String( s.available_qty ),
					reserved_qty: s.reserved_qty ?? 0,
			  } ) )
			: [
					{
						id: null,
						sku_code: '',
						attributesText: 'size=M, color=Black',
						price_cents: '1999',
						available_qty: '10',
						reserved_qty: 0,
					},
			  ]
	);

	const handleTitleChange = ( nextTitle: string ): void => {
		setTitle( nextTitle );
		if ( ! slugTouched ) {
			setSlug( slugify( nextTitle ) );
		}
	};

	const updateSku = ( index: number, patch: Partial< SkuDraft > ): void => {
		setSkus( ( prev ) => prev.map( ( row, i ) => ( i === index ? { ...row, ...patch } : row ) ) );
	};

	const addSkuRow = (): void => {
		setSkus( ( prev ) => [
			...prev,
			{
				id: null,
				sku_code: '',
				attributesText: '',
				price_cents: '0',
				available_qty: '0',
				reserved_qty: 0,
			},
		] );
	};

	const removeSkuRow = ( index: number ): void => {
		setSkus( ( prev ) => prev.filter( ( _, i ) => i !== index ) );
	};

	const handleSubmit = ( e: React.FormEvent ): void => {
		e.preventDefault();
		onSubmit( {
			id: product?.id ?? null,
			title,
			slug: slug || slugify( title ),
			description,
			status,
			skus: skus.map( ( s ) => ( {
				id: s.id ?? null,
				sku_code: s.sku_code.trim(),
				attributes: parseAttributes( s.attributesText ),
				price_cents: Number.parseInt( s.price_cents, 10 ) || 0,
				available_qty: Number.parseInt( s.available_qty, 10 ) || 0,
				reserved_qty: s.reserved_qty,
			} ) ),
		} );
	};

	return (
		<Modal
			title={ product ? `Edit Product: ${ product.title }` : 'New Product & SKUs' }
			onRequestClose={ onClose }
			style={ { maxWidth: '720px', width: '100%' } }
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
						label="Title"
						value={ title }
						onChange={ handleTitleChange }
						required
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
					<TextControl
						label="Slug"
						value={ slug }
						onChange={ ( val ) => {
							setSlugTouched( true );
							setSlug( val );
						} }
						required
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</div>

				<div style={ { marginTop: '12px', display: 'grid', gridTemplateColumns: '200px 1fr', gap: '12px' } }>
					<SelectControl
						label="Status"
						value={ status }
						options={ [
							{ label: 'Draft', value: 'draft' },
							{ label: 'Published', value: 'published' },
							{ label: 'Archived', value: 'archived' },
						] }
						onChange={ ( val ) => setStatus( val as ProductStatus ) }
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
					<TextareaControl
						label="Description"
						value={ description }
						onChange={ setDescription }
						rows={ 2 }
						__nextHasNoMarginBottom
					/>
				</div>

				<div
					style={ {
						marginTop: '20px',
						paddingTop: '16px',
						borderTop: '1px solid #E2E8F0',
					} }
				>
					<div style={ { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '12px' } }>
						<strong style={ { color: '#0F172A' } }>SKUs &amp; Variable Attributes</strong>
						<Button variant="secondary" onClick={ addSkuRow } type="button">
							+ Add SKU
						</Button>
					</div>

					{ skus.map( ( sku, idx ) => (
						<div
							key={ idx }
							style={ {
								display: 'grid',
								gridTemplateColumns: '1.2fr 1.6fr 0.9fr 0.8fr auto',
								gap: '8px',
								alignItems: 'end',
								marginBottom: '10px',
								padding: '10px',
								backgroundColor: '#F8FAFC',
								border: '1px solid #E2E8F0',
								borderRadius: '4px',
							} }
						>
							<TextControl
								label="SKU Code"
								value={ sku.sku_code }
								onChange={ ( val ) => updateSku( idx, { sku_code: val } ) }
								required
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
							<TextControl
								label="Attributes (k=v, ...)"
								value={ sku.attributesText }
								onChange={ ( val ) => updateSku( idx, { attributesText: val } ) }
								placeholder="size=L, color=Black"
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
							<TextControl
								label="Price (cents)"
								type="number"
								min={ 0 }
								value={ sku.price_cents }
								onChange={ ( val ) => updateSku( idx, { price_cents: val } ) }
								required
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
							<TextControl
								label="Avail Qty"
								type="number"
								min={ 0 }
								value={ sku.available_qty }
								onChange={ ( val ) => updateSku( idx, { available_qty: val } ) }
								required
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
							<Button
								variant="tertiary"
								isDestructive
								type="button"
								onClick={ () => removeSkuRow( idx ) }
								disabled={ skus.length <= 1 }
							>
								Remove
							</Button>
						</div>
					) ) }
				</div>

				<div style={ { display: 'flex', justifyContent: 'flex-end', gap: '8px', marginTop: '20px' } }>
					<Button variant="secondary" type="button" onClick={ onClose } disabled={ isSaving }>
						Cancel
					</Button>
					<Button variant="primary" type="submit" isBusy={ isSaving } disabled={ isSaving }>
						{ isSaving ? 'Saving...' : 'Save Product' }
					</Button>
				</div>
			</form>
		</Modal>
	);
};
