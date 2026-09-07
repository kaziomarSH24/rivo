<h1 align="center">🚀 Laravel 12 Boilerplate</h1>
<p align="center">
Production-ready Laravel backend with Authentication, Roles & Permissions, Activity Log, Real-time Chat (Reverb), Stripe Billing, Queues, Scheduler, Redis, Firebase FCM, and Clean Architecture.
</p>

---

## ✨ Features

### 🔐 Authentication & Authorization

-   Complete auth flow (Register, Login, Logout, Email Verification, Password Reset via OTP)
-   User Profile (View & Update)
-   Role & Permission management (spatie/laravel-permission)
-   API Token authentication (Laravel Sanctum)
-   Activity logging (spatie/laravel-activitylog)

### 💬 Real-time Chat

-   Conversations & Messages (1-on-1 and Groups)
-   Typing indicators & Read receipts
-   Real-time updates via Laravel Reverb WebSocket
-   Group management (Add/Remove members, Promote/Demote)

### 💳 Stripe Integration

-   **One-Time Payments**: Checkout Sessions & Payment Intents
-   **Subscriptions**: Create, Show, Swap, Cancel, Resume
-   **Payment Methods**: Add, List, Set Default, Delete
-   **Billing Portal**: Redirect to Stripe hosted portal
-   **Refunds**: Webhook-driven refund handling
-   **Invoices**: List & Download PDF invoices
-   **Webhooks**: Automatic payment lifecycle sync via StripeEventListener

### 🛠️ Developer Experience

-   **Clean Architecture**: Routes → Controllers → Services → Models
-   **Reusable Traits**: FileUpload, Cacheable, ManagesData
-   **Advanced Query Builder**: Filtering, sorting, includes (spatie/laravel-query-builder)
-   **Background Jobs**: Queue worker & Scheduler in separate containers
-   **Image Processing**: Resize & WebP conversion
-   **Tag-based Caching**: Per-model cache invalidation
-   **Firebase Push Notifications**: FCM integration ready

### 🐳 Docker Ready

-   **PHP-FPM** (8.2+)
-   **Nginx** (App server + Reverse proxy with SSL support)
-   **MySQL** 8.0
-   **Redis** 7 (Cache, Queue, Broadcasting)
-   **Queue Worker** (dedicated container)
-   **Scheduler** (dedicated container)
-   **Reverb** (WebSocket server)
-   **phpMyAdmin** (Database management)

---

## 📦 Key Packages

| Package                        | Purpose                                                   |
| ------------------------------ | --------------------------------------------------------- |
| `laravel/framework` (v12)      | Core framework                                            |
| `laravel/sanctum`              | API token authentication                                  |
| `laravel/cashier`              | Stripe billing (subscriptions, invoices, payment methods) |
| `laravel/reverb`               | Real-time WebSocket server                                |
| `spatie/laravel-permission`    | Roles & permissions                                       |
| `spatie/laravel-activitylog`   | Audit logging                                             |
| `spatie/laravel-query-builder` | Advanced API filtering/sorting                            |
| `dompdf/dompdf`                | PDF generation (invoices)                                 |
| `intervention/image`           | Image manipulation                                        |
| `kreait/laravel-firebase`      | Firebase FCM push notifications                           |
| `knuckleswtf/scribe`           | API documentation generator                               |

---

## 🚀 Installation & Setup

### Prerequisites

-   **Docker** & **Docker Compose** installed
-   **Git** installed

### Development Setup (First Time Setup)

Navigate to the `deployment` directory:

```bash
cd deployment
```

#### Step 1: Environment Configuration

Copy the environment example file:

```bash
cp .env.example .env
```

Edit `.env` and configure the following (minimum required):

```env
# App
APP_NAME="Laravel Boilerplate"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

# Database
DB_CONNECTION=mysql
DB_HOST=boilerplate-db
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=laravel_user
DB_PASSWORD=secret_password
MYSQL_ROOT_PASSWORD=root_password

# Redis
REDIS_HOST=boilerplate-redis
REDIS_PASSWORD=null
REDIS_PORT=6379

# Reverb (WebSocket)
REVERB_APP_ID=app-id
REVERB_APP_KEY=local-app-key
REVERB_APP_SECRET=app-secret
REVERB_HOST=boilerplate-reverb
REVERB_PORT=8080
REVERB_SCHEME=http

# Stripe (Optional - add if using payments)
STRIPE_KEY=your_stripe_publishable_key
STRIPE_SECRET=your_stripe_secret_key
STRIPE_WEBHOOK_SECRET=your_webhook_secret

# Firebase (Optional - add if using push notifications)
FIREBASE_CREDENTIALS=backend/firebase-credentials.json
```

#### Step 2: Solving the "Chicken and Egg" Problem

When you first clone this repository, the `vendor` folder doesn't exist (it's in `.gitignore`). The Docker container's entrypoint script requires this folder to run properly, which creates a catch-22 situation.

**Solution:** Run Composer install BEFORE starting the full stack:

```bash
docker compose run --rm --entrypoint "" boilerplate-app composer install
```

> **Note:** Replace `boilerplate-app` with your actual service name if you've changed it in `docker-compose.yml`

**What this does:**

-   `run` - Executes a one-time command in a new container
-   `--rm` - Automatically removes the container after execution
-   `--entrypoint ""` - Bypasses the default entrypoint script
-   `boilerplate-app` - The service name from docker-compose.yml
-   `composer install` - Installs all PHP dependencies into the `vendor` folder

#### Step 3: Start the Docker Environment

Now you can start all services normally:

```bash
docker compose up -d
```

This will start:

-   Laravel Application (PHP-FPM)
-   Nginx (Web server + Reverse proxy)
-   MySQL Database
-   Redis
-   Queue Worker
-   Scheduler
-   Reverb WebSocket Server
-   Node.js (for Vite development)
-   phpMyAdmin

#### Step 4: Initialize the Application

Generate application key:

```bash
docker compose exec boilerplate-app php artisan key:generate
```

Run database migrations and seeders:

```bash
docker compose exec boilerplate-app php artisan migrate --seed
```

#### Step 5: Access Your Application

-   **Application**: http://localhost
-   **phpMyAdmin**: http://localhost:8081
-   **Vite Dev Server**: http://localhost:5173
-   **Reverb WebSocket**: ws://localhost:8089

---

## 🔄 Daily Development Workflow

### Starting the Environment

```bash
cd deployment
docker compose up -d
```

### Stopping the Environment

```bash
docker compose down
```

### Viewing Logs

```bash
# All services
docker compose logs -f

# Specific service
docker compose logs -f boilerplate-app
docker compose logs -f queue-worker
docker compose logs -f scheduler
```

### Running Artisan Commands

```bash
docker compose exec boilerplate-app php artisan [command]
```

Examples:

```bash
# Clear cache
docker compose exec boilerplate-app php artisan cache:clear

# Create a new controller
docker compose exec boilerplate-app php artisan make:controller ApiController

# Run migrations
docker compose exec boilerplate-app php artisan migrate

# Create a new migration
docker compose exec boilerplate-app php artisan make:migration create_posts_table
```

### Installing New Packages

**PHP packages:**

```bash
docker compose exec boilerplate-app composer require vendor/package
```

**After pulling new code with updated dependencies:**

```bash
docker compose exec boilerplate-app composer install
```

**Node packages:**

```bash
docker compose exec boilerplate-node npm install package-name
```

### Running Tests

```bash
docker compose exec boilerplate-app php artisan test
```

---

## 📋 Production Deployment

### Using Docker Compose in Production

This boilerplate uses a single `docker-compose.yml` file with an optional `docker-compose.override.yml` for development-specific configurations.

**In Development:**

-   Both `docker-compose.yml` and `docker-compose.override.yml` are used automatically
-   Override file mounts source code as volumes for live reloading
-   Includes Vite dev server and development-specific configurations

**In Production:**

-   Only `docker-compose.yml` is used
-   No override file should exist on production server
-   Code is baked into the Docker image (no volume mounts)
-   Optimized for performance and security

### Production Deployment Steps

1. **Build the production image:**

```bash
docker build -t my-boilerplate-app:latest -f backend/Dockerfile backend/
```

2. **Push to registry (optional):**

```bash
docker tag my-boilerplate-app:latest registry.example.com/my-boilerplate-app:latest
docker push registry.example.com/my-boilerplate-app:latest
```

3. **On production server:**

```bash
# Copy only docker-compose.yml and .env
scp deployment/docker-compose.yml user@server:/app/
scp deployment/.env user@server:/app/

# SSH into server
ssh user@server

# Navigate to app directory
cd /app

# Pull and start services
docker compose pull
docker compose up -d

# Run migrations (first time or after updates)
docker compose exec boilerplate-app php artisan migrate --force
```

4. **SSL Certificate Setup:**

```bash
# Request SSL certificate (first time)
docker compose exec certbot certbot certonly --webroot \
  -w /var/www/certbot \
  -d yourdomain.com \
  -d www.yourdomain.com \
  --email your@email.com \
  --agree-tos \
  --no-eff-email
```

### Production Environment Variables

Ensure these are properly configured in production `.env`:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

# Use strong random keys
APP_KEY=base64:...

# Production database
DB_HOST=boilerplate-db
DB_DATABASE=production_db
DB_USERNAME=prod_user
DB_PASSWORD=strong_random_password

# Production Stripe keys
STRIPE_KEY=pk_live_...
STRIPE_SECRET=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...

# Configure mail service
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
```

---

## 🏗️ Architecture Overview

### Layered Architecture

```
Routes → Controllers → Services → Models
```

### Service Layer Pattern

All business logic resides in service classes extending `BaseService`:

```php
namespace App\Services;

class UserService extends BaseService
{
    protected string $modelClass = User::class;

    // Custom business logic here
}
```

### Reusable Traits

**FileUploadTrait** - Handle image uploads with resize & WebP conversion:

```php
$path = $this->handleFileUpload($request, 'avatar', 'avatars', width: 600, height: 600, forceWebp: true);
```

**Cacheable** - Tag-based model caching:

```php
return $this->cache(__FUNCTION__, func_get_args(), function () {
    return $this->model->latest()->get();
});
```

**ManagesData** - Transactional create/update:

```php
$entity = $this->storeOrUpdate($data, $modelInstance, ['roles' => [1,2]]);
```

### Event-Driven Architecture

-   **Chat Events**: `MessageSent`, `MessageUpdated`, `UserTyping`
-   **Payment Events**: Stripe webhook listener automatically syncs subscriptions, payments, and refunds

## 🗂️ Project Structure

```
app/
├── Services/           # Business logic (extend BaseService)
├── Traits/            # Reusable components (Cacheable, ManagesData, FileUploadTrait)
├── Http/Controllers/  # Route controllers
├── Models/            # Eloquent models
├── Listeners/         # Event listeners (StripeEventListener)
├── Events/            # Application events
├── Policies/          # Authorization policies
├── Notifications/     # Email/SMS/Push notifications
└── Helpers/           # Helper functions

config/
├── cashier.php        # Stripe Cashier configuration
├── permission.php     # Role & permission settings
└── services.php       # Third-party service configs

routes/
├── api.php            # API routes (v1)
├── web.php            # Web routes (Stripe webhooks)
├── channels.php       # Broadcast channel authorization
└── console.php        # Artisan commands

database/
├── migrations/        # Database migrations
├── seeders/           # Database seeders
└── factories/         # Model factories
```

---

## � API Authentication

All API endpoints require authentication via Laravel Sanctum tokens.

### Authentication Flow

**1. Register**

```http
POST /api/v1/auth/register
Content-Type: application/json

{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

**2. Login**

```http
POST /api/v1/auth/login
Content-Type: application/json

{
  "email": "john@example.com",
  "password": "password123"
}

Response:
{
  "token": "1|abc123...",
  "user": { ... }
}
```

**3. Use the token for authenticated requests**

```http
GET /api/v1/profile/me
Authorization: Bearer 1|abc123...
```

### Additional Auth Endpoints

-   `POST /api/v1/auth/verify` - Verify email
-   `POST /api/v1/auth/resend-verification` - Resend verification email
-   `POST /api/v1/auth/forgot-password` - Request password reset
-   `POST /api/v1/auth/verify-password-otp` - Verify OTP
-   `POST /api/v1/auth/reset-password-with-token` - Reset password
-   `POST /api/v1/auth/update-password` - Update password (authenticated)
-   `POST /api/v1/auth/logout` - Logout (authenticated)

### Profile Endpoints

-   `GET /api/v1/profile/me` - Get authenticated user
-   `POST /api/v1/profile/update` - Update profile

---

## 💬 Chat API

All chat endpoints are protected with `auth:sanctum` middleware.

**Base URL:** `/api/v1/chat`

### Conversations

```http
# List all conversations
GET /api/v1/chat/conversations

# Create a new conversation
POST /api/v1/chat/conversations
{
  "recipient_id": 2,          // For 1-on-1 (optional)
  "participant_ids": [2, 3],  // For groups (optional)
  "name": "Project Discussion" // For groups (optional)
}
```

### Messages

```http
# Get messages for a conversation
GET /api/v1/chat/conversations/{conversation}/messages

# Send a message
POST /api/v1/chat/messages
{
  "conversation_id": 1,
  "body": "Hello there!",
  "attachment": <file> // Optional
}

# Edit a message
PATCH /api/v1/chat/messages/{message}
{
  "body": "Updated message"
}

# Delete a message
DELETE /api/v1/chat/messages/{message}

# Mark messages as read
POST /api/v1/chat/messages/read
{
  "message_ids": [1, 2, 3]
}

# Send typing indicator
POST /api/v1/chat/conversations/{conversation}/typing
{
  "typing": true
}
```

### Group Management

```http
# Add members to group
POST /api/v1/chat/groups/{conversation}/members
{
  "user_ids": [4, 5]
}

# Remove member from group
DELETE /api/v1/chat/groups/{conversation}/members
{
  "user_id": 4
}

# Leave group
POST /api/v1/chat/groups/{conversation}/leave

# Promote to admin
POST /api/v1/chat/groups/{conversation}/promote
{
  "user_id": 4
}

# Demote from admin
POST /api/v1/chat/groups/{conversation}/demote
{
  "user_id": 4
}
```

### Real-time Events

Subscribe to these channels using Laravel Echo:

-   **Private Channel:** `conversations.{id}`
    -   Event: `MessageSent` - New message received
    -   Event: `MessageUpdated` - Message edited
    -   Event: `MessageDeleted` - Message deleted
    -   Whisper: `typing` - User is typing

---

## 💳 Payment & Billing API

**Base URL:** `/api/v1/payment`

### Stripe Webhooks

Configure these webhook endpoints in your Stripe Dashboard:

```
POST /api/v1/stripe/webhook        # Cashier webhook (recommended)
POST /stripe/webhook                # Fallback webhook
```

**Webhook Events Handled:**

-   `customer.subscription.created`
-   `customer.subscription.updated`
-   `customer.subscription.deleted`
-   `invoice.payment_succeeded`
-   `charge.succeeded`
-   `charge.refunded`
-   `payment_method.attached`
-   `setup_intent.succeeded`

### One-Time Payments

```http
# Create checkout session
POST /api/v1/payment/one-time/checkout-session
{
  "amount": 5000,              // Amount in cents
  "description": "Product XYZ",
  "success_url": "https://yoursite.com/success",
  "cancel_url": "https://yoursite.com/cancel"
}

# Create payment intent (for custom flows)
POST /api/v1/payment/one-time/payment-intent
{
  "amount": 5000,
  "description": "Product XYZ"
}
```

### Subscriptions

```http
# Create subscription
POST /api/v1/payment/subscriptions
{
  "price_id": "price_123abc",      // Stripe Price ID
  "payment_method": "pm_123abc"    // Optional if default exists
}

# Get current subscription
GET /api/v1/payment/subscriptions

# Cancel subscription
POST /api/v1/payment/subscriptions/cancel
{
  "immediately": false  // false = end of period, true = now
}

# Resume cancelled subscription
POST /api/v1/payment/subscriptions/resume

# Swap/change plan
POST /api/v1/payment/subscriptions/swap
{
  "price_id": "price_456def"
}
```

### Payment Methods

```http
# List payment methods
GET /api/v1/payment/payment-methods

# Add payment method (via Setup Intent)
POST /api/v1/payment/payment-methods/setup-intent

Response:
{
  "client_secret": "seti_123_secret_abc"
}

# Add payment method (via Checkout Session)
POST /api/v1/payment/payment-methods/setup-session
{
  "success_url": "https://yoursite.com/success",
  "cancel_url": "https://yoursite.com/cancel"
}

# Set default payment method
PATCH /api/v1/payment/payment-methods/{id}/set-default

# Delete payment method
DELETE /api/v1/payment/payment-methods/{id}

# Bulk delete payment methods
DELETE /api/v1/payment/payment-methods
{
  "payment_method_ids": ["pm_123", "pm_456"]
}
```

### Invoices

```http
# List invoices
GET /api/v1/payment/invoices

# Download invoice PDF
GET /api/v1/payment/invoices/{invoice}/download
```

### Refunds

```http
# Request refund
POST /api/v1/payment/refunds
{
  "charge_id": "ch_123abc",
  "amount": 5000,        // Optional (full refund if omitted)
  "reason": "requested_by_customer"  // Optional
}
```

### Billing Portal

```http
# Redirect to Stripe Billing Portal
POST /api/v1/payment/billing-portal
{
  "return_url": "https://yoursite.com/account"
}

Response:
{
  "url": "https://billing.stripe.com/session/..."
}
```

---

## 🔄 Subscription Lifecycle

The system automatically keeps subscriptions in sync via Stripe webhooks:

1. **User Registration** → Stripe customer created on first billing action
2. **Subscription Created** → Webhook `customer.subscription.created` → Local subscription record created
3. **Payment Success** → Webhook `invoice.payment_succeeded` → Transaction record created
4. **Subscription Updated** → Webhook `customer.subscription.updated` → Status, trial, cancel period synced
5. **Refund Issued** → Webhook `charge.refunded` → Transaction metadata updated with refund info
6. **Payment Method Added** → Webhook `payment_method.attached` → Card fingerprint checked for duplicates

### Transaction Model

All payments are tracked in the `transactions` table with:

-   Amount, currency, status
-   Stripe charge ID, invoice ID
-   Receipt URL
-   Billing period (start/end dates)
-   Refund details (in metadata JSON)
-   Idempotent upserts (no duplicate records)

---

## 🔍 Advanced Query Filtering

This project uses `spatie/laravel-query-builder` for powerful, secure API filtering, sorting, and includes.

### Why Use It?

-   **Whitelist-based security**: Only allowed filters/sorts/includes work
-   **Consistent API patterns**: Standardized query parameters across all endpoints
-   **Frontend-friendly**: Easy to integrate with React/Vue data tables
-   **Performance**: Built-in support for sparse fieldsets and eager loading

### Features

#### Filtering

```http
# Exact match
GET /api/v1/users?filter[status]=active

# Partial match (LIKE)
GET /api/v1/users?filter[name]=john

# Global search across multiple columns
GET /api/v1/users?filter[global]=gmail
```

#### Sorting

```http
# Ascending
GET /api/v1/users?sort=name

# Descending
GET /api/v1/users?sort=-created_at

# Multiple sorts
GET /api/v1/users?sort=name,-id
```

#### Includes (Eager Loading)

```http
# Load relationships
GET /api/v1/users?include=roles,permissions

# Combine with filters
GET /api/v1/users?include=roles&filter[status]=active
```

#### Sparse Fieldsets

```http
# Select specific columns
GET /api/v1/users?fields[users]=id,name,email

# Reduce payload size for mobile apps
GET /api/v1/users?fields[users]=id,name&include=roles&fields[roles]=id,name
```

#### Pagination

```http
# Page size and number
GET /api/v1/users?page[size]=50&page[number]=2
```

### Implementation Example

In your service class:

```php
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use App\Filters\GlobalSearchFilter;

public function list()
{
    return QueryBuilder::for(User::class)
        ->allowedFilters([
            AllowedFilter::exact('id'),
            AllowedFilter::exact('status'),
            'name',
            'email',
            AllowedFilter::custom('global', new GlobalSearchFilter()),
        ])
        ->allowedSorts(['id', 'name', 'created_at'])
        ->allowedIncludes(['roles', 'permissions'])
        ->allowedFields(['users.id', 'users.name', 'users.email'])
        ->paginate(request('page.size', 15))
        ->appends(request()->query());
}
```

### Global Search Filter

Custom filter for searching across multiple columns:

```php
// app/Filters/GlobalSearchFilter.php
namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Filters\Filter;

class GlobalSearchFilter implements Filter
{
    public function __invoke(Builder $query, $value, string $property)
    {
        $columns = explode(',', $property);

        $query->where(function (Builder $q) use ($columns, $value) {
            foreach ($columns as $column) {
                $q->orWhere($column, 'LIKE', "%{$value}%");
            }
        });
    }
}
```

### Query Parameter Cheat Sheet

| Capability      | Example                        |
| --------------- | ------------------------------ |
| Global search   | `?filter[global]=john`         |
| Exact filter    | `?filter[status]=active`       |
| Partial filter  | `?filter[name]=ali`            |
| Sort descending | `?sort=-created_at`            |
| Multiple sorts  | `?sort=name,-id`               |
| Includes        | `?include=roles,permissions`   |
| Sparse fields   | `?fields[users]=id,name,email` |
| Page size       | `?page[size]=50`               |
| Page number     | `?page[number]=2`              |

### Security & Performance

-   **Index commonly filtered columns** in your migrations:

    ```php
    $table->index(['status', 'created_at']);
    ```

-   **Use full-text indexes** for large text searches:

    ```php
    $table->fullText(['name', 'description']);
    ```

-   **Always whitelist** allowed filters, sorts, and includes
-   **Hide sensitive fields** in model `$hidden` property

---

## 🧪 Testing

```bash
# Run all tests
docker compose exec boilerplate-app php artisan test

# Run specific test file
docker compose exec boilerplate-app php artisan test tests/Feature/AuthTest.php

# Run with coverage
docker compose exec boilerplate-app php artisan test --coverage
```

### Generate API Documentation

```bash
docker compose exec boilerplate-app php artisan scribe:generate
```

Documentation will be available at `/docs`

---

## 🐞 Troubleshooting

| Issue                            | Cause                       | Fix                                                                            |
| -------------------------------- | --------------------------- | ------------------------------------------------------------------------------ |
| 419 / CSRF (SPA)                 | Sanctum / domain mismatch   | Align SESSION_DOMAIN & frontend origin                                         |
| Webhook 400                      | Wrong STRIPE_WEBHOOK_SECRET | Update `.env`; test via Stripe CLI                                             |
| Duplicate payment method skipped | Same card fingerprint       | Expected (dedup logic)                                                         |
| Subscription missing             | Webhook not processed       | Ensure queue worker running                                                    |
| Reverb connection fails          | Port or CORS misconfig      | Expose REVERB_PORT & adjust JS client                                          |
| vendor folder missing            | First time clone            | Run `docker compose run --rm --entrypoint "" boilerplate-app composer install` |

---

## 📝 Next Steps & Improvements

-   Add Laravel Horizon for advanced queue management
-   Implement rate limiting for API endpoints
-   Add full-text search with Scout + Meilisearch
-   Webhook signature logging
-   Multi-tier subscription management UI
-   Automated backup system

---

## 📜 License

MIT License - Feel free to use this boilerplate for personal or commercial projects.

---

## 🙌 Contributing

Contributions are welcome! Please:

-   Fork the repository
-   Create a feature branch
-   Make your changes with clear commit messages
-   Submit a pull request

---

## ✅ Summary

This boilerplate provides everything you need to quickly build and deploy a production-ready SaaS application:

✅ Complete authentication & authorization  
✅ Real-time chat with WebSockets  
✅ Stripe payment integration  
✅ Background jobs & scheduling  
✅ Docker-based deployment  
✅ Clean, scalable architecture

**Ship faster, focus on your business logic!** 🚀

---

## 🔌 Frontend Integration (React & React Native)

Integrate with real-time + REST APIs using Laravel Echo (Reverb / Pusher protocol) and Axios.

### Backend Broadcast Essentials

`.env` keys:

```
BROADCAST_DRIVER=reverb
REVERB_APP_KEY=local-app-key
REVERB_APP_SECRET=app-secret
REVERB_APP_ID=app-id
REVERB_HOST=0.0.0.0
REVERB_PORT=8085
REVERB_SCHEME=http
```

Private / presence auth: `/broadcasting/auth` with Bearer token.

Event example:

```php
class MessageSent implements ShouldBroadcast {
	public function __construct(public Message $message) {}
	public function broadcastOn(): array { return [new PrivateChannel('conversations.' . $this->message->conversation_id)]; }
	public function broadcastWith(): array { return [ 'id'=>$this->message->id,'body'=>$this->message->body,'user_id'=>$this->message->user_id,'conversation_id'=>$this->message->conversation_id,'created_at'=>$this->message->created_at->toISOString(), ]; }
}
```

### React Web

`.env.local`:

```
VITE_API_URL=http://localhost:81/api/v1
VITE_AUTH_TOKEN_KEY=auth_token
VITE_REVERB_APP_KEY=local-app-key
VITE_REVERB_HOST=localhost
VITE_REVERB_PORT=8085
VITE_REVERB_SCHEME=http
```

Install:

```bash
npm i axios laravel-echo pusher-js
```

`src/lib/api.ts`:

```ts
import axios from "axios";
const api = axios.create({ baseURL: import.meta.env.VITE_API_URL });
api.interceptors.request.use((c) => {
    const t = localStorage.getItem(
        import.meta.env.VITE_AUTH_TOKEN_KEY || "token"
    );
    if (t) c.headers.Authorization = `Bearer ${t}`;
    c.headers.Accept = "application/json";
    return c;
});
export default api;
```

`src/lib/echo.ts`:

```ts
import Echo from "laravel-echo";
import Pusher from "pusher-js";
(window as any).Pusher = Pusher;
const authHeaders = () => {
    const t = localStorage.getItem(
        import.meta.env.VITE_AUTH_TOKEN_KEY || "token"
    );
    return t ? { Authorization: `Bearer ${t}` } : {};
};
export const echo = new Echo({
    broadcaster: "reverb",
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: +(import.meta.env.VITE_REVERB_PORT || 8085),
    wssPort: +(import.meta.env.VITE_REVERB_PORT || 8085),
    forceTLS: import.meta.env.VITE_REVERB_SCHEME === "https",
    enabledTransports: ["ws", "wss"],
    authEndpoint: "/broadcasting/auth",
    auth: { headers: authHeaders() },
});
```

Component usage:

```tsx
useEffect(() => {
    const ch = echo.private(`conversations.${conversationId}`);
    ch.listen("MessageSent", (e: any) => setMessages((m) => [...m, e]));
    ch.listenForWhisper("typing", (w: any) => setTypingUser(w.user_id));
    return () => echo.leave(`private-conversations.${conversationId}`);
}, [conversationId]);
```

### React Native

`.env`:

```
API_URL=http://10.0.2.2:81/api/v1
REVERB_APP_KEY=local-app-key
REVERB_HOST=10.0.2.2
REVERB_PORT=8085
```

Install:

```bash
npm i axios laravel-echo pusher-js @react-native-async-storage/async-storage
```

`echo.ts`:

```ts
import Echo from "laravel-echo";
import Pusher from "pusher-js/react-native";
import AsyncStorage from "@react-native-async-storage/async-storage";
export const echo = new Echo({
    broadcaster: "reverb",
    key: process.env.REVERB_APP_KEY,
    wsHost: process.env.REVERB_HOST,
    wsPort: Number(process.env.REVERB_PORT || 8085),
    forceTLS: false,
    enabledTransports: ["ws", "wss"],
    authEndpoint: "http://10.0.2.2:81/broadcasting/auth",
    authorizer: (channel, options) => ({
        authorize: async (socketId, cb) => {
            try {
                const token = await AsyncStorage.getItem("auth_token");
                const res = await fetch(options.authEndpoint, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        Authorization: token ? `Bearer ${token}` : "",
                        "X-Socket-ID": socketId,
                    },
                    body: JSON.stringify({ channel_name: channel.name }),
                });
                if (!res.ok) throw new Error("Auth failed");
                cb(false, await res.json());
            } catch (e) {
                cb(true, e);
            }
        },
    }),
});
```

`api.ts`:

```ts
import axios from "axios";
import AsyncStorage from "@react-native-async-storage/async-storage";
export const api = axios.create({ baseURL: process.env.API_URL });
api.interceptors.request.use(async (c) => {
    const t = await AsyncStorage.getItem("auth_token");
    if (t) c.headers.Authorization = `Bearer ${t}`;
    c.headers.Accept = "application/json";
    return c;
});
```

### Channel Authorization (`routes/channels.php`)

```php
Broadcast::channel('conversations.{id}', fn($user,$id)=>$user->canAccessConversation($id));
Broadcast::channel('presence.conversations.{id}', function($user,$id){ if($user->canAccessConversation($id)){ return ['id'=>$user->id,'name'=>$user->name]; } return false;});
```

### Typing Whisper

```ts
echo.private(`conversations.${conversationId}`).whisper("typing", {
    user_id: currentUserId,
    typing: true,
});
```

### Quick Test

1. Login & store token.
2. Console: `window.Echo.private('conversations.1').listen('MessageSent',console.log)`.
3. POST `/api/v1/chat/messages` → event appears.
4. Whisper typing → second client sees indicator.

### Common Issues

| Problem                | Cause                 | Fix                      |
| ---------------------- | --------------------- | ------------------------ |
| 403 /broadcasting/auth | Missing token         | Add Authorization header |
| No events              | Channel typo          | Match exact channel      |
| Whispers missing       | Used listen()         | Use listenForWhisper()   |
| RN cannot connect      | Using localhost       | Use 10.0.2.2 / device IP |
| Presence empty         | Returned boolean only | Return user data array   |
