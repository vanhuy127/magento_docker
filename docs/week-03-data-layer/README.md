# Week 3: Data Layer — Models, Database, Service Contracts

**Goal:** Build a complete data layer for a custom entity — from database table creation to a clean, testable repository API.

---

## Topics Covered

- Declarative schema (`db_schema.xml`) — creating tables without raw SQL
- Schema whitelist generation
- Data patches and schema patches for data and structure changes
- Model / ResourceModel / Collection triad
- EAV architecture and custom product attributes
- EAV beyond products — customer attributes, category attributes
- Transaction patterns and SaveHandler for complex entities
- Service Contracts — interface-first design
- Repository pattern with proper error handling
- SearchCriteria for filtering and pagination

---

## Reference Exercises

- **Exercise 3.1:** Create `training_review` table via `db_schema.xml`
- **Exercise 3.2:** Write a Data Patch and a Schema Patch
- **Exercise 3.3:** Build the Model / ResourceModel / Collection layer
- **Exercise 3.4:** Define Service Contract interfaces (`Api/Data/` and `Api/`)
- **Exercise 3.5:** Implement repository and use it in a controller
- **Exercise 3.6:** Add a custom customer attribute (loyalty tier) via setup patch
- **Exercise 3.7:** Add a custom category attribute (featured flag) via setup patch
- **Exercise 3.8:** Implement transaction-wrapped save in repository
- **Exercise 3.9 (optional):** Build a SaveHandler for complex entity logic

---

## By End of Week 3 You Must Prove

- [ ] Custom table created via `db_schema.xml` without errors
- [ ] Schema whitelist generated and committed
- [ ] Data Patch inserts records, Schema Patch modifies a column
- [ ] Model/ResourceModel/Collection implemented and wired
- [ ] Service Contract interfaces defined in `Api/Data/` and `Api/`
- [ ] Repository implementation wired via `di.xml`
- [ ] Controller uses repository (not direct model access)
- [ ] Customer EAV attribute created and readable via customer object
- [ ] Transaction pattern used in repository (tested by simulating failure)
- [ ] `bin/magento setup:db:status` shows module schema as current
- [ ] DoD assessment passed

---

## Assessment Criteria

| Test | Time | Criteria |
|------|------|----------|
| Schema Creation | 30 min | 6-column table via db_schema.xml, whitelist generated |
| Patches | 30 min | Data patch inserts 3 rows + schema patch modifies column |
| Service Contracts | 45 min | Interface + repository wired via di.xml |
| SearchCriteria + Controller | 30 min | Repository used in controller with filters |

---

## Topics

---

### Topic 1: Declarative Schema (db_schema.xml)

**Why Declarative Schema?**

| Approach | Problem |
|---------|---------|
| Raw SQL in install scripts | No rollback, version conflicts |
| Setup scripts (old way) | Complex, hard to maintain |
| `db_schema.xml` | Single source of truth, auto-generates whitelist |

**db_schema.xml Structure:**

```xml
<!-- app/code/Training/Review/etc/db_schema.xml -->
<?xml version="1.0"?>
<schema xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Setup:Declaration:etc/db_schema.xsd">
    <table name="training_review"
           resource="default"
           comment="Product Review Table"
           engine="innodb">
        <column xsi:type="int" name="review_id" padding="10" unsigned="true"
                identity="true" nullable="false" primary="true"/>
        <column xsi:type="int" name="product_id" padding="10" unsigned="true"
                nullable="false"/>
        <column xsi:type="varchar" name="reviewer_name" length="100"
                nullable="false"/>
        <column xsi:type="int" name="rating" padding="1" unsigned="true"
                nullable="false" default="5"/>
        <column xsi:type="text" name="review_text" nullable="true"/>
        <column xsi:type="timestamp" name="created_at" nullable="false"
                on_update="false" default="CURRENT_TIMESTAMP"/>
        <constraint xsi:type="foreign"
                     referenceId="TRAINING_REVIEW_PRODUCT_ID"
                     table="training_review"
                     column="product_id"
                     referenceTable="catalog_product_entity"
                     referenceColumn="entity_id"
                     onDelete="CASCADE"/>
    </table>
</schema>
```

**Column Types:**

| Type | SQL Equivalent | Use For |
|------|---------------|---------|
| `int` | INT | IDs, counts |
| `varchar` | VARCHAR | Short strings |
| `text` | TEXT | Long text, HTML |
| `decimal` | DECIMAL(12,4) | Prices, weights |
| `timestamp` | TIMESTAMP | Dates |
| `boolean` | TINYINT(1) | Flags |
| `blob` | BLOB | Binary data |

**Generate Whitelist:**

```bash
bin/magento setup:db-declaration:generate-whitelist training_review
```

This creates `etc/db_schema_whitelist.json` — **commit this file**.

**Apply Changes:**

```bash
bin/magento setup:upgrade
bin/magento setup:db:status  # Shows current schema state
```

---

### Topic 2: Data Patches & Schema Patches

**Data Patch — Insert Initial Data:**

```php
<?php
// Setup/Patch/Data/AddSampleReviews.php
namespace Training\Review\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;

class AddSampleReviews implements DataPatchInterface
{
    private $moduleDataSetup;

    public function __construct(ModuleDataSetupInterface $moduleDataSetup)
    {
        $this->moduleDataSetup = $moduleDataSetup;
    }

    public function apply(): void
    {
        $this->moduleDataSetup->getConnection()->insertMultiple('training_review', [
            ['product_id' => 1, 'reviewer_name' => 'Alice', 'rating' => 5, 'review_text' => 'Great product!'],
            ['product_id' => 1, 'reviewer_name' => 'Bob',   'rating' => 4, 'review_text' => 'Good value'],
            ['product_id' => 2, 'reviewer_name' => 'Carol', 'rating' => 3, 'review_text' => 'Average'],
        ]);
    }

    public function getAliases(): array { return []; }
    public static function getDependencies(): array { return []; }
}
```

**Schema Patch — Modify Existing Table:**

```php
<?php
// Setup/Patch/Schema/AddVerifiedColumn.php
namespace Training\Review\Setup\Patch\Schema;

use Magento\Framework\Setup\SchemaPatchInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;

class AddVerifiedColumn implements SchemaPatchInterface
{
    private $moduleDataSetup;

    public function __construct(ModuleDataSetupInterface $moduleDataSetup)
    {
        $this->moduleDataSetup = $moduleDataSetup;
    }

    public function apply(): void
    {
        $this->moduleDataSetup->getConnection()->addColumn(
            'training_review',
            'is_verified',
            ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_SMALLINT,
             'nullable' => false, 'default' => 0,
             'comment' => 'Verified Purchase Flag']
        );
    }

    public function getAliases(): array { return []; }
    public static function getDependencies(): array { return []; }
}
```

**Running Patches:**

```bash
bin/magento setup:upgrade --keep-generated
bin/magento setup:db:status
```

---

### Topic 3: Model, ResourceModel & Collection

**The Triad:**

| Class | Responsibility |
|-------|---------------|
| Model | Entity logic, holds data, knows nothing about DB |
| ResourceModel | Database operations (CRUD for a specific table) |
| Collection | Set of Model instances, supports filtering/sorting/pagination |

**Model:**

```php
<?php
// Model/Review.php
namespace Training\Review\Model;

use Magento\Framework\Model\AbstractModel;

class Review extends AbstractModel
{
    protected function _construct(): void
    {
        $this->_init(\Training\Review\Model\ResourceModel\Review::class);
    }
}
```

**ResourceModel:**

```php
<?php
// Model/ResourceModel/Review.php
namespace Training\Review\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Review extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('training_review', 'review_id');
    }
}
```

**Collection:**

```php
<?php
// Model/ResourceModel/Review/Collection.php
namespace Training\Review\Model\ResourceModel\Review;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(
            \Training\Review\Model\Review::class,
            \Training\Review\Model\ResourceModel\Review::class
        );
    }
}
```

**Using the Collection:**

```php
$collection = $this->reviewCollectionFactory->create();
$collection->addFieldToFilter('product_id', 1);
$collection->setOrder('created_at', 'DESC');
$collection->setPageSize(10)->setCurPage(1);

foreach ($collection as $review) {
    echo $review->getReviewerName();
}
```

---

### Topic 4: Service Contracts (Interface-First Design)

**Why Service Contracts?**

| Without Interfaces | With Interfaces |
|-------------------|-----------------|
| Direct model dependency | Depend on interface |
| Hard to unit test | Easy to mock |
| Internal structure leaked | Internal structure hidden |
| Breaks when you refactor | Refactor safely |

**Data Interface — `Api/Data/ReviewInterface.php`:**

```php
<?php
namespace Training\Review\Api\Data;

interface ReviewInterface
{
    public function getReviewId(): int;
    public function setReviewId(int $id): self;
    public function getProductId(): int;
    public function setProductId(int $id): self;
    public function getReviewerName(): string;
    public function setReviewerName(string $name): self;
    public function getRating(): int;
    public function setRating(int $rating): self;
    public function getReviewText(): string;
    public function setReviewText(string $text): self;
    public function getCreatedAt(): string;
    public function setCreatedAt(string $date): self;
    public function getIsVerified(): bool;
    public function setIsVerified(bool $verified): self;
}
```

**Repository Interface — `Api/ReviewRepositoryInterface.php`:**

```php
<?php
namespace Training\Review\Api;

use Training\Review\Api\Data\ReviewInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;

interface ReviewRepositoryInterface
{
    public function save(ReviewInterface $review): ReviewInterface;
    public function getById(int $reviewId): ReviewInterface;
    public function delete(ReviewInterface $review): bool;
    public function deleteById(int $reviewId): bool;
    public function getList(SearchCriteriaInterface $searchCriteria): SearchResultsInterface;
}
```

**Wiring in `di.xml`:**

```xml
<preference for="Training\Review\Api\ReviewRepositoryInterface"
            type="Training\Review\Model\ReviewRepository"/>
<preference for="Training\Review\Api\Data\ReviewInterface"
            type="Training\Review\Model\Review"/>
```

---

### Topic 5: Repository Implementation & SearchCriteria

**Full Repository with Error Handling:**

```php
<?php
// Model/ReviewRepository.php
namespace Training\Review\Model;

use Training\Review\Api\ReviewRepositoryInterface;
use Training\Review\Api\Data\ReviewInterface;
use Training\Review\Api\Data\ReviewInterfaceFactory;
use Training\Review\Model\ResourceModel\Review as ResourceModel;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Training\Review\Model\ResourceModel\Review\CollectionFactory;

class ReviewRepository implements ReviewRepositoryInterface
{
    protected $resourceModel;
    protected $reviewFactory;
    protected $searchResultsFactory;
    protected $searchCriteriaBuilder;
    protected $reviewCollectionFactory;

    public function __construct(
        ResourceModel $resourceModel,
        ReviewInterfaceFactory $reviewFactory,
        SearchResultsInterfaceFactory $searchResultsFactory,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        CollectionFactory $reviewCollectionFactory
    ) {
        $this->resourceModel = $resourceModel;
        $this->reviewFactory = $reviewFactory;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->reviewCollectionFactory = $reviewCollectionFactory;
    }

    public function save(ReviewInterface $review): ReviewInterface
    {
        try {
            $this->resourceModel->save($review);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__('Cannot save review: %1', $e->getMessage()));
        }
        return $review;
    }

    public function getById(int $reviewId): ReviewInterface
    {
        $review = $this->reviewFactory->create();
        $this->resourceModel->load($review, $reviewId);
        if (!$review->getReviewId()) {
            throw new NoSuchEntityException(__('Review %1 not found', $reviewId));
        }
        return $review;
    }

    public function getList(SearchCriteriaInterface $searchCriteria): \Magento\Framework\Api\SearchResultsInterface
    {
        $collection = $this->reviewCollectionFactory->create();
        $this->applySearchCriteria($collection, $searchCriteria);

        $searchResult = $this->searchResultsFactory->create();
        $searchResult->setItems($collection->getItems());
        $searchResult->setTotalCount($collection->getSize());
        $searchResult->setSearchCriteria($searchCriteria);
        return $searchResult;
    }

    private function applySearchCriteria($collection, $searchCriteria)
    {
        foreach ($searchCriteria->getFilterGroups() as $filterGroup) {
            foreach ($filterGroup->getFilters() as $filter) {
                $collection->addFieldToFilter(
                    $filter->getField(),
                    [$filter->getConditionType() => $filter->getValue()]
                );
            }
        }
    }
}
```

**SearchCriteria Usage:**

```php
$criteria = $this->searchCriteriaBuilder
    ->addFilter('product_id', 1)
    ->addFilter('rating', 3, 'gteq')   // >= 3
    ->addFilter('is_verified', 1)
    ->addSortOrder('created_at', 'DESC')
    ->setPageSize(10)
    ->setCurrentPage(1)
    ->create();

$result = $this->reviewRepository->getList($criteria);
```

**Filter Operators:**

| Operator | Meaning |
|----------|---------|
| `eq` | Equals |
| `neq` | Not equals |
| `like` | SQL LIKE |
| `in` | IN array |
| `gt`, `gteq` | Greater than |
| `lt`, `lteq` | Less than |

---

### Topic 6: EAV Architecture

**Why EAV?**

Flat tables have fixed columns. EAV stores attribute definitions separately so different products/customers can have different attributes without altering the table schema.

**EAV Table Structure:**

```
eav_attribute              ← Attribute definitions (name, type, validation)
catalog_product_entity      ← Core entity rows (product_id, created_at)
catalog_product_entity_varchar   ← String values
catalog_product_entity_int      ← Integer values
catalog_product_entity_decimal  ← Decimal values (prices)
catalog_product_entity_text     ← Text/HTML values
catalog_product_entity_datetime ← Date/time values
```

Each value table: `(entity_id, attribute_id, value)`

**EAV vs Flat Table:**

| Scenario | Use |
|----------|-----|
| Custom entity with fixed columns | Flat table (`db_schema.xml`) — simpler |
| Product, customer, category | EAV — dynamic attributes |
| High-volume data (millions rows) | Flat table — EAV has overhead |
| Admin-managed attributes | EAV — attributes created in admin |

**Adding a Custom Product Attribute:**

```php
<?php
// Setup/Patch/Data/AddReviewRatingAttribute.php
namespace Training\Review\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class AddReviewRatingAttribute implements DataPatchInterface
{
    protected $moduleDataSetup;
    protected $eavSetupFactory;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        EavSetupFactory $eavSetupFactory
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->eavSetupFactory = $eavSetupFactory;
    }

    public function apply(): void
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);
        $eavSetup->addAttribute(Product::ENTITY, 'allow_review', [
            'type' => 'int',
            'label' => 'Allow Customer Reviews',
            'input' => 'select',
            'source' => \Magento\Eav\Model\Entity\Attribute\Source\Boolean::class,
            'global' => \Magento\Catalog\Model\ResourceModel\Eav\Attribute::SCOPE_GLOBAL,
            'visible' => true,
            'required' => false,
            'user_defined' => true,
            'default' => 1,
        ]);
    }

    public function getAliases(): array { return []; }
    public static function getDependencies(): array { return []; }
}
```

**Reading Custom Attributes:**

```php
$product = $this->product->getProduct();
$allowReview = $product->getData('allow_review');
// or
$allowReview = $product->getAllowReview();
```

---


### Topic 7: EAV Beyond Products — Customer & Category Attributes

**Customer Attributes:**

Product EAV was covered in Topic 6. But Magento's EAV system applies equally to customers and categories. Customer attributes are stored across these tables:

| Table | Stores |
|-------|--------|
| `customer_entity` | Core customer data |
| `customer_entity_varchar` | String values (e.g., `nickname`, `twitter`) |
| `customer_entity_int` | Integer values (e.g., `points`, `loyalty_tier`) |
| `customer_entity_decimal` | Decimal values |
| `customer_entity_text` | Text values (e.g., `bio`) |
| `customer_entity_datetime` | Date/time values |

**Adding a Customer Attribute:**

```php
<?php
// Setup/Patch/Data/AddCustomerLoyaltyTier.php
namespace Training\Review\Setup\Patch\Data;

use Magento\Customer\Model\Customer;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class AddCustomerLoyaltyTier implements DataPatchInterface
{
    protected $moduleDataSetup;
    protected $eavSetupFactory;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        EavSetupFactory $eavSetupFactory
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->eavSetupFactory = $eavSetupFactory;
    }

    public function apply(): void
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);
        $eavSetup->addAttribute(
            Customer::ENTITY,
            'loyalty_tier',
            [
                'type' => 'int',
                'label' => 'Loyalty Tier',
                'input' => 'select',
                'source' => \Training\Review\Model\Customer\Source\LoyaltyTier::class,
                'global' => \Magento\Catalog\Model\ResourceModel\Eav\Attribute::SCOPE_WEBSITE,
                'default' => 0,
                'visible' => true,
                'required' => false,
                'user_defined' => true,
            ]
        );
    }

    public function getAliases(): array { return []; }
    public static function getDependencies(): array { return []; }
}
```

**Custom Source Model for Customer Attribute:**

```php
<?php
// Model/Customer/Source/LoyaltyTier.php
namespace Training\Review\Model\Customer\Source;

use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;

class LoyaltyTier extends AbstractSource
{
    public function getAllOptions(): array
    {
        if ($this->_options === null) {
            $this->_options = [
                ['value' => 0, 'label' => __('Standard')],
                ['value' => 1, 'label' => __('Silver')],
                ['value' => 2, 'label' => __('Gold')],
                ['value' => 3, 'label' => __('Platinum')],
            ];
        }
        return $this->_options;
    }
}
```

**Category Attributes:**

```php
<?php
// Setup/Patch/Data/AddCategoryFeaturedAttribute.php
namespace Training\Review\Setup\Patch\Data;

use Magento\Catalog\Model\Category;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class AddCategoryFeaturedAttribute implements DataPatchInterface
{
    protected $moduleDataSetup;
    protected $eavSetupFactory;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        EavSetupFactory $eavSetupFactory
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->eavSetupFactory = $eavSetupFactory;
    }

    public function apply(): void
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);
        $eavSetup->addAttribute(Category::ENTITY, 'is_featured', [
            'type' => 'int',
            'label' => 'Featured Category',
            'input' => 'select',
            'source' => \Magento\Eav\Model\Entity\Attribute\Source\Boolean::class,
            'global' => \Magento\Catalog\Model\ResourceModel\Eav\Attribute::SCOPE_GLOBAL,
            'visible' => true,
            'required' => false,
            'default' => 0,
        ]);
    }

    public function getAliases(): array { return []; }
    public static function getDependencies(): array { return []; }
}
```

**Reading Custom Customer Attributes:**

```php
<?php
$customer = $this->customerSession->getCustomer();
$loyaltyTier = $customer->getData('loyalty_tier');
// or
$loyaltyTier = $customer->getCustomAttribute('loyalty_tier')?->getValue();
```

**Key Difference from Product EAV:**

| Aspect | Product EAV | Customer/Category EAV |
|--------|-------------|----------------------|
| Attribute sets | Yes (grouping) | No |
| Default entity | `Catalog\Product` | `Customer::ENTITY` or `Category::ENTITY` |
| Admin visibility | Product edit page | Customer account page |
| Searchable | Yes (if indexed) | Yes (if indexed) |
| Scope | `SCOPE_GLOBAL` / `SCOPE_WEBSITE` | `SCOPE_WEBSITE` (customer) |

---

### Topic 8: Transaction Patterns & SaveHandler

**The Problem Without Transactions:**

```php
<?php
// BAD — if savePoint() fails, partial data is committed
public function saveReview(ReviewInterface $review, array $additionalData): void
{
    $this->resourceModel->save($review);      // Commits immediately
    $this->saveAdditionalData($review, $additionalData); // If this fails, $review already saved
    $this->sendNotification($review);        // If this fails, first two already committed
}
```

**Using Database Transactions:**

```php
<?php
// GOOD — all or nothing
public function saveReview(ReviewInterface $review, array $additionalData): void
{
    $connection = $this->resourceModel->getConnection();
    $connection->beginTransaction();

    try {
        $this->resourceModel->save($review);
        $this->saveAdditionalData($review, $additionalData);
        $this->sendNotification($review);
        $connection->commit();
    } catch (\Exception $e) {
        $connection->rollBack();
        throw $e;
    }
}
```

**SaveHandler Pattern — Magento's Structured Save:**

For complex entities, implement `SaveHandlerInterface` to encapsulate all save logic including validation and side-effects:

```php
<?php
// Model/ReviewSaveHandler.php
namespace Training\Review\Model;

use Magento\Framework\Model\ResourceModel\Db\SaveHandlerInterface;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Training\Review\Api\Data\ReviewInterface;
use Psr\Log\LoggerInterface;

class ReviewSaveHandler implements SaveHandlerInterface
{
    protected $resourceModel;
    protected $logger;

    public function __construct(
        \Training\Review\Model\ResourceModel\Review $resourceModel,
        LoggerInterface $logger
    ) {
        $this->resourceModel = $resourceModel;
        $this->logger = $logger;
    }

    public function save(\Magento\Framework\Model\AbstractModel $review): \Magento\Framework\Model\AbstractModel
    {
        $connection = $this->resourceModel->getConnection();
        $connection->beginTransaction();

        try {
            // Validate before any write
            $this->validate($review);

            // Trigger business logic before save
            $this->beforeSave($review);

            // Perform the save
            $this->resourceModel->save($review);

            // Trigger business logic after save
            $this->afterSave($review);

            $connection->commit();
        } catch (\Exception $e) {
            $connection->rollBack();
            $this->logger->error('Review save failed', [
                'review_id' => $review->getId(),
                'error' => $e->getMessage()
            ]);
            throw $e;
        }

        return $review;
    }

    protected function validate(ReviewInterface $review): void
    {
        if (strlen($review->getReviewerName()) < 2) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Reviewer name must be at least 2 characters')
            );
        }
        if ($review->getRating() < 1 || $review->getRating() > 5) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Rating must be between 1 and 5')
            );
        }
    }

    protected function beforeSave(ReviewInterface $review): void
    {
        if (!$review->getCreatedAt()) {
            $review->setCreatedAt(date('Y-m-d H:i:s'));
        }
    }

    protected function afterSave(ReviewInterface $review): void
    {
        // Reindex product review summary after save
        $this->indexerRegistry->get('training_review_product_summary')
            ->executeRow($review->getProductId());
    }
}
```

**Wiring SaveHandler in `di.xml`:**

```xml
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:ObjectManager/etc/di.xsd">
    <type name="Training\Review\Model\Review">
        <arguments>
            <argument name="saveHandler" xsi:type="object">Training\Review\Model\ReviewSaveHandler</argument>
        </arguments>
    </type>
</config>
```

**When to Use Transactions:**

| Situation | Use Transaction? |
|-----------|-----------------|
| Single model save | No (Magento handles it) |
| Multiple saves in sequence | Yes |
| Save + event dispatch (observer side-effects) | Yes |
| External API call after save | Yes (with queue for the external call) |
| Complex validation across multiple entities | Yes |

**The Golden Rule:** If you call multiple `$repository->save()` in sequence, wrap them in a transaction. If any fails, rollback all of them.


## Reading List

- [Magento Model Architecture](https://developer.adobe.com/commerce/php/development/components/entities/) — Model/ResourceModel/Collection
- [Declarative Schema](https://developer.adobe.com/commerce/php/development/components/declarative-schema/) — db_schema.xml
- [Service Contracts](https://developer.adobe.com/commerce/php/development/components/service-contracts/) — Interface-first design
- [EAV](https://developer.adobe.com/commerce/php/development/components/attributes/) — Custom attributes

---

## Edge Cases & Troubleshooting

| Issue | Symptom | Solution |
|-------|---------|----------|
| Table not created | Table missing from DB | Check db_schema.xml syntax, run `setup:upgrade` |
| Whitelist missing | Upgrade script fails | `setup:db-declaration:generate-whitelist` |
| Patch not running | Data not inserted | Check class name matches filename exactly |
| Repository class not found | DI compile error | `setup:di:compile` after adding new classes |
| EAV attribute not showing | Attribute missing in admin | Check attribute set assignment |

---

## Common Mistakes to Avoid

1. ❌ Creating table manually in SQL → Use db_schema.xml only
2. ❌ Forgetting whitelist generation → Schema changes won't apply
3. ❌ Repository returns raw model instead of interface → Return type must be interface
4. ❌ SearchCriteria filter on non-indexed column → Slow on large datasets
5. ❌ Forgetting `di.xml` preferences → Interface not wired to implementation

---

*Week 3 of Magento 2 Zero to Hero Training Program*  
*For: Interns*  
*Language: English*
