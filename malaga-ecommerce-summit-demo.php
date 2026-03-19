<?php
/**
 * Plugin Name: Malaga Ecommerce Summit Demo
 * Description: Activa la integración MCP para WooCommerce (habilita la feature flag mcp_integration).
 * Version: 1.0.0
 * Author: Malaga Ecommerce Summit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Expone las abilities nativas de WordPress
add_filter( 'wp_register_ability_args', function( $args, $ability_name ) {
    $core_abilities = ['core/get-site-info', 'core/get-user-info', 'core/get-environment-info'];
    if ( in_array( $ability_name, $core_abilities, true ) ) {
        $args['meta']['mcp']['public'] = true;
    }
    return $args;
}, 10, 2 );


// Centraliza la comprobación de permisos para todas las abilities de la demo.
function mesd_can_manage_commerce() {
    return current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' );
}

// Devuelve el bloque de metadatos compartido por las abilities expuestas por MCP.
function mesd_get_ability_meta() {
    return [
        'annotations' => [
            'readonly'    => true,
            'destructive' => false,
            'idempotent'  => true,
        ],
        'show_in_rest' => true,
        'mcp'          => [
            'public' => true,
            'type'   => 'tool',
        ],
    ];
}

// Valida fechas de entrada con el formato YYYY-MM-DD antes de construir consultas.
function mesd_validate_date_string( $date, $field_name ) {
    if ( empty( $date ) ) {
        return null;
    }

    $datetime = DateTime::createFromFormat( 'Y-m-d', $date, wp_timezone() );

    if ( ! $datetime || $datetime->format( 'Y-m-d' ) !== $date ) {
        return new WP_Error(
            'invalid_date',
            sprintf( 'El campo %s debe tener formato YYYY-MM-DD.', $field_name )
        );
    }

    return $datetime;
}

// Normaliza un pedido de WooCommerce a una estructura compacta y estable para MCP.
function mesd_format_order_summary( $order ) {
    $shipping_methods = [];

    // Recoge cada línea de envío para que el consumidor vea método y coste sin reprocesar el pedido.
    foreach ( $order->get_items( 'shipping' ) as $shipping_item ) {
        $shipping_methods[] = [
            'method_id'    => (string) $shipping_item->get_method_id(),
            'method_title' => (string) $shipping_item->get_method_title(),
            'total'        => (float) $shipping_item->get_total(),
        ];
    }

    return [
        'order_id'              => $order->get_id(),
        'status'                => $order->get_status(),
        'currency'              => $order->get_currency(),
        'total'                 => (float) $order->get_total(),
        'customer_id'           => (int) $order->get_customer_id(),
        'customer_name'         => trim( $order->get_formatted_billing_full_name() ),
        'billing_email'         => (string) $order->get_billing_email(),
        'billing_city'          => (string) $order->get_billing_city(),
        'shipping_city'         => (string) $order->get_shipping_city(),
        'payment_method'        => (string) $order->get_payment_method(),
        'payment_method_title'  => (string) $order->get_payment_method_title(),
        'coupon_codes'          => array_values( $order->get_coupon_codes() ),
        'discount_total'        => (float) $order->get_discount_total(),
        'discount_tax'          => (float) $order->get_discount_tax(),
        'shipping_methods'      => $shipping_methods,
        'date_created'          => $order->get_date_created() ? $order->get_date_created()->date_i18n( 'Y-m-d H:i:s' ) : null,
        'date_paid'             => $order->get_date_paid() ? $order->get_date_paid()->date_i18n( 'Y-m-d H:i:s' ) : null,
    ];
}

// Convierte un cupón en un resumen legible para asistentes y paneles de demo.
function mesd_format_coupon_summary( $coupon ) {
    return [
        'coupon_id'             => $coupon->get_id(),
        'code'                  => $coupon->get_code(),
        'discount_type'         => $coupon->get_discount_type(),
        'amount'                => (float) $coupon->get_amount(),
        'description'           => (string) $coupon->get_description(),
        'usage_limit'           => null === $coupon->get_usage_limit() ? null : (int) $coupon->get_usage_limit(),
        'usage_count'           => (int) $coupon->get_usage_count(),
        'usage_limit_per_user'  => null === $coupon->get_usage_limit_per_user() ? null : (int) $coupon->get_usage_limit_per_user(),
        'minimum_amount'        => '' === $coupon->get_minimum_amount() ? null : (float) $coupon->get_minimum_amount(),
        'maximum_amount'        => '' === $coupon->get_maximum_amount() ? null : (float) $coupon->get_maximum_amount(),
        'free_shipping'         => (bool) $coupon->get_free_shipping(),
        'product_ids'           => array_map( 'intval', $coupon->get_product_ids() ),
        'date_expires'          => $coupon->get_date_expires() ? $coupon->get_date_expires()->date_i18n( 'Y-m-d H:i:s' ) : null,
    ];
}

// Enriquece cada refund con datos del pedido padre para que el contexto comercial quede completo.
function mesd_format_refund_summary( $refund ) {
    $parent_order = $refund->get_parent_id() ? wc_get_order( $refund->get_parent_id() ) : null;

    return [
        'refund_id'             => $refund->get_id(),
        'order_id'              => (int) $refund->get_parent_id(),
        'amount'                => (float) $refund->get_amount(),
        'reason'                => (string) $refund->get_reason(),
        'currency'              => $parent_order ? $parent_order->get_currency() : get_woocommerce_currency(),
        'customer_id'           => $parent_order ? (int) $parent_order->get_customer_id() : 0,
        'customer_name'         => $parent_order ? trim( $parent_order->get_formatted_billing_full_name() ) : '',
        'payment_method_title'  => $parent_order ? (string) $parent_order->get_payment_method_title() : '',
        'date_created'          => $refund->get_date_created() ? $refund->get_date_created()->date_i18n( 'Y-m-d H:i:s' ) : null,
    ];
}

// Agrupa en una sola respuesta los campos de perfil y métricas de compra del cliente.
function mesd_format_customer_summary( $user ) {
    $customer = new WC_Customer( $user->ID );

    return [
        'customer_id'        => (int) $user->ID,
        'username'           => (string) $user->user_login,
        'name'               => (string) $user->display_name,
        'email'              => (string) $user->user_email,
        'billing_company'    => (string) $customer->get_billing_company(),
        'billing_city'       => (string) $customer->get_billing_city(),
        'billing_state'      => (string) $customer->get_billing_state(),
        'billing_country'    => (string) $customer->get_billing_country(),
        'billing_phone'      => (string) $customer->get_billing_phone(),
        'shipping_city'      => (string) $customer->get_shipping_city(),
        'shipping_state'     => (string) $customer->get_shipping_state(),
        'shipping_country'   => (string) $customer->get_shipping_country(),
        'order_count'        => (int) wc_get_customer_order_count( $user->ID ),
        'total_spent'        => (float) wc_get_customer_total_spent( $user->ID ),
        'date_registered'    => $user->user_registered ? mysql2date( 'Y-m-d H:i:s', $user->user_registered, false ) : null,
    ];
}

// Reduce una nota de pedido a un formato apto para consultas MCP y soporte comercial.
function mesd_format_order_note_summary( $order_note ) {
    $comment = get_comment( $order_note->id );

    return [
        'note_id'        => (int) $order_note->id,
        'order_id'       => $comment ? (int) $comment->comment_post_ID : 0,
        'note'           => trim( wp_strip_all_tags( $order_note->content ) ),
        'customer_note'  => (bool) $order_note->customer_note,
        'added_by_user'  => (bool) $order_note->added_by_user,
        'author'         => $comment ? (string) $comment->comment_author : '',
        'date_created'   => $order_note->date_created ? $order_note->date_created->date_i18n( 'Y-m-d H:i:s' ) : null,
    ];
}

// Este hook se ejecuta cuando la Abilities API está lista para registrar categorías.
add_action( 'wp_abilities_api_categories_init', function() {
    if ( ! function_exists( 'wp_register_ability_category' ) ) {
        return;
    }

    wp_register_ability_category(
        'mcp-commerce-demo',
        [
            'label'       => 'MCP Commerce Demo',
            'description' => 'Abilities personalizadas para la demo de comercio con MCP.',
        ]
    );
} );

// Este hook se ejecuta cuando la Abilities API de WordPress está lista para registrar nuevas abilities.
add_action( 'wp_abilities_api_init', function() {
    // Verificar que la función de Abilities API existe (por si el API no estuviera disponible).
    if ( ! function_exists( 'wp_register_ability' ) ) {
        return;
    }

    // Ability de ejemplo para localizar clientes que compraron en una fecha concreta.
    wp_register_ability(
        'mcp-commerce-demo/get-customers-by-date',  // Nombre único: "namespace/ability-name"
        [
            'label'       => 'Get Customers by Purchase Date',
            'description' => 'Obtiene una lista de clientes que realizaron compras en una fecha dada (usando pedidos de WooCommerce).',
            'category'    => 'mcp-commerce-demo',
            'input_schema'  => [
                'type'       => 'object',
                'properties' => [
                    'date' => [
                        'type'        => 'string',
                        'description' => 'Fecha de búsqueda (YYYY-MM-DD)',
                    ],
                ],
                'required' => ['date']  // La fecha es obligatoria
            ],
            'output_schema' => [
                'type'  => 'array',
                'items' => [
                    'type'       => 'object',
                    'properties' => [
                        'user_id' => ['type' => 'integer'],
                        'name'    => ['type' => 'string'],
                        'email'   => ['type' => 'string'],
                    ]
                ],
                'description' => 'Lista de clientes (ID, nombre, email) que compraron en la fecha indicada.'
            ],
            'execute_callback' => function( $input ) {
                // Asegurar que WooCommerce está activo
                if ( ! function_exists( 'wc_get_orders' ) ) {
                    return new WP_Error( 'no_woocommerce', 'WooCommerce no está activo.' );
                }

                $date = sanitize_text_field( $input['date'] );
                if ( empty( $date ) ) {
                    return new WP_Error( 'missing_date', 'No se proporcionó una fecha.' );
                }

                $validated_date = mesd_validate_date_string( $date, 'date' );

                if ( is_wp_error( $validated_date ) ) {
                    return $validated_date;
                }

                // Definir rango de fecha (todo el día especificado)
                $start_datetime = new DateTime( $date . ' 00:00:00', wp_timezone() );
                $end_datetime   = new DateTime( $date . ' 23:59:59', wp_timezone() );

                // Obtener pedidos cuyo date_created caiga en ese rango de fechas
                $orders = wc_get_orders([
                    'limit'        => -1,  // sin límite, obtener todos los pedidos en ese día
                    'status'       => array('wc-completed', 'wc-processing', 'wc-on-hold', 'wc-refunded'),  // estados de pedido considerados "compra"
                    'date_created' => $start_datetime->format('Y-m-d H:i:s') . '...' . $end_datetime->format('Y-m-d H:i:s'),
                ]);

                $customers = [];
                foreach ( $orders as $order ) {
                    // Obtener ID de usuario (cliente registrado) o 0 si invitado
                    $user_id = $order->get_user_id();
                    if ( $user_id ) {
                        $user = get_userdata( $user_id );
                        if ( $user ) {
                            $name = $user->display_name;
                            $email = $user->user_email;
                        } else {
                            // Si por alguna razón el usuario no existe, saltar
                            continue;
                        }
                    } else {
                        // Pedidos de invitados: usar nombre y email de facturación
                        $name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
                        $email = $order->get_billing_email();
                        $user_id = 0;
                    }

                    // Evitar duplicados (varios pedidos del mismo cliente en el día)
                    $key = $user_id ? $user_id : $email;
                    if ( isset( $customers[ $key ] ) ) {
                        continue;
                    }

                    $customers[ $key ] = [
                        'user_id' => $user_id,
                        'name'    => $name,
                        'email'   => $email,
                    ];
                }

                // Devolver los clientes como arreglo de objetos (valores)
                return array_values( $customers );
            },
            'permission_callback' => function() {
                // Solo permitir a administradores o gestores de tienda ejecutar esta ability
                return mesd_can_manage_commerce();
            },
            'meta' => mesd_get_ability_meta(),
        ]
    );

    // Ability de lectura para listar pedidos con filtros básicos de explotación comercial.
    wp_register_ability(
        'mcp-commerce-demo/list-orders',
        [
            'label'         => 'List WooCommerce Orders',
            'description'   => 'Lista pedidos de WooCommerce con filtros básicos de estado, cliente y rango de fechas.',
            'category'      => 'mcp-commerce-demo',
            'input_schema'  => [
                'type'       => 'object',
                'properties' => [
                    'limit' => [
                        'type'        => 'integer',
                        'description' => 'Número máximo de pedidos a devolver. Máximo 50.',
                        'default'     => 10,
                    ],
                    'status' => [
                        'type'        => 'string',
                        'description' => 'Estado del pedido, por ejemplo completed, processing, on-hold, pending, cancelled o failed.',
                    ],
                    'customer_id' => [
                        'type'        => 'integer',
                        'description' => 'Filtra por ID de cliente.',
                    ],
                    'date_from' => [
                        'type'        => 'string',
                        'description' => 'Fecha inicial en formato YYYY-MM-DD.',
                    ],
                    'date_to' => [
                        'type'        => 'string',
                        'description' => 'Fecha final en formato YYYY-MM-DD.',
                    ],
                ],
            ],
            'output_schema' => [
                'type'        => 'array',
                'description' => 'Lista de pedidos con datos de cliente, pago, envío y fechas.',
                'items'       => [
                    'type'       => 'object',
                    'properties' => [
                        'order_id'             => [ 'type' => 'integer' ],
                        'status'               => [ 'type' => 'string' ],
                        'currency'             => [ 'type' => 'string' ],
                        'total'                => [ 'type' => 'number' ],
                        'customer_id'          => [ 'type' => 'integer' ],
                        'customer_name'        => [ 'type' => 'string' ],
                        'billing_email'        => [ 'type' => 'string' ],
                        'billing_city'         => [ 'type' => 'string' ],
                        'shipping_city'        => [ 'type' => 'string' ],
                        'payment_method'       => [ 'type' => 'string' ],
                        'payment_method_title' => [ 'type' => 'string' ],
                        'coupon_codes'         => [
                            'type'  => 'array',
                            'items' => [ 'type' => 'string' ],
                        ],
                        'discount_total'       => [ 'type' => 'number' ],
                        'discount_tax'         => [ 'type' => 'number' ],
                        'shipping_methods'     => [
                            'type'  => 'array',
                            'items' => [
                                'type'       => 'object',
                                'properties' => [
                                    'method_id'    => [ 'type' => 'string' ],
                                    'method_title' => [ 'type' => 'string' ],
                                    'total'        => [ 'type' => 'number' ],
                                ],
                            ],
                        ],
                        'date_created'         => [ 'type' => [ 'string', 'null' ] ],
                        'date_paid'            => [ 'type' => [ 'string', 'null' ] ],
                    ],
                ],
            ],
            'execute_callback' => function( $input ) {
                if ( ! function_exists( 'wc_get_orders' ) ) {
                    return new WP_Error( 'no_woocommerce', 'WooCommerce no está activo.' );
                }

                // Se limita el tamaño de respuesta para mantener las respuestas MCP manejables.
                $limit = isset( $input['limit'] ) ? (int) $input['limit'] : 10;
                $limit = max( 1, min( 50, $limit ) );

                $query_args = [
                    'type'    => 'shop_order',
                    'limit'   => $limit,
                    'orderby' => 'date',
                    'order'   => 'DESC',
                ];

                if ( ! empty( $input['status'] ) ) {
                    $query_args['status'] = sanitize_key( $input['status'] );
                }

                if ( ! empty( $input['customer_id'] ) ) {
                    $query_args['customer_id'] = (int) $input['customer_id'];
                }

                $date_from = isset( $input['date_from'] ) ? sanitize_text_field( $input['date_from'] ) : '';
                $date_to   = isset( $input['date_to'] ) ? sanitize_text_field( $input['date_to'] ) : '';

                $validated_date_from = mesd_validate_date_string( $date_from, 'date_from' );
                $validated_date_to   = mesd_validate_date_string( $date_to, 'date_to' );

                if ( is_wp_error( $validated_date_from ) ) {
                    return $validated_date_from;
                }

                if ( is_wp_error( $validated_date_to ) ) {
                    return $validated_date_to;
                }

                if ( $date_from || $date_to ) {
                    $range_from = $date_from ? $date_from . ' 00:00:00' : '1970-01-01 00:00:00';
                    $range_to   = $date_to ? $date_to . ' 23:59:59' : gmdate( 'Y-m-d H:i:s' );

                    // WooCommerce acepta rangos de fechas en formato inicio...fin.
                    $query_args['date_created'] = $range_from . '...' . $range_to;
                }

                $orders = wc_get_orders( $query_args );

                return array_map( 'mesd_format_order_summary', $orders );
            },
            'permission_callback' => function() {
                return mesd_can_manage_commerce();
            },
            'meta' => mesd_get_ability_meta(),
        ]
    );

    // Ability para consultar notas de pedido y cruzarlas con incidencias o seguimiento comercial.
    wp_register_ability(
        'mcp-commerce-demo/list-order-notes',
        [
            'label'         => 'List WooCommerce Order Notes',
            'description'   => 'Lista notas de pedido de WooCommerce, internas o visibles para el cliente, con filtros básicos.',
            'category'      => 'mcp-commerce-demo',
            'input_schema'  => [
                'type'       => 'object',
                'properties' => [
                    'limit' => [
                        'type'        => 'integer',
                        'description' => 'Número máximo de notas a devolver. Máximo 50.',
                        'default'     => 20,
                    ],
                    'order_id' => [
                        'type'        => 'integer',
                        'description' => 'Filtra por pedido concreto.',
                    ],
                    'note_type' => [
                        'type'        => 'string',
                        'description' => 'Tipo de nota: all, internal o customer.',
                    ],
                    'search' => [
                        'type'        => 'string',
                        'description' => 'Texto a buscar dentro del contenido de la nota.',
                    ],
                ],
            ],
            'output_schema' => [
                'type'        => 'array',
                'description' => 'Lista de notas de pedido con autor, tipo y fecha.',
                'items'       => [
                    'type'       => 'object',
                    'properties' => [
                        'note_id'       => [ 'type' => 'integer' ],
                        'order_id'      => [ 'type' => 'integer' ],
                        'note'          => [ 'type' => 'string' ],
                        'customer_note' => [ 'type' => 'boolean' ],
                        'added_by_user' => [ 'type' => 'boolean' ],
                        'author'        => [ 'type' => 'string' ],
                        'date_created'  => [ 'type' => [ 'string', 'null' ] ],
                    ],
                ],
            ],
            'execute_callback' => function( $input ) {
                if ( ! function_exists( 'wc_get_order_notes' ) ) {
                    return new WP_Error( 'no_woocommerce', 'WooCommerce no está activo.' );
                }

                $limit = isset( $input['limit'] ) ? (int) $input['limit'] : 20;
                $limit = max( 1, min( 50, $limit ) );

                $query_args = [
                    'limit' => $limit,
                    'order' => 'DESC',
                    'type'  => 'all',
                ];

                if ( ! empty( $input['order_id'] ) ) {
                    $query_args['order_id'] = (int) $input['order_id'];
                }

                if ( ! empty( $input['note_type'] ) ) {
                    $note_type = sanitize_key( $input['note_type'] );
                    if ( in_array( $note_type, [ 'all', 'internal', 'customer' ], true ) ) {
                        $query_args['type'] = $note_type;
                    }
                }

                $order_notes = wc_get_order_notes( $query_args );
                $search      = isset( $input['search'] ) ? sanitize_text_field( $input['search'] ) : '';
                $results     = [];

                foreach ( $order_notes as $order_note ) {
                    $summary = mesd_format_order_note_summary( $order_note );

                    if ( $search && false === stripos( $summary['note'], $search ) ) {
                        continue;
                    }

                    $results[] = $summary;
                }

                return $results;
            },
            'permission_callback' => function() {
                return mesd_can_manage_commerce();
            },
            'meta' => mesd_get_ability_meta(),
        ]
    );

    // Ability para consultar clientes registrados con datos de perfil y compra agregados.
    wp_register_ability(
        'mcp-commerce-demo/list-customers',
        [
            'label'         => 'List WooCommerce Customers',
            'description'   => 'Lista clientes registrados con datos de facturación, ubicación y métricas básicas de compra.',
            'category'      => 'mcp-commerce-demo',
            'input_schema'  => [
                'type'       => 'object',
                'properties' => [
                    'limit' => [
                        'type'        => 'integer',
                        'description' => 'Número máximo de clientes a devolver. Máximo 50.',
                        'default'     => 20,
                    ],
                    'search' => [
                        'type'        => 'string',
                        'description' => 'Texto para buscar por nombre, usuario o email.',
                    ],
                    'city' => [
                        'type'        => 'string',
                        'description' => 'Filtra por ciudad de facturación.',
                    ],
                    'state' => [
                        'type'        => 'string',
                        'description' => 'Filtra por provincia o región de facturación.',
                    ],
                    'has_orders' => [
                        'type'        => 'boolean',
                        'description' => 'Si es true, solo devuelve clientes con al menos un pedido.',
                    ],
                ],
            ],
            'output_schema' => [
                'type'        => 'array',
                'description' => 'Lista de clientes con localización y métricas comerciales básicas.',
                'items'       => [
                    'type'       => 'object',
                    'properties' => [
                        'customer_id'      => [ 'type' => 'integer' ],
                        'username'         => [ 'type' => 'string' ],
                        'name'             => [ 'type' => 'string' ],
                        'email'            => [ 'type' => 'string' ],
                        'billing_company'  => [ 'type' => 'string' ],
                        'billing_city'     => [ 'type' => 'string' ],
                        'billing_state'    => [ 'type' => 'string' ],
                        'billing_country'  => [ 'type' => 'string' ],
                        'billing_phone'    => [ 'type' => 'string' ],
                        'shipping_city'    => [ 'type' => 'string' ],
                        'shipping_state'   => [ 'type' => 'string' ],
                        'shipping_country' => [ 'type' => 'string' ],
                        'order_count'      => [ 'type' => 'integer' ],
                        'total_spent'      => [ 'type' => 'number' ],
                        'date_registered'  => [ 'type' => [ 'string', 'null' ] ],
                    ],
                ],
            ],
            'execute_callback' => function( $input ) {
                if ( ! class_exists( 'WC_Customer' ) ) {
                    return new WP_Error( 'no_woocommerce', 'WooCommerce no está activo.' );
                }

                // Se recorta el resultado máximo para que el agente no reciba listados excesivos.
                $limit = isset( $input['limit'] ) ? (int) $input['limit'] : 20;
                $limit = max( 1, min( 50, $limit ) );

                $user_query_args = [
                    'role'    => 'customer',
                    'orderby' => 'registered',
                    'order'   => 'DESC',
                    'number'  => 200,
                    'search'  => '*',
                ];

                if ( ! empty( $input['search'] ) ) {
                    $search = sanitize_text_field( $input['search'] );
                    $user_query_args['search'] = '*' . $search . '*';
                    $user_query_args['search_columns'] = [ 'user_login', 'user_email', 'display_name' ];
                }

                $users = get_users( $user_query_args );
                $city_filter = isset( $input['city'] ) ? sanitize_text_field( $input['city'] ) : '';
                $state_filter = isset( $input['state'] ) ? sanitize_text_field( $input['state'] ) : '';
                $has_orders_filter = array_key_exists( 'has_orders', $input ) ? (bool) $input['has_orders'] : null;

                $customers = [];

                foreach ( $users as $user ) {
                    $summary = mesd_format_customer_summary( $user );

                    // Los filtros se aplican después de construir el resumen para reutilizar una sola forma de salida.
                    if ( $city_filter && 0 !== strcasecmp( $summary['billing_city'], $city_filter ) ) {
                        continue;
                    }

                    if ( $state_filter && 0 !== strcasecmp( $summary['billing_state'], $state_filter ) ) {
                        continue;
                    }

                    if ( null !== $has_orders_filter ) {
                        $has_orders = $summary['order_count'] > 0;

                        if ( $has_orders_filter !== $has_orders ) {
                            continue;
                        }
                    }

                    $customers[] = $summary;

                    if ( count( $customers ) >= $limit ) {
                        break;
                    }
                }

                return $customers;
            },
            'permission_callback' => function() {
                return mesd_can_manage_commerce();
            },
            'meta' => mesd_get_ability_meta(),
        ]
    );

    // Ability para exponer los cupones demo y sus restricciones sin necesidad de ir al admin.
    wp_register_ability(
        'mcp-commerce-demo/list-coupons',
        [
            'label'         => 'List WooCommerce Coupons',
            'description'   => 'Lista cupones de WooCommerce con sus restricciones y uso actual.',
            'category'      => 'mcp-commerce-demo',
            'input_schema'  => [
                'type'       => 'object',
                'properties' => [
                    'limit' => [
                        'type'        => 'integer',
                        'description' => 'Número máximo de cupones a devolver. Máximo 50.',
                        'default'     => 20,
                    ],
                    'search' => [
                        'type'        => 'string',
                        'description' => 'Texto para buscar por código o descripción.',
                    ],
                ],
            ],
            'output_schema' => [
                'type'        => 'array',
                'description' => 'Lista de cupones con tipo, importe, límites y productos asociados.',
                'items'       => [
                    'type'       => 'object',
                    'properties' => [
                        'coupon_id'            => [ 'type' => 'integer' ],
                        'code'                 => [ 'type' => 'string' ],
                        'discount_type'        => [ 'type' => 'string' ],
                        'amount'               => [ 'type' => 'number' ],
                        'description'          => [ 'type' => 'string' ],
                        'usage_limit'          => [ 'type' => [ 'integer', 'null' ] ],
                        'usage_count'          => [ 'type' => 'integer' ],
                        'usage_limit_per_user' => [ 'type' => [ 'integer', 'null' ] ],
                        'minimum_amount'       => [ 'type' => [ 'number', 'null' ] ],
                        'maximum_amount'       => [ 'type' => [ 'number', 'null' ] ],
                        'free_shipping'        => [ 'type' => 'boolean' ],
                        'product_ids'          => [
                            'type'  => 'array',
                            'items' => [ 'type' => 'integer' ],
                        ],
                        'date_expires'         => [ 'type' => [ 'string', 'null' ] ],
                    ],
                ],
            ],
            'execute_callback' => function( $input ) {
                if ( ! class_exists( 'WC_Coupon' ) ) {
                    return new WP_Error( 'no_woocommerce', 'WooCommerce no está activo.' );
                }

                // Se consulta sobre el CPT nativo de cupones para mantener compatibilidad con WooCommerce.
                $limit = isset( $input['limit'] ) ? (int) $input['limit'] : 20;
                $limit = max( 1, min( 50, $limit ) );

                $coupon_posts = get_posts(
                    [
                        'post_type'      => 'shop_coupon',
                        'post_status'    => [ 'publish', 'draft', 'pending', 'private' ],
                        'posts_per_page' => $limit,
                        'orderby'        => 'date',
                        'order'          => 'DESC',
                        's'              => isset( $input['search'] ) ? sanitize_text_field( $input['search'] ) : '',
                    ]
                );

                $coupons = [];

                foreach ( $coupon_posts as $coupon_post ) {
                    // Cada entrada se normaliza con el mismo esquema de salida para la ability.
                    $coupons[] = mesd_format_coupon_summary( new WC_Coupon( $coupon_post->ID ) );
                }

                return $coupons;
            },
            'permission_callback' => function() {
                return mesd_can_manage_commerce();
            },
            'meta' => mesd_get_ability_meta(),
        ]
    );

    // Ability de lectura para revisar compensaciones y devoluciones realizadas en la tienda.
    wp_register_ability(
        'mcp-commerce-demo/list-refunds',
        [
            'label'         => 'List WooCommerce Refunds',
            'description'   => 'Lista reembolsos de WooCommerce con pedido original, cliente y motivo.',
            'category'      => 'mcp-commerce-demo',
            'input_schema'  => [
                'type'       => 'object',
                'properties' => [
                    'limit' => [
                        'type'        => 'integer',
                        'description' => 'Número máximo de reembolsos a devolver. Máximo 50.',
                        'default'     => 20,
                    ],
                    'order_id' => [
                        'type'        => 'integer',
                        'description' => 'Filtra reembolsos de un pedido concreto.',
                    ],
                    'date_from' => [
                        'type'        => 'string',
                        'description' => 'Fecha inicial en formato YYYY-MM-DD.',
                    ],
                    'date_to' => [
                        'type'        => 'string',
                        'description' => 'Fecha final en formato YYYY-MM-DD.',
                    ],
                ],
            ],
            'output_schema' => [
                'type'        => 'array',
                'description' => 'Lista de reembolsos con importe, motivo, pedido y cliente relacionados.',
                'items'       => [
                    'type'       => 'object',
                    'properties' => [
                        'refund_id'            => [ 'type' => 'integer' ],
                        'order_id'             => [ 'type' => 'integer' ],
                        'amount'               => [ 'type' => 'number' ],
                        'reason'               => [ 'type' => 'string' ],
                        'currency'             => [ 'type' => 'string' ],
                        'customer_id'          => [ 'type' => 'integer' ],
                        'customer_name'        => [ 'type' => 'string' ],
                        'payment_method_title' => [ 'type' => 'string' ],
                        'date_created'         => [ 'type' => [ 'string', 'null' ] ],
                    ],
                ],
            ],
            'execute_callback' => function( $input ) {
                if ( ! function_exists( 'wc_get_orders' ) ) {
                    return new WP_Error( 'no_woocommerce', 'WooCommerce no está activo.' );
                }

                // Los refunds se obtienen como un tipo de pedido específico dentro de WooCommerce.
                $limit = isset( $input['limit'] ) ? (int) $input['limit'] : 20;
                $limit = max( 1, min( 50, $limit ) );

                $query_args = [
                    'type'    => 'shop_order_refund',
                    'limit'   => $limit,
                    'orderby' => 'date',
                    'order'   => 'DESC',
                ];

                if ( ! empty( $input['order_id'] ) ) {
                    $query_args['parent'] = (int) $input['order_id'];
                }

                $date_from = isset( $input['date_from'] ) ? sanitize_text_field( $input['date_from'] ) : '';
                $date_to   = isset( $input['date_to'] ) ? sanitize_text_field( $input['date_to'] ) : '';

                $validated_date_from = mesd_validate_date_string( $date_from, 'date_from' );
                $validated_date_to   = mesd_validate_date_string( $date_to, 'date_to' );

                if ( is_wp_error( $validated_date_from ) ) {
                    return $validated_date_from;
                }

                if ( is_wp_error( $validated_date_to ) ) {
                    return $validated_date_to;
                }

                if ( $date_from || $date_to ) {
                    $range_from = $date_from ? $date_from . ' 00:00:00' : '1970-01-01 00:00:00';
                    $range_to   = $date_to ? $date_to . ' 23:59:59' : gmdate( 'Y-m-d H:i:s' );

                    // Se reutiliza el mismo formato de rango temporal que en los pedidos.
                    $query_args['date_created'] = $range_from . '...' . $range_to;
                }

                $refunds = wc_get_orders( $query_args );

                return array_map( 'mesd_format_refund_summary', $refunds );
            },
            'permission_callback' => function() {
                return mesd_can_manage_commerce();
            },
            'meta' => mesd_get_ability_meta(),
        ]
    );
} );

