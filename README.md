# Malaga Ecommerce Summit Demo

Version: 1.0.1

Plugin de demostracion para WordPress y WooCommerce orientado a exponer capacidades MCP mediante Abilities API.

## Que hace

- Expone abilities core de WordPress para uso publico por MCP.
- Registra la categoria `mcp-commerce-demo` para abilities de demo.
- Publica abilities de solo lectura para consultar datos comerciales de WooCommerce.
- Reutiliza helpers comunes de permisos, metadatos, validacion de fechas y formateo de respuestas.

## Abilities incluidas

- `mcp-commerce-demo/get-customers-by-date`
- `mcp-commerce-demo/list-orders`
- `mcp-commerce-demo/list-order-notes`
- `mcp-commerce-demo/list-customers`
- `mcp-commerce-demo/list-coupons`
- `mcp-commerce-demo/list-refunds`

## Abilities core expuestas

- `core/get-site-info`
- `core/get-user-info`
- `core/get-environment-info`

## Permisos

Las abilities de comercio solo pueden ejecutarse por usuarios con `manage_woocommerce` o `manage_options`.

## Objetivo

Este plugin sirve para demos y asistentes conectados por MCP que necesiten consultar pedidos, clientes, cupones, reembolsos y notas de pedido sin usar el administrador de WooCommerce.