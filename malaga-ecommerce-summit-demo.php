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

if ( ! function_exists( 'mesd_enable_mcp_integration' ) ) {
    /**
     * Activa la integración MCP en WooCommerce añadiendo la feature flag 'mcp_integration' al array de
     * características. Esto es necesario para que WooCommerce exponga las funcionalidades de MCP a través
     * de su API y permita que herramientas como Visual Studio Code puedan interactuar con WooCommerce 
     * usando MCP.
     *
     * @param array $features WooCommerce features array.
     * @return array
     */
    function mesd_enable_mcp_integration( $features ) {
        $features['mcp_integration'] = true;
        return $features;
    }
}

add_filter( 'woocommerce_features', 'mesd_enable_mcp_integration' );

/**
 * Activa esta opción solo en entornos de desarrollo, no en producción, ya que permite conexiones sin HTTPS.
 */
add_filter( 'woocommerce_mcp_allow_insecure_transport', '__return_true' );

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

    // Registrar la Ability personalizada.
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
                return current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' );
            },
            'meta' => [
                'annotations' => [
                    'readonly'   => true,
                    'destructive'=> false,
                    'idempotent' => true,
                ],
                'show_in_rest' => true,
                'mcp' => [
                    'public' => true,
                    'type'   => 'tool',
                ],
            ],
        ]
    );
} );

