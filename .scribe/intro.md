# Introduction

Comprehensive SaaS & realtime backend API (Auth, Roles, Chat, Stripe Billing, Subscriptions, Payment Methods, Invoices, File Upload, Reverb events).

<aside>
    <strong>Base URL</strong>: <code>http://localhost:81</code>
</aside>

**Welcome to the Boilerplate API.**

This backend covers: **Authentication**, **Profile**, **Roles & Permissions**, **Realtime Chat**, **Stripe One‑time & Subscription Billing**, **Invoices & Refunds**, **Payment Methods**, **Activity Log**, and **File Uploads**.

### Auth Quick Start
1. Register/Login to obtain a Bearer token.
2. Add header: `Authorization: Bearer YOUR_TOKEN`.
3. Websocket private/presence auth uses the same token at `/broadcasting/auth`.

### Response Examples
Generated samples are illustrative; validate business rules in production.

> Tip: Use the Postman collection or OpenAPI spec for faster integration.

