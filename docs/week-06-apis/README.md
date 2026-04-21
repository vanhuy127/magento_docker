# Week 6: REST APIs, Webhooks & Integration

**Goal:** Build and consume REST APIs — both Magento's Web API framework and external services. Understand webhook patterns for event-driven external system integration.

---

## Topics Covered

- Magento REST API architecture (webapi.xml, service contracts → API binding)
- Creating custom REST endpoints via `webapi.xml`
- Token-based authentication (admin tokens, customer tokens)
- OAuth 2.0 and Integration authentication
- Webhook dispatcher pattern for external system notifications
- Bulk and Async API for large-scale operations
- Rate limiting and error handling for public APIs

---

## Reference Exercises

- **Exercise 6.1:** Create a REST endpoint via `webapi.xml` and test with cURL
- **Exercise 6.2:** Implement token-based authentication for admin access
- **Exercise 6.3:** Build a webhook observer to dispatch on order creation
- **Exercise 6.4:** Implement bulk import using async queue processing

---

## By End of Week 6 You Must Prove

- [ ] Custom REST endpoint accessible at `/rest/V1/training/reviews/:id`
- [ ] Admin token obtained and used to call a protected endpoint
- [ ] Webhook dispatched when order is placed (observer on `sales_order_place_after`)
- [ ] Bulk import processes via async queue without blocking HTTP
- [ ] API returns proper error responses (404, 401, 400) for invalid input
- [ ] DoD assessment passed

---

## Assessment Criteria

| Test | Time | Criteria |
|------|------|----------|
| REST Endpoint | 25 min | Endpoint defined in webapi.xml, returns JSON via cURL |
| Auth Token | 20 min | Token obtained, used to access protected endpoint |
| Webhook Observer | 30 min | Event dispatched, external endpoint called, response logged |
| Bulk/Async Import | 30 min | Import queued and processed via consumer |
| Error Handling | 10 min | API returns proper error codes and messages |

---

## Topics

---

### Topic 1: Magento REST API Architecture

**How Magento's Web API Works:*

```
HTTP Request → Authentication (token/OAuth) → Route matched via webapi.xml
    → Service Class (Repository) → DB
         ↑ Auto-generated if using Service Contracts
```

**Route Definition in `etc/webapi.xml`:*

```xml
<?xml version="1.0"?>
<routes xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Webapi/etc/webapi.xsd">

    <!-- GET /V1/training/reviews — list all -->
    <route method="GET" url="/V1/training/reviews">
        <service class="Training\Review\Api\ReviewRepositoryInterface" method="getList"/>
        <resources>
            <resource ref="Training_Review::review_view"/>
        </resources>
    </route>

    <!-- GET /V1/training/reviews/:id — get one -->
    <route method="GET" url="/V1/training/reviews/:id">
        <service class="Training\Review\Api\ReviewRepositoryInterface" method="getById"/>
        <resources>
            <resource ref="Training_Review::review_view"/>
        </resources>
    </route>

    <!-- POST /V1/training/reviews — create -->
    <route method="POST" url="/V1/training/reviews">
        <service class="Training\Review\Api\ReviewRepositoryInterface" method="save"/>
        <resources>
            <resource ref="Training_Review::review_edit"/>
        </resources>
    </route>

    <!-- DELETE /V1/training/reviews/:id -->
    <route method="DELETE" url="/V1/training/reviews/:id">
        <service class="Training\Review\Api\ReviewRepositoryInterface" method="deleteById"/>
        <resources>
            <resource ref="Training_Review::review_delete"/>
        </resources>
    </route>
</routes>
```

**ACL Resources (required in every route):*

```xml
<resource ref="anonymous"/>                   <!-- Anyone -->
<resource ref="self"/>                        <!-- Current customer -->
<resource ref="Training_Review::review_edit"/> <!-- ACL resource -->
```

---

### Topic 2: REST API Calls

**Getting an Admin Token:*

```bash
curl -X POST http://localhost:8080/rest/V1/integration/admin/token \
  -H "Content-Type: application/json" \
  -d '{"username":"admin", "password":"admin123"}'
```

Returns: `"a1b2c3d4e5f6g7h8i9j0..."`

**Calling a Protected Endpoint:*

```bash
# Get all reviews
curl -X GET http://localhost:8080/rest/V1/training/reviews \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json"

# Get review by ID
curl -X GET http://localhost:8080/rest/V1/training/reviews/1 \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

**Creating a Review via POST:*

```bash
curl -X POST http://localhost:8080/rest/V1/training/reviews \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -d '{
    "review": {
      "product_id": 1,
      "reviewer_name": "Alice",
      "rating": 5,
      "review_text": "Excellent product quality!"
    }
  }'
```

**Updating a Review:*

```bash
curl -X PUT http://localhost:8080/rest/V1/training/reviews/1 \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -d '{
    "review": {
      "rating": 4,
      "review_text": "Updated: Good but delivery was slow"
    }
  }'
```

**Deleting a Review:*

```bash
curl -X DELETE http://localhost:8080/rest/V1/training/reviews/1 \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

**Error Responses:*

| HTTP Code | Meaning | Response Body |
|-----------|---------|---------------|
| 200 | Success | `{"...": "..."}` |
| 400 | Bad Request | `{"message": "Invalid parameter", "parameters": {...}}` |
| 401 | Unauthorized | `{"message": "Consumer is not authorized"}` |
| 403 | Forbidden | `{"message": "ACL resource denied"}` |
| 404 | Not Found | `{"message": "Requested entity not found"}` |
| 500 | Server Error | `{"message": "Internal error"}` |

---

### Topic 3: Authentication Methods

**Token-Based Auth (simplest):*

```php
// Get admin token
$token = $this->httpClient->post(
    BASE_URL . '/rest/V1/integration/admin/token',
    ['json' => ['username' => 'admin', 'password' => 'admin123']]
)->getBody();

// Use in subsequent requests
$headers = ['Authorization' => "Bearer $token"];
```

**OAuth 2.0 (for third-party integrations):*
More complex — requires pre-registration in Admin → Extensions → Integrations. Returns `access_token` and `refresh_token`.

**Integration Authentication:*
In Admin → System → Integrations, create an integration. Magento generates consumer key/secret for OAuth flow.

**Securing Specific Endpoints with ACL:*

```xml
<!-- Allow anonymous for public reads -->
<route method="GET" url="/V1/training/reviews">
    <service .../>
    <resources><resource ref="anonymous"/></resources>
</route>

<!-- Require authentication for writes -->
<route method="POST" url="/V1/training/reviews">
    <service .../>
    <resources><resource ref="Training_Review::review_edit"/></resources>
</route>
```

---

### Topic 4: Webhooks for External Systems

**Pattern:** When something happens in Magento → dispatch notification → external system reacts.

**Webhook Observer — `Observer/OrderCreatedWebhook.php`:*

```php
<?php
// Observer/OrderCreatedWebhook.php
namespace Training\Webhook\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;

class OrderCreatedWebhook implements ObserverInterface
{
    protected $logger;
    protected $webhookService;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Training\Webhook\Service\WebhookService $webhookService
    ) {
        $this->logger = $logger;
        $this->webhookService = $webhookService;
    }

    public function execute(Observer $observer): void
    {
        /** @var Order $order */
        $order = $observer->getData('order');

        // sales_order_place_after only fires on new order creation (not updates)

        $payload = [
            'event' => 'order.created',
            'timestamp' => time(),
            'data' => [
                'order_id' => $order->getId(),
                'increment_id' => $order->getIncrementId(),
                'customer_email' => $order->getCustomerEmail(),
                'total' => $order->getGrandTotal(),
                'currency' => $order->getOrderCurrencyCode(),
            ]
        ];

        try {
            $this->webhookService->dispatch('order.created', $payload);
            $this->logger->info('Webhook dispatched for order: ' . $order->getId());
        } catch (\Exception $e) {
            $this->logger->error('Webhook dispatch failed: ' . $e->getMessage());
        }
    }
}
```

**Webhook Service — `Service/WebhookService.php`:*

```php
<?php
// Service/WebhookService.php
namespace Training\Webhook\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

class WebhookService
{
    protected $logger;
    protected $scopeConfig;
    protected $client;

    public function __construct(
        LoggerInterface $logger,
        ScopeConfigInterface $scopeConfig,
        Client $client
    ) {
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
        $this->client = $client;
    }

    public function dispatch(string $event, array $payload): bool
    {
        $webhookUrl = $this->scopeConfig->getValue("webhooks/events/{$event}/url");
        if (!$webhookUrl) return false;

        try {
            $response = $this->client->post($webhookUrl, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Webhook-Signature' => $this->sign($payload),
                    'X-Webhook-Event' => $event,
                ],
                'json' => $payload
            ]);

            return $response->getStatusCode() === 200;
        } catch (GuzzleException $e) {
            $this->logger->error("Webhook failed for {$event}: " . $e->getMessage());
            return false;
        }
    }

    private function sign(array $payload): string
    {
        $secret = $this->scopeConfig->getValue('webhooks/secret');
        return hash_hmac('sha256', json_encode($payload), $secret);
    }
}
```

**Register Event — `etc/events.xml`:*

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Event/etc/events.xsd">
    <event name="sales_order_place_after">
        <observer name="training_webhook_order"
                  instance="Training\Webhook\Observer\OrderCreatedWebhook"
                  sortOrder="10"/>
    </event>
</config>
```

**Important:** In production, dispatch webhooks asynchronously via queue — never synchronously in the observer, or a slow external call will block the HTTP response.

---

### Topic 5: Bulk & Async API

**Use case:** Large imports/exports that would timeout if run synchronously.

**Queue Configuration — `etc/queue.xml`:*

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:MessageQueue/etc/queue.xsd">
    <queue name="training_review_bulk_export"
           connection="db"
           exchange="magento"
           topic="training.review.bulk_export">
        <consumer name="Training_Review_Consumer_BulkExport"
                  queue="training_review_bulk_export"
                  maxMessages="100"
                  instance="Training\Review\Model\Async\BulkExportConsumer"/>
    </queue>
</config>
```

**Scheduling Bulk Operations:*

```php
<?php
use Magento\AsynchronousOperations\Api\BulkScheduleInterface;
use Magento\Framework\Async\BulkOperationInterface;

class BulkExportController
{
    protected $bulkSchedule;

    public function __construct(BulkScheduleInterface $bulkSchedule)
    {
        $this->bulkSchedule = $bulkSchedule;
    }

    public function execute(): array
    {
        $reviewIds = $this->getReviewIdsFromRequest();

        $bulkUuid = $this->bulkSchedule->scheduleBulk(
            'training_export_reviews',
            $reviewIds,
            [],
            'Bulk review export',
            0
        );

        return ['uuid' => $bulkUuid, 'status' => 'scheduled'];
    }
}
```

**Async Consumer:*

```php
<?php
// Model/Async/BulkExportConsumer.php
namespace Training\Review\Model\Async;

use Magento\Framework\MessageQueue\ConsumerInterface;
use Psr\Log\LoggerInterface;

class BulkExportConsumer implements ConsumerInterface
{
    protected $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function processMessage(array $message): void
    {
        $reviewIds = $message['review_ids'] ?? [];
        $this->logger->info('Processing bulk export for ' . count($reviewIds) . ' reviews');

        foreach ($reviewIds as $reviewId) {
            // Generate export row...
        }

        $this->logger->info('Bulk export complete');
    }
}
```

**Running the Consumer:*

```bash
# Continuous worker
bin/magento queue:consumers:start Training_Review_Consumer_BulkExport

# One-shot (cron-based)
bin/magento queue:consumers:run Training_Review_Consumer_BulkExport --max-messages=100
```

---

## Reading List

- [Magento Web API](https://developer.adobe.com/commerce/php/development/components/webapi/) — REST and GraphQL
- [Create Custom REST API](https://developer.adobe.com/commerce/webapi/rest/tutorials/orders/) — webapi.xml configuration
- [Authentication](https://developer.adobe.com/commerce/php/development/components/webapi/authentication/) — Token, OAuth, Integration
- [Message Queue](https://developer.adobe.com/commerce/php/development/components/message-queue/) — Async operations

---

## Edge Cases & Troubleshooting

| Issue | Symptom | Solution |
|-------|---------|----------|
| API returns 401 | Token expired/invalid | Re-authenticate; tokens expire |
| Webhook not firing | External system stale | Verify event name in `events.xml` matches exactly |
| Bulk API timeout | Import stalls | Split into smaller batches or use async queue |
| CORS errors | Browser blocked | Use server-side (PHP) API calls, not AJAX |
| Missing ACL resource | 403 on endpoint | `<resource ref="..."/>` must exist in `etc/acl.xml` |

---

## Common Mistakes to Avoid

1. ❌ Exposing sensitive endpoints without ACL → Use `anonymous` only for public reads
2. ❌ Not validating API input → Check required fields in webapi.xml and repository
3. ❌ Synchronous webhooks → Use queue for external HTTP calls
4. ❌ Hardcoding API secrets → Use `scopeConfig`, never commit to git
5. ❌ Forgetting error handling → API errors should return proper HTTP codes

---

*Week 6 of Magento 2 Zero to Hero Training Program*  
*For: Interns*  
*Language: English*
