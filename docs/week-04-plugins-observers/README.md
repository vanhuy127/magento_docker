# Week 4: Customization — Plugins, Observers & Dependency Injection

**Goal:** Customize Magento behavior without touching core code — using plugins to intercept method calls and observers to react to events.

---

## Topics Covered

- After plugins — modifying return values after a method runs
- Before plugins — validating or modifying input before a method runs
- Around plugins — wrapping method logic, conditionally calling the original
- Event dispatch and observer registration
- Custom event dispatch
- Plugin sort order and interaction between multiple plugins
- di.xml advanced: preferences, virtual types, constructor injection configuration

---

## Reference Exercises

- **Exercise 4.1:** Create an after plugin on `ProductRepositoryInterface::save` that logs the SKU
- **Exercise 4.2:** Create a before plugin on `save` that rejects negative price or empty SKU
- **Exercise 4.3:** Create an around plugin on `getById` that logs execution time
- **Exercise 4.4:** Dispatch a custom event and create an observer to handle it
- **Exercise 4.5:** Configure a virtual type and non-shared factory in di.xml

---

## By End of Week 4 You Must Prove

- [ ] After plugin modifies `ProductRepositoryInterface::save` return value
- [ ] Before plugin validates SKU (not empty) and price (not negative)
- [ ] Around plugin wraps `getById` with timing logic
- [ ] Custom event dispatched from controller/service
- [ ] Observer responds to dispatched event
- [ ] Plugin sort order configured for multiple plugins
- [ ] Virtual type or preference configured in di.xml
- [ ] `bin/magento setup:di:compile` succeeds without errors
- [ ] DoD assessment passed

---

## Assessment Criteria

| Test | Time | Criteria |
|------|------|----------|
| After Plugin | 20 min | afterSave logs SKU, returns ProductInterface |
| Before Plugin | 20 min | beforeSave rejects empty SKU, negative price |
| Around Plugin | 25 min | aroundGetById wraps with timing, calls $proceed |
| Observer | 20 min | Custom event dispatched + observer logs data |
| Sort Order + di.xml | 10 min | 3 plugins with correct execution sequence |

---

## Topics

---

### Topic 1: After Plugins

**What after plugins do:** Run after the original method. Can modify the return value before it's returned to the caller. The most commonly used plugin type.

**Registration in `di.xml`:**

```xml
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:ObjectManager/etc/di.xsd">
    <type name="Magento\Catalog\Api\ProductRepositoryInterface">
        <plugin name="training_review_plugin"
                type="Training\Review\Plugin\ProductRepositoryPlugin"
                sortOrder="10"/>
    </type>
</config>
```

**After Plugin Pattern:**

```php
<?php
// Plugin/ProductRepositoryPlugin.php
namespace Training\Review\Plugin;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;

class ProductRepositoryPlugin
{
    private $logger;

    public function __construct(\Psr\Log\LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function afterSave(
        ProductRepositoryInterface $subject,
        ProductInterface $result
    ): ProductInterface {
        $sku = $result->getSku();
        $this->logger->info("Product saved via plugin: {$sku}");
        return $result;
    }

    public function afterGetById(
        ProductRepositoryInterface $subject,
        ProductInterface $result,
        int $productId
    ): ProductInterface {
        if ($result && !$result->getDescription()) {
            $result->setDescription('Default description set by plugin');
        }
        return $result;
    }
}
```

**Key rules:**
- Return the `$result` (or compatible type) — NOT returning = null = broken
- Method name must be exactly the intercepted method name
- The method receives the original method's arguments plus the result

---

### Topic 2: Before Plugins

**What before plugins do:** Run before the original method. Can validate or modify input arguments before the original method runs. Commonly used for input validation.

**Before Plugin Pattern:**

```php
<?php
// Plugin/ProductValidationPlugin.php
namespace Training\Review\Plugin;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;

class ProductValidationPlugin
{
    public function beforeSave(
        ProductRepositoryInterface $subject,
        ProductInterface $product,
        $saveOptions = []
    ): array {
        $sku = $product->getSku();

        if (empty($sku)) {
            throw new LocalizedException(__('SKU cannot be empty'));
        }

        if (strlen($sku) > 64) {
            throw new LocalizedException(__('SKU cannot exceed 64 characters'));
        }

        $price = $product->getPrice();
        if ($price !== null && $price < 0) {
            throw new LocalizedException(__('Price cannot be negative'));
        }

        return [$product, $saveOptions];
    }
}
```

**Key rules:**
- Method name is `before` + intercepted method name (e.g., `beforeSave`)
- Must return an array of modified arguments matching the original method signature
- Returning nothing = original arguments unchanged
- Used for validation, normalization, early-exit checks

---

### Topic 3: Around Plugins

**What around plugins do:** Completely wrap the original method. You control when (and if) the original method runs. Use sparingly — prefer before/after when possible.

**Around Plugin Pattern:**

```php
<?php
// Plugin/ProductTimingPlugin.php
namespace Training\Review\Plugin;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Api\Data\ProductInterface;

class ProductTimingPlugin
{
    private $logger;

    public function __construct(\Psr\Log\LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function aroundGetById(
        ProductRepositoryInterface $subject,
        callable $proceed,
        int $productId
    ): ProductInterface {
        $start = microtime(true);

        $product = $proceed($productId);  // Call original method

        $elapsed = round((microtime(true) - $start) * 1000, 2);
        $this->logger->info("getById took {$elapsed}ms for product {$productId}");

        return $product;
    }

    public function aroundSave(
        ProductRepositoryInterface $subject,
        callable $proceed,
        ProductInterface $product,
        $saveOptions = []
    ): ProductInterface {
        // Conditionally skip — for special flagged products
        if ($product->getData('skip_save')) {
            $this->logger->info('Skipping save for flagged product');
            return $product;
        }

        return $proceed($product, $saveOptions);
    }
}
```

**Key rules:**
- **You MUST call `$proceed()`** — otherwise the original method never runs and Magento breaks
- Only call `$proceed()` when you want the original to run
- Not calling `$proceed()` = you completely replaced the method's behavior

**When to use around vs before/after:**

| Situation | Plugin Type |
|-----------|-------------|
| Modify return value | `after` |
| Validate/change input | `before` |
| Conditionally skip method | `around` (don't call `$proceed()`) |
| Add logic before AND modify return | `around` |
| Caching layer | `around` |

---

### Topic 4: Observers & Events

**What observers do:** React to dispatched events — completely decoupled from the code that dispatches the event. Use when you want to react to something that happened, not intercept how it happened.

**Dispatching a Custom Event:**

```php
<?php
// In any service class
use Magento\Framework\Event\ManagerInterface;

class ReviewManagement
{
    protected $eventManager;

    public function __construct(ManagerInterface $eventManager)
    {
        $this->eventManager = $eventManager;
    }

    public function submitReview(array $reviewData): void
    {
        // ... save review ...

        $this->eventManager->dispatch('training_review_submitted', [
            'review' => $reviewData,
            'result' => $savedReview
        ]);
    }
}
```

**Registering an Observer — `etc/events.xml`:**

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Event/etc/events.xsd">
    <event name="training_review_submitted">
        <observer name="training_review_handler"
                  instance="Training\Review\Observer\ProcessReviewObserver"
                  sortOrder="10"/>
    </event>
</config>
```

**Observer Implementation:**

```php
<?php
// Observer/ProcessReviewObserver.php
namespace Training\Review\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class ProcessReviewObserver implements ObserverInterface
{
    private $logger;

    public function __construct(\Psr\Log\LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function execute(Observer $observer): void
    {
        $review = $observer->getData('review');
        $this->logger->info('Processing submitted review', $review);
        // Send notification, update stats, trigger workflow...
    }
}
```

**Key rule:** Get event data via `$observer->getData('key')`.

**Magento Core Events:**
Many core events exist — key ones to know:
- `controller_action_predispatch` — Before any controller action
- `sales_order_place_after` — After order placed
- `catalog_product_save_after` — After product saved
- `checkout_submit_all_after` — After checkout complete

---

### Topic 5: Plugin Sort Order & di.xml Advanced

**Plugin Sort Order:**

Lower `sortOrder` = earlier execution. All `before` plugins run first (in ascending order), then the original method, then all `after` plugins (in descending order).

```xml
<type name="Magento\Catalog\Api\ProductRepositoryInterface">
    <plugin name="logging"       type="LoggingPlugin"       sortOrder="10"/>
    <plugin name="validation"    type="ValidationPlugin"   sortOrder="20"/>
    <plugin name="notification"  type="NotificationPlugin" sortOrder="30"/>
</type>
```

Execution: logging(before) → validation(before) → original → notification(after) → validation(after) → logging(after)

**Preferences — Replace Entire Class:**

```xml
<preference for="Magento\Catalog\Api\ProductRepositoryInterface"
            type="Training\Review\Custom\ProductRepository"/>
```

Replaces the entire interface implementation. Use sparingly.

**Virtual Types — Custom Configurations Without New Classes:**

```xml
<virtualType name="TrainingReviewSearchResults"
             type="Magento\Framework\Api\SearchResults">
    <arguments>
        <argument name="logger" xsi:type="object">TrainingReviewLogger</argument>
    </arguments>
</virtualType>
```

**Constructor Injection via di.xml:**

```xml
<type name="Training\Review\Controller\Index\Index">
    <arguments>
        <argument name="paramName" xsi:type="string">custom_value</argument>
    </arguments>
</type>
```

**Shared vs Non-Shared:**

```xml
<!-- Non-shared: new instance every time (good for factories) -->
<type name="Training\Review\Model\ReviewFactory" shared="false"/>
```

---

## Reading List

- [Magento 2 Plugins](https://developer.adobe.com/commerce/php/development/components/plugins/) — Plugin types, sort order
- [Events and Observers](https://developer.adobe.com/commerce/php/development/components/events/) — Event dispatch, observer registration
- [Dependency Injection](https://developer.adobe.com/commerce/php/development/components/di/) — di.xml, virtual types, preferences

---

## Edge Cases & Troubleshooting

| Issue | Symptom | Solution |
|-------|---------|----------|
| Plugin not firing | Method unchanged | Check method name in di.xml matches exactly |
| Around plugin hangs | Request times out | Missing `$proceed()` call — original never runs |
| Observer not triggered | Event not caught | Check event name in `events.xml` matches dispatch |
| Circular dependency | DI compilation error | Plugin cannot depend on the type it intercepts |
| After returns null | Original result lost | After plugin must return a value |

---

## Common Mistakes to Avoid

1. ❌ Forgetting to call `$proceed()` in around plugin → Request hangs forever
2. ❌ Using preference when plugin would suffice → Preference replaces everything
3. ❌ Typo in event name in events.xml → Observer never fires
4. ❌ Plugin on non-public method → Won't work; only public methods support plugins
5. ❌ Modifying constructor args without understanding DI → Creates hard-to-debug issues

---

*Week 4 of Magento 2 Zero to Hero Training Program*  
*For: Interns*  
*Language: English*
