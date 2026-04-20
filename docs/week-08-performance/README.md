# Week 8: Performance Optimization

**Goal:** Understand and fix common Magento performance bottlenecks — cache layers, query optimization, indexing strategy, and profiling tools.

---

## Topics Covered

- Magento cache layers — which cache to use when
- Full-page caching and block caching (`cacheable="false"` caveats)
- Database query optimization — identifying slow queries
- Indexing strategy — realtime vs schedule trade-offs
- Profiling with built-in tools (`bin/magento profiler`)
- Image optimization and lazy loading
- JavaScript bundling and CSS minification
- Redis configuration for session and cache
- Asynchronous operations for heavy tasks

---

## Reference Exercises

- **Exercise 8.1:** Enable all cache types, verify `var/cache` is populated
- **Exercise 8.2:** Add block cache to a custom block via `cache.xml`
- **Exercise 8.3:** Identify and fix a slow query using `db_schema.xml` indexing
- **Exercise 8.4:** Configure Redis for session and cache backend
- **Exercise 8.5:** Set up a cron job asynchronously using message queue for bulk operations
- **Exercise 8.6:** Run the Magento profiler, interpret the output

---

## By End of Week 8 You Must Prove

- [ ] Block caching configured in `cache.xml` for a custom block
- [ ] Cache warms after first page load (subsequent loads served from cache)
- [ ] Slow query identified via logging or MySQL EXPLAIN, index added
- [ ] Redis configured as cache backend, sessions stored in Redis
- [ ] Asynchronous bulk operation processes via queue
- [ ] `bin/magento cache:flush` clears all cache successfully
- [ ] DoD assessment passed

---

## Assessment Criteria

| Test | Time | Criteria |
|------|------|----------|
| Block Caching | 20 min | Block renders on first load, cached on subsequent |
| Query Optimization | 30 min | Slow query found, index added, query time reduced |
| Redis Setup | 20 min | Redis running, Magento configured to use Redis |
| Async Queue | 30 min | Bulk operation queued and processed via consumer |
| Profiling | 20 min | Profiler output interpreted, bottleneck identified |

---

## Topics

---

### Topic 1: Magento Cache Layers

**Cache Types:**

| Cache Type | Purpose | Default TTL |
|-----------|---------|------------|
| `config` | XML config files | 86400 (24h) |
| `layout` | Layout XML compiled | 86400 |
| `block_html` | Block HTML output | 86400 |
| `full_page` | Full-page cache (FPC) | 86400 |
| `collections` | Database collections | 86400 |
| `reflection` | API reflection | 86400 |
| `webservice` | API introspection | 86400 |

**Viewing Cache Status:**

```bash
bin/magento cache:status
bin/magento cache:enable  layout block_html
bin/magento cache:disable config
```

**Cache Commands:**

```bash
bin/magento cache:flush     # Clear everything (all cache types)
bin/magento cache:clean    # Clear stale only
bin/magento cache:enable  # Enable specific cache
bin/magento cache:disable # Disable specific cache
```

**When to Flush Cache:**

| Change | Flush? |
|--------|--------|
| Edited PHTML template | Yes (`block_html`) |
| Changed layout XML | Yes (`layout`) |
| Changed system config | Yes (`config`) |
| Edited controller | Yes (`di`) |
| Edited block class | Yes (`block_html`) |
| New product saved | Yes (related caches) |

---

### Topic 2: Block & Full-Page Caching

**Block Caching — `cache.xml`:**

```xml
<!-- app/code/Training/Review/etc/cache.xml -->
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Cache/etc/cache.xsd">
    <placeholder name="training_review_block_message">
        <tag>training_review</tag>
        <tag>catalog_product</tag>
    </placeholder>
</config>
```

**Block with Cache Tags in Code:**

```php
<?php
// Block/CachedMessage.php
namespace Training\Review\Block;

use Magento\Framework\View\Element\Template;
use Magento\Catalog\Model\Product;

class CachedMessage extends Template
{
    public function getCacheLifetime(): ?int
    {
        return 3600; // 1 hour, null = infinite
    }

    public function getCacheTags(): array
    {
        return array_merge(parent::getCacheTags(), [
            \Magento\Catalog\Model\Product::CACHE_TAG . '_' . $this->getProductId()
        ]);
    }

    public function getCacheKeyInfo(): array
    {
        return array_merge(parent::getCacheKeyInfo(), [
            'product_id' => $this->getProductId()
        ]);
    }

    public function getIdentities(): array
    {
        return array_merge(parent::getIdentities(), [
            Product::CACHE_TAG . '_' . $this->getProductId()
        ]);
    }
}
```

**Template Cache Hint:**

```php
<?php // In template, add comment to identify cache issues ?>
<!-- cacheable="true" tag="training_review" lifetime="3600" -->
```

**Important: `cacheable="false"`**

```xml
<!-- DANGER: This block can NOT be cached. Use sparingly. -->
<block class="Training\Review\Block\Dynamic" cacheable="false" .../>
```

If any block on a page has `cacheable="false"`, the **entire page** cannot be full-page cached. Only use on:
- Customer-specific content (cart, wishlist)
- Admin-related blocks
- Frequently changing data

**Full-Page Cache (FPC) Configuration:**

```bash
# Enable FPC
bin/magento cache:enable full_page

# Or in env.php:
'system' => [
    'default' => [
        'system' => [
            'full_page_cache' => [
                'caching_application' => 2  # 2 = Redis, 1 = Varnish
            ]
        ]
    ]
]
```

---

### Topic 3: Database Query Optimization

**Finding Slow Queries:**

```bash
# Enable MySQL slow query log
bin/magento setup:config:set --sql-log-enable=1

# Or in env.php:
'db' => [
    'connection' => [
        'default' => [
            'profiler' => [
                'class' => '\Magento\Framework\DB\Profiler',
                'enabled' => true,
            ]
        ]
    ]
],
```

Then check `var/log/debug.db.log`.

**EXPLAIN Analysis:**

```sql
EXPLAIN SELECT * FROM training_review WHERE product_id = 1 ORDER BY created_at DESC;
```

Look for:
- `type: ALL` — full table scan → needs index
- `rows: > 1000` — too many rows examined
- `Extra: Using filesort` — slow sort operation

**Adding Indexes via `db_schema.xml`:**

```xml
<index referenceId="TRAINING_REVIEW_PRODUCT_ID_INDEX"
       tableName="training_review"
       indexType="btree">
    <column name="product_id"/>
</index>

<index referenceId="TRAINING_REVIEW_CREATED_AT_INDEX"
       tableName="training_review"
       indexType="btree">
    <column name="created_at"/>
</index>

<index referenceId="TRAINING_REVIEW_COMPOSITE_INDEX"
       tableName="training_review"
       indexType="btree">
    <column name="product_id"/>
    <column name="created_at"/>
</index>
```

**Query Optimization Rules:**

| Problem | Solution |
|---------|---------|
| `WHERE product_id = X` slow | Add index on `product_id` |
| `ORDER BY created_at` slow | Composite index on `(product_id, created_at)` |
| `LIKE '%keyword%'` slow | Full-text index or Elasticsearch |
| JOIN without index | Index foreign key columns |
| `COUNT(*)` on large table | Cache count result, invalidate on change |

**Collection Optimization:**

```php
// Bad: loads all data
$collection = $this->collectionFactory->create();
foreach ($collection as $item) { /* ... */ }

// Good: select only needed columns
$collection = $this->collectionFactory->create();
$collection->addFieldToSelect(['review_id', 'reviewer_name', 'rating']);
$collection->setPageSize(20)->setCurPage(1);

// Good: use getItems() for lightweight iteration
$items = $collection->getItems();
```

---

### Topic 4: Redis Configuration

**Why Redis?**

| Storage | Without Redis | With Redis |
|---------|--------------|-----------|
| Cache | File system (slow) | Memory (fast) |
| Sessions | File system (slow, disk I/O) | Memory (fast) |
| Page cache | File system | Memory (fast) |

**Docker Compose Redis Service:**

```yaml
redis:
  image: redis:7-alpine
  container_name: magento2-redis
  ports:
    - "6379:6379"
```

**Magento Redis Configuration in `env.php`:**

```php
'cache' => [
    'frontend' => [
        'default' => [
            'backend' => 'Magento\Framework\Cache\Backend\Redis',
            'backend_options' => [
                'server' => 'redis',
                'port' => 6379,
                'database' => 0,
                'password' => '',
                'compress_data' => true,
                'compression_lib' => 'gzip',
            ]
        ],
        'page_cache' => [
            'backend' => 'Magento\Framework\Cache\Backend\Redis',
            'backend_options' => [
                'server' => 'redis',
                'port' => 6379,
                'database' => 1,
                'compress_data' => 2,
                'compression_lib' => 'gzip',
            ]
        ]
    ]
],

'session' => [
    'save' => 'redis',
    'redis' => [
        'host' => 'redis',
        'port' => 6379,
        'database' => 2,
        'log_level' => 'info',
    ]
],
```

**Verifying Redis:**

```bash
# Connect to Redis container
docker compose exec redis redis-cli

# Check keys
KEYS *        # All keys
KEYS magento:*  # Magento keys
INFO clients  # Number of connected clients
```

---

### Topic 5: Profiling with Magento Tools

**Enabling the Profiler:**

```bash
# Enable
bin/magento dev:profiler:enable

# Disable
bin/magento dev:profiler:disable
```

**Profiler Output (HTML mode):**

Access `?profile=1` on any page to see:
- Timers — how long each step took
- SQL queries — all database queries with execution time
- Block rendering — which blocks rendered and how long
- Memory usage — peak memory consumption

**Code Profiling in Your Own Classes:**

```php
<?php
use Magento\Framework\Profiler;

// In your method
Profiler::start('training_review_process');
try {
    // ... work ...
} finally {
    Profiler::stop('training_review_process');
}
```

**Output to File:**

```bash
bin/magento dev:profiler:enable file
tail -f var/log/profiler.log
```

---

### Topic 6: Asynchronous Operations with Message Queue

**Why Async?**

Heavy operations (bulk email, mass indexing, export) can block HTTP requests. Moving them to a queue keeps the request fast.

**Message Queue Architecture:**

```
Producer → Queue → Consumer (async worker)
```

**Queue Configuration — `queue.xml`:**

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:MessageQueue/etc/queue.xsd">
    <queue name="training_review_bulk_export"
           connection="db"
           exchange="magento-export"
           topic="training.review.bulk_export">
        <consumer name="Training_Review_Consumer_BulkExport"
                  queue="training_review_bulk_export"
                  maxMessages="100"
                  instance="Training\Review\Model\Async\BulkExportConsumer"/>
    </queue>
</config>
```

**Publish to Queue:**

```php
<?php
// Publisher/PublishBulkExport.php
namespace Training\Review\Model\Publisher;

use Magento\Framework\MessageQueue\PublisherInterface;

class PublishBulkExport
{
    protected $publisher;

    public function __construct(PublisherInterface $publisher)
    {
        $this->publisher = $publisher;
    }

    public function publish(array $reviewIds): void
    {
        $this->publisher->publish('training.review.bulk_export', [
            'review_ids' => $reviewIds,
            'initiator' => 'admin_user_id_' . $this->getCurrentUserId(),
            'timestamp' => time()
        ]);
    }
}
```

**Consumer:**

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
        $reviewIds = $message['review_ids'];
        $this->logger->info('Processing bulk export for ' . count($reviewIds) . ' reviews');

        // Expensive export work
        foreach ($reviewIds as $reviewId) {
            // Generate export row...
        }

        $this->logger->info('Bulk export complete');
    }
}
```

**Running the Consumer:**

```bash
# Run continuously (for long-running worker)
bin/magento queue:consumers:start Training_Review_Consumer_BulkExport

# Or run once (for cron-based)
bin/magento queue:consumers:run Training_Review_Consumer_BulkExport --max-messages=100
```

---

## Reading List

- [Caching](https://developer.adobe.com/commerce/php/development/components/cache/) — Block and page caching
- [Cache Configuration](https://experienceleague.adobe.com/docs/commerce-operations/configuration-guide/cache/configure-redis.html) — Redis setup
- [Message Queue](https://developer.adobe.com/commerce/php/development/components/message-queue/) — Async consumers
- [Profiling](https://experienceleague.adobe.com/docs/commerce-operations/configuration-guide/debug/debug.html) — Profiler setup

---

## Edge Cases & Troubleshooting

| Issue | Symptom | Solution |
|-------|---------|----------|
| Cache not working | Page still slow | `cacheable="false"` on page — check all blocks |
| Redis not connecting | Cache falls back to file | Check Redis is running: `docker compose ps` |
| Slow query persists | EXPLAIN shows no index | Add index to `db_schema.xml`, run `setup:upgrade` |
| Profiler output messy | Too much data | Filter by timer name: `?profile=1&timer=sql` |
| Queue consumer timeout | Messages pile up | Increase `maxMessages`, check for long operations |
| Block cache not invalidating | Stale data shown | Block cache tags must match invalidated tags |

---

## Common Mistakes to Avoid

1. ❌ Using `cacheable="false"` too broadly → Entire page can't be cached
2. ❌ Not clearing cache after config changes → Stale config served
3. ❌ Large collections without pagination → Memory exhausted
4. ❌ Missing database indexes → Slow queries on production scale
5. ❌ Forgetting Redis password in production → Security vulnerability
6. ❌ Sync operations for bulk email → HTTP timeouts

---

*Week 8 of Magento 2 Zero to Hero Training Program*  
*For: Interns*  
*Language: English*
