<?php

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Manages product category columns
 *
 * Here all product category columns defined and managed.
 *
 * @version		1.0.0
 * @package		ecommerce-product-catalog/includes
 * @author 		impleCode
 */
add_action( 'al_product-cat_add_form', 'add_product_category_helper' );

/**
 * Adds info box on product category page
 *
 */
function add_product_category_helper() {
	doc_helper( __( 'category', 'ecommerce-product-catalog' ), 'product-categories', 'left' );
}

add_action( 'al_product-cat_edit_form_fields', 'product_category_edit_form_fields' );
add_action( 'al_product-cat_edit_form', 'product_category_edit_form' );
add_action( 'al_product-cat_add_form_fields', 'product_category_edit_form_fields' );
add_action( 'al_product-cat_add_form', 'product_category_edit_form' );

function product_category_edit_form() {
	?>
	<script type="text/javascript">
		jQuery( document ).ready( function () {
			jQuery( '#edittag' ).attr( "enctype", "multipart/form-data" ).attr( "encoding", "multipart/form-data" );
		} );
	</script>
	<?php

}

add_action( 'edit_al_product-cat', 'save_product_cat_image' );
add_action( 'create_al_product-cat', 'save_product_cat_image' );

/**
 * Saves category image assignment option
 *
 * @param int $term_id
 */
function save_product_cat_image( $term_id ) {
	if ( isset( $_POST[ 'product_cat_image' ] ) ) {
		if ( function_exists( 'update_term_meta' ) ) {
			update_term_meta( $term_id, 'thumbnail_id', intval( $_POST[ 'product_cat_image' ] ) );
		} else {
			update_option( 'al_product_cat_image_' . $term_id, intval( $_POST[ 'product_cat_image' ] ) );
		}
		do_action( 'ic_save_product_category', $term_id );
	}
}

add_action( 'delete_al_product-cat', 'delete_product_cat_image' );

/**
 * Deletes category image assignmend from database
 *
 * @param int $term_id
 */
function delete_product_cat_image( $term_id ) {
	delete_option( 'al_product_cat_image_' . $term_id );
	if ( function_exists( 'delete_term_meta' ) ) {
		delete_term_meta( $term_id, 'thumbnail_id' );
	}
}

function product_category_edit_form_fields( $field ) {
	if ( isset( $field->term_id ) ) {
		$cat_img_src = get_product_category_image_id( $field->term_id );
	} else {
		$cat_img_src = '';
	}
	implecode_upload_image( __( 'Select Category Image', 'ecommerce-product-catalog' ), 'product_cat_image', $cat_img_src, null, 'id' );
	do_action( 'ic_product_cat_fields', $field );
}

add_filter( 'manage_edit-al_product-cat_columns', 'product_cat_columns' );

/**
 * Adds product category specific column names
 * @param string $product_columns
 * @return array
 */
function product_cat_columns( $product_columns ) {
	$product_columns				 = array_reverse( $product_columns );
	$temp							 = $product_columns[ 'cb' ];
	unset( $product_columns[ 'cb' ] );
	unset( $product_columns[ 'slug' ] );
	$product_columns[ 'img' ]		 = __( 'Image', 'ecommerce-product-catalog' );
	$product_columns[ 'id' ]		 = __( 'ID', 'ecommerce-product-catalog' );
	$product_columns[ 'cb' ]		 = $temp;
	$product_columns				 = array_reverse( $product_columns );
	$product_columns[ 'shortcode' ]	 = __( 'Shortcode', 'ecommerce-product-catalog' );
	return $product_columns;
}

add_action( 'manage_al_product-cat_custom_column', 'manage_product_category_columns', 10, 3 );

/**
 * Adds product category specific column values
 *
 * @param type $depr
 * @param string $column_name
 * @param int $term_id
 */
function manage_product_category_columns( $depr, $column_name, $term_id ) {
	switch ( $column_name ) {
		case 'img':
			$attachment_id = get_product_category_image_id( $term_id );
			echo wp_get_attachment_image( $attachment_id, array( 40, 40 ) );
			break;
		case 'id':
			echo $term_id;
			break;

		case 'shortcode':
			echo '[show_products category="' . $term_id . '"][show_categories include="' . $term_id . '"]';
			break;

		default:
			break;
	}
}

/**
 * Returns category image ID
 *
 * @param int $cat_id
 * @return int
 */
function get_product_category_image_id( $cat_id ) {
	if ( function_exists( 'get_term_meta' ) ) {
		$image_id = get_term_meta( $cat_id, 'thumbnail_id', true );
	}
	if ( empty( $image_id ) ) {
		$image_id = get_option( 'al_product_cat_image_' . $cat_id );
		if ( !empty( $image_id ) ) {
			update_term_meta( $cat_id, 'thumbnail_id', intval( $image_id ) );
			delete_option( 'al_product_cat_image_' . $cat_id );
		}
	}
	return apply_filters( 'ic_category_image_id', $image_id, $cat_id );
}
