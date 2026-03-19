# Malaga Ecommerce Summit Demo

Version: 1.0.1

Demo plugin for WordPress and WooCommerce focused on exposing MCP capabilities through the WordPress Abilities API.

## What this plugin does

- Exposes selected WordPress core abilities for public MCP use.
- Registers the `mcp-commerce-demo` category for demo abilities.
- Publishes read-only WooCommerce abilities for common commerce queries.
- Reuses shared helpers for permissions, metadata, date validation, and response formatting.

## Included abilities

- `mcp-commerce-demo/get-customers-by-date`
- `mcp-commerce-demo/list-orders`
- `mcp-commerce-demo/list-order-notes`
- `mcp-commerce-demo/list-customers`
- `mcp-commerce-demo/list-coupons`
- `mcp-commerce-demo/list-refunds`

## Exposed core abilities

- `core/get-site-info`
- `core/get-user-info`
- `core/get-environment-info`

## Permissions

Commerce abilities can only be executed by users with `manage_woocommerce` or `manage_options` capabilities.

## Purpose

This plugin is intended for demos and MCP-connected assistants that need to query WooCommerce orders, customers, coupons, refunds, and order notes without relying on the WooCommerce admin UI.

## Example abilities and prompts

These are practical examples based on the abilities currently implemented in the plugin.

### Customers by date

Ability:

- `mcp-commerce-demo/get-customers-by-date`

Example prompts:

- `Use mcp-commerce-demo/get-customers-by-date and tell me which customers purchased on 2026-03-16 in a table with name, email, and user ID.`
- `Run mcp-commerce-demo/get-customers-by-date and summarize how many customers purchased on 2026-03-18.`

### Orders

Ability:

- `mcp-commerce-demo/list-orders`

Example prompts:

- `Use mcp-commerce-demo/list-orders and show the 10 most recent orders with ID, status, total, payment method, and billing city.`
- `Use mcp-commerce-demo/list-orders and summarize the orders currently being prepared.`
- `Use mcp-commerce-demo/list-orders and highlight which orders contain coupon codes and how much discount was applied.`

### Customers

Ability:

- `mcp-commerce-demo/list-customers`

Example prompts:

- `Use mcp-commerce-demo/list-customers and list 10 customers with name, email, city, phone, order count, and total spent.`
- `Use mcp-commerce-demo/list-customers and summarize the demo customers from Andalucia.`

### Coupons

Ability:

- `mcp-commerce-demo/list-coupons`

Example prompts:

- `Use mcp-commerce-demo/list-coupons and list all available coupons with code, type, amount, and usage limits.`
- `Use mcp-commerce-demo/list-coupons and tell me which coupons have already been used.`

### Refunds

Ability:

- `mcp-commerce-demo/list-refunds`

Example prompts:

- `Use mcp-commerce-demo/list-refunds and list the recent refunds with refund ID, original order, amount, reason, and date.`
- `Use mcp-commerce-demo/list-refunds and explain which refund is associated with order 29.`

### Order notes

Ability:

- `mcp-commerce-demo/list-order-notes`

Example prompts:

- `Use mcp-commerce-demo/list-order-notes and list the latest order notes with order ID, note type, and content.`
- `Use mcp-commerce-demo/list-order-notes and find notes related to refund incidents.`

### Recommended demo sequence

For a short live demo, these prompts work well:

1. `Use mcp-commerce-demo/list-orders and tell me which 5 orders are the most recent.`
2. `Use mcp-commerce-demo/list-orders and highlight the orders created on 2026-03-19 that used coupon codes.`
3. `Use mcp-commerce-demo/list-coupons and tell me which coupons have already been used and how many times.`
4. `Use mcp-commerce-demo/list-customers and summarize the active customers who already have orders.`
5. `Use mcp-commerce-demo/list-refunds and summarize recent refunds or compensations.`
6. `Use mcp-commerce-demo/list-order-notes and identify notes about delay, refund, or post-sales follow-up.`