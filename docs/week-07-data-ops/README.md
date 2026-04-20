# Week 7: Data Operations — Import/Export, Indexing & Scheduled Tasks

**Goal:** Build reliable data operations — bulk imports, exports, custom indexers, and cron jobs for automation.

---

## Topics Covered

- CSV import/export using Magento's import/export framework
- Import validation and error handling
- Custom indexers — triggered and scheduled
- Cron schedule configuration and job execution
- Mass actions in admin grids
- Custom CLI commands — `bin/magento training:review:approve`
- Data cleanup and archiving strategies
- Bulk operations via registry and queue

---

## Reference Exercises

- **Exercise 7.1:** Create a CSV export of reviews via the Export button
- **Exercise 7.2:** Build a custom importer for review data with validation
- **Exercise 7.3:** Create a custom indexer that rebuilds on product save
- **Exercise 7.4:** Set up a cron job to auto-approve reviews older than 30 days
- **Exercise 7.5:** Write a CLI command to approve/delete reviews via `bin/magento`
- **Exercise 7.6:** Add a mass "Mark as Verified" action to the admin grid

---

## By End of Week 7 You Must Prove

- [ ] Export button generates valid CSV with review data
- [ ] Custom import validates SKU, reviewer name, rating; rejects bad rows with clear errors
- [ ] Custom indexer updates when products are saved
- [ ] Cron job runs on configured schedule, logs auto-approval action
- [ ] Mass action processes selected rows correctly
- [ ] Custom CLI command registered, runs with full DI context
- [ ] `bin/magento indexer:reindex` runs without errors on custom indexer
- [ ] DoD assessment passed

---

## Assessment Criteria

| Test | Time | Criteria |
|------|------|----------|
| CSV Export | 20 min | Export generates valid CSV with all review columns |
| CSV Import | 40 min | Import validates data, reports errors, inserts valid rows |
| Custom Indexer | 30 min | Indexer runs, reindex updates table, invalidation triggers on save |
| Cron Job | 20 min | Cron fires on schedule, processes old reviews, logs action |
| Mass Action | 20 min | Select multiple → Mass action executes on each |

---

## Topics

---

### Topic 1: CSV Export

**Magento's Export Framework:**

Magento provides a generic export infrastructure. To enable export for your custom entity, you need an export entity and a controller.

**Export Entity — `Export/Convertor/Review.php`:**

```php
<?php
// Export/Convertor/Review.php
namespace Training\Review\Export\Convertor;

use Magento\ImportExport\Model\Export\AbstractConvertor;

class Review extends AbstractConvertor
{
    public function convert(array $rowData, $additionalData = [])
    {
        return [
            'review_id'    => $rowData['review_id'],
            'product_id'  => $rowData['product_id'],
            'reviewer'    => $rowData['reviewer_name'],
            'rating'      => $rowData['rating'],
            'text'        => $rowData['review_text'],
            'verified'    => $rowData['is_verified'] ? 'Yes' : 'No',
            'created_at'  => $rowData['created_at'],
        ];
    }
}
```

**Export Controller (Proper DI):**

```php
<?php
// Controller/Adminhtml/Export/Reviews.php
namespace Training\Review\Controller\Adminhtml\Export;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Response\Http\FileFactory;
use Training\Review\Model\ResourceModel\Review\CollectionFactory;

class Reviews extends Action
{
    protected $collectionFactory;
    protected $fileFactory;

    public function __construct(
        Context $context,
        CollectionFactory $collectionFactory,
        FileFactory $fileFactory
    ) {
        parent::__construct($context);
        $this->collectionFactory = $collectionFactory;
        $this->fileFactory = $fileFactory;
    }

    public function execute()
    {
        $collection = $this->collectionFactory->create();
        $collection->setOrder('created_at', 'DESC');

        $rows = [];
        $rows[] = ['review_id', 'product_id', 'reviewer_name', 'rating', 'review_text', 'is_verified', 'created_at'];

        foreach ($collection as $review) {
            $rows[] = [
                $review->getReviewId(),
                $review->getProductId(),
                $review->getReviewerName(),
                $review->getRating(),
                $review->getReviewText(),
                $review->getIsVerified() ? 'Yes' : 'No',
                $review->getCreatedAt(),
            ];
        }

        $csvContent = $this->generateCsv($rows);
        return $this->fileFactory->create('reviews_export.csv', $csvContent, 'var/export');
    }

    private function generateCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);
        return $content;
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed('Training_Review::review_export');
    }
}
```

**Simpler Approach — Override Magento's Export Controller:**

```php
<?php
// Controller/Adminhtml/Export/ExportReviews.php
namespace Training\Review\Controller\Adminhtml\Export;

use Magento\Backend\App\Action;
use Magento\Framework\App\Response\Http\FileFactory;
use Training\Review\Model\ResourceModel\Review\CollectionFactory;

class ExportReviews extends Action
{
    protected $collectionFactory;
    protected $fileFactory;

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        CollectionFactory $collectionFactory,
        FileFactory $fileFactory
    ) {
        parent::__construct($context);
        $this->collectionFactory = $collectionFactory;
        $this->fileFactory = $fileFactory;
    }

    public function execute()
    {
        $collection = $this->collectionFactory->create();
        $collection->setOrder('created_at', 'DESC');

        $rows = [];
        $rows[] = ['review_id', 'product_id', 'reviewer_name', 'rating', 'review_text', 'is_verified', 'created_at'];

        foreach ($collection as $review) {
            $rows[] = [
                $review->getReviewId(),
                $review->getProductId(),
                $review->getReviewerName(),
                $review->getRating(),
                $review->getReviewText(),
                $review->getIsVerified() ? 'Yes' : 'No',
                $review->getCreatedAt(),
            ];
        }

        $csvContent = $this->generateCsv($rows);
        return $this->fileFactory->create('reviews_export.csv', $csvContent, 'var/export');
    }

    private function generateCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);
        return $content;
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed('Training_Review::review_export');
    }
}
```

---

### Topic 2: CSV Import with Validation

**Import Model — `Model/Import/Review.php`:**

```php
<?php
// Model/Import/Review.php
namespace Training\Review\Model\Import;

use Magento\ImportExport\Model\Import\EntitiesAbstract;
use Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingError;
use Magento\Framework\App\ObjectManager;

class Review extends EntitiesAbstract
{
    protected $entityTypeCode = 'training_review';

    protected $returnIndexOf = true; // Return index instead of assoc array

    protected function _importData(): bool
    {
        // Custom implementation
    }

    public function validateData(): bool
    {
        $this->errorAggregator = new \Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingErrorAggregator();
        return parent::validateData();
    }

    protected function _saveData(): bool
    {
        $rows = $this->getProcessedRows();
        foreach ($rows as $rowData) {
            try {
                $this->_saveEntity($this->_getEntity($rowData));
            } catch (\Exception $e) {
                $this->addRowError($e->getMessage(), $this->getRowNum());
            }
        }
        return true;
    }
}
```

**Custom Import with Direct Processing:**

```php
<?php
// Model/Import/Review.php
namespace Training\Review\Model\Import;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\ImportExport\Model\Import\AbstractSource;
use Magento\ImportExport\Model\Import\Adapter\Factory as ImportAdapterFactory;

class Review
{
    protected $directoryList;
    protected $reviewFactory;
    protected $reviewRepository;
    protected $errorMessages = [];

    public function __construct(
        \Training\Review\Api\ReviewRepositoryInterface $reviewRepository,
        \Training\Review\Api\Data\ReviewInterfaceFactory $reviewFactory,
        DirectoryList $directoryList
    ) {
        $this->directoryList = $directoryList;
        $this->reviewRepository = $reviewRepository;
        $this->reviewFactory = $reviewFactory;
    }

    public function importFromCsvFile(string $filePath): array
    {
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new \Exception("Cannot open file: $filePath");
        }

        $header = fgetcsv($handle);
        $expectedHeader = ['review_id', 'product_id', 'reviewer_name', 'rating', 'review_text', 'is_verified', 'created_at'];
        if ($header !== $expectedHeader) {
            throw new \Exception('Invalid CSV header');
        }

        $imported = 0;
        $errors = [];
        $rowNum = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            $data = array_combine($header, $row);

            // Validate
            $validationResult = $this->validateRow($data, $rowNum);
            if ($validationResult !== true) {
                $errors[] = $validationResult;
                continue;
            }

            try {
                $review = $this->reviewFactory->create();
                $review->setProductId((int)($data['product_id'] ?? 0));
                $review->setReviewerName($data['reviewer_name']);
                $review->setRating((int)($data['rating'] ?? 5));
                $review->setReviewText($data['review_text'] ?? '');
                $review->setIsVerified($data['is_verified'] === 'Yes');
                $review->setCreatedAt($data['created_at'] ?? date('Y-m-d H:i:s'));

                $this->reviewRepository->save($review);
                $imported++;
            } catch (\Exception $e) {
                $errors[] = "Row $rowNum: " . $e->getMessage();
            }
        }

        fclose($handle);
        return ['imported' => $imported, 'errors' => $errors];
    }

    private function validateRow(array $data, int $rowNum): bool|string
    {
        if (empty($data['reviewer_name']) || strlen(trim($data['reviewer_name'])) < 2) {
            return "Row $rowNum: Reviewer name must be at least 2 characters";
        }

        $rating = (int)($data['rating'] ?? 0);
        if ($rating < 1 || $rating > 5) {
            return "Row $rowNum: Rating must be between 1 and 5";
        }

        if (!is_numeric($data['product_id']) || (int)$data['product_id'] <= 0) {
            return "Row $rowNum: Invalid product_id";
        }

        return true;
    }
}
```

**Import Controller:**

```php
<?php
// Controller/Adminhtml/Import/Reviews.php
namespace Training\Review\Controller\Adminhtml\Import;

use Magento\Backend\App\Action;
use Magento\Framework\App\Request\DataFactory;
use Training\Review\Model\Import\Review as ImportModel;

class Reviews extends Action
{
    protected $importModel;

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        ImportModel $importModel
    ) {
        parent::__construct($context);
        $this->importModel = $importModel;
    }

    public function execute()
    {
        $file = $this->getRequest()->getFile('import_file');
        if (!$file) {
            $this->messageManager->addErrorMessage(__('No file uploaded'));
            return $this->_redirect('*/*/index');
        }

        try {
            $result = $this->importModel->importFromCsvFile($file['tmp_name']);
            $this->messageManager->addSuccessMessage(
                __('Imported %1 records. Errors: %2', $result['imported'], count($result['errors']))
            );
            if (!empty($result['errors'])) {
                foreach (array_slice($result['errors'], 0, 5) as $error) {
                    $this->messageManager->addErrorMessage($error);
                }
            }
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $this->_redirect('*/*/index');
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed('Training_Review::review_import');
    }
}
```

---

### Topic 3: Custom Indexers

**Indexer Modes:**

| Mode | Trigger | Use Case |
|------|---------|----------|
| `onormal` | Manual or cron | Full rebuild on change |
| `realtime` | Immediate on save | Real-time updates |
| `schedule` | Cron schedule | Batched updates |

**Indexer Definition — `etc/indexer.xml`:**

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Indexer/etc/indexer.xsd">
    <indexer id="training_review_product_summary"
            class="Training\Review\Indexer\ReviewSummaryIndexer"
            title="Review Summary by Product"
            description="Rebuilds product review summary (avg rating, count)">
        <fieldset name="review" source="Training\Review\Model\ResourceModel\Review\Collection"
                  provider="Training\Review\Indexer\ReviewSummaryIndexer\Pool">
            <field name="product_id" xsi:type="filterable" dataType="int"/>
            <field name="avg_rating" xsi:type="filterable" dataType="decimal"/>
            <field name="review_count" xsi:type="filterable" dataType="int"/>
            <field name="last_review_at" xsi:type="filterable" dataType="datetime"/>
        </fieldset>
    </indexer>
</config>
```

**Indexer Class — `Indexer/ReviewSummaryIndexer.php`:**

```php
<?php
// Indexer/ReviewSummaryIndexer.php
namespace Training\Review\Indexer;

use Magento\Framework\Indexer\AbstractIndexer;
use Magento\Framework\Indexer\StateInterface;
use Magento\Catalog\Model\Product;

class ReviewSummaryIndexer extends AbstractIndexer
{
    protected $reviewResource;

    public function __construct(
        \Training\Review\Model\ResourceModel\Review $reviewResource
    ) {
        $this->reviewResource = $reviewResource;
    }

    public function execute(): void
    {
        $this->reindexAll();
    }

    public function executeList(array $productIds): void
    {
        foreach ($productIds as $productId) {
            $this->reindexProduct($productId);
        }
    }

    public function executeRow(int $productId): void
    {
        $this->reindexProduct($productId);
    }

    private function reindexProduct(int $productId): void
    {
        $connection = $this->reviewResource->getConnection();

        $select = $connection->select()
            ->from($this->reviewResource->getMainTable(), [
                'product_id' => 'product_id',
                'avg_rating' => new \Zend_Db_Expr('AVG(rating)'),
                'review_count' => new \Zend_Db_Expr('COUNT(*)'),
                'last_review_at' => new \Zend_Db_Expr('MAX(created_at)'),
            ])
            ->where('product_id = ?', $productId)
            ->group('product_id');

        $data = $connection->fetchRow($select);
        if ($data) {
            // Update summary table or cache
            $this->updateSummaryTable($data);
        }
    }

    private function reindexAll(): void
    {
        // Full rebuild — process all products
        $connection = $this->reviewResource->getConnection();
        $table = $this->getSummaryTable();

        $select = $connection->select()
            ->from($this->reviewResource->getMainTable(), [
                'product_id' => 'product_id',
                'avg_rating' => new \Zend_Db_Expr('AVG(rating)'),
                'review_count' => new \Zend_Db_Expr('COUNT(*)'),
                'last_review_at' => new \Zend_Db_Expr('MAX(created_at)'),
            ])
            ->group('product_id');

        $connection->query("TRUNCATE TABLE {$table}");
        $connection->query($select);
    }

    protected function updateSummaryTable(array $data): void
    {
        // Write to summary table
    }

    protected function getSummaryTable(): string
    {
        return 'training_review_product_summary';
    }

    public function isAvailable(): bool
    {
        return true;
    }
}
```

**Triggering Indexer on Product Save — `etc/events.xml`:**

```xml
<event name="catalog_product_save_commit_after">
    <observer name="training_review_product_saved"
              instance="Training\Review\Observer\ReindexProductReviews"
              sortOrder="10"/>
</event>
```

**Observer:**

```php
<?php
// Observer/ReindexProductReviews.php
namespace Training\Review\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Indexer\IndexerRegistry;

class ReindexProductReviews implements ObserverInterface
{
    protected $indexerRegistry;

    public function __construct(IndexerRegistry $indexerRegistry)
    {
        $this->indexerRegistry = $indexerRegistry;
    }

    public function execute(Observer $observer): void
    {
        $product = $observer->getData('product');
        $productId = $product->getId();

        $indexer = $this->indexerRegistry->get('training_review_product_summary');
        if ($indexer->isAvailable()) {
            $indexer->executeRow($productId);
        }
    }
}
```

**Indexer Commands:**

```bash
bin/magento indexer:reindex training_review_product_summary
bin/magento indexer:status           # Show all indexers
bin/magento indexer:set-mode realtime training_review_product_summary
bin/magento indexer:set-mode schedule training_review_product_summary
```

---

### Topic 4: Cron Jobs

**Cron Configuration — `crontab.xml`:**

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:etc/crontab.xsd">
    <group id="default">
        <job name="training_review_auto_approve"
             instance="Training\Review\Cron\AutoApprove"
             method="execute">
            <schedule>0 2 * * *</schedule>  <!-- 2:00 AM daily -->
        </job>

        <job name="training_review_cleanup"
             instance="Training\Review\Cron\CleanupOldReviews"
             method="execute">
            <schedule>0 3 * * 0</schedule>  <!-- 3:00 AM every Sunday -->
        </job>
    </group>
</config>
```

**Cron Job Class — `Cron/AutoApprove.php`:**

```php
<?php
// Cron/AutoApprove.php
namespace Training\Review\Cron;

use Psr\Log\LoggerInterface;
use Training\Review\Api\ReviewRepositoryInterface;
use Training\Review\Api\Data\ReviewInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\FilterBuilder;

class AutoApprove
{
    protected $logger;
    protected $reviewRepository;
    protected $searchCriteriaBuilder;
    protected $filterBuilder;

    public function __construct(
        LoggerInterface $logger,
        ReviewRepositoryInterface $reviewRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        FilterBuilder $filterBuilder
    ) {
        $this->logger = $logger;
        $this->reviewRepository = $reviewRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->filterBuilder = $filterBuilder;
    }

    public function execute(): void
    {
        $this->logger->info('Running auto-approve cron');

        $thirtyDaysAgo = date('Y-m-d H:i:s', strtotime('-30 days'));

        $this->searchCriteriaBuilder
            ->addFilter('is_verified', 0)
            ->addFilter('created_at', $thirtyDaysAgo, 'lteq');

        $criteria = $this->searchCriteriaBuilder->create();
        $reviews = $this->reviewRepository->getList($criteria);

        $approved = 0;
        foreach ($reviews->getItems() as $review) {
            try {
                $review->setIsVerified(true);
                $this->reviewRepository->save($review);
                $approved++;
            } catch (\Exception $e) {
                $this->logger->error('Failed to auto-approve review ' . $review->getReviewId(), [
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->logger->info("Auto-approve complete: {$approved} reviews approved");
    }
}
```

**Checking Cron Status:**

```bash
# View cron schedule
bin/magento cron:show

# Run all cron jobs manually
bin/magento cron:run --group=default

# Run specific job
bin/magento cron:run --job-name=training_review_auto_approve
```

**Cron Schedule Format:**

```
 ┌───────────── minute (0-59)
 │ ┌───────────── hour (0-23)
 │ │ ┌───────────── day of month (1-31)
 │ │ │ ┌───────────── month (1-12)
 │ │ │ │ ┌───────────── day of week (0-7, 0 and 7 = Sunday)
 │ │ │ │ │
 * * * * *
```

Examples:
- `0 2 * * *` — Every day at 2:00 AM
- `*/15 * * * *` — Every 15 minutes
- `0 3 * * 0` — Every Sunday at 3:00 AM
- `30 8 1 * *` — First day of every month at 8:30 AM

---

### Topic 5: Mass Actions in Admin Grids

**Adding a Mass Action to a UI Component Grid:**

```xml
<!-- In the listing XML, add to listingToolbar -->
<listingToolbar name="listing_top">
    ...
    <massaction name="listing_massaction" target="-1">
        <action name="verify_selected" label="Mark as Verified">
            <settings>
                <url path="review/index/massVerify"/>
                <type>selected</type>
                <confirm>
                    <title>Verify Reviews</title>
                    <message>Mark selected reviews as verified?</message>
                </confirm>
            </settings>
        </action>
        <action name="delete_selected" label="Delete Selected">
            <settings>
                <url path="review/index/massDelete"/>
                <type>selected</type>
                <confirm>
                    <title>Delete Reviews</title>
                    <message>Delete selected reviews?</message>
                </confirm>
            </settings>
        </action>
    </massaction>
</listingToolbar>
```

**Mass Action Controller — `Controller/Adminhtml/Index/MassVerify.php`:**

```php
<?php
// Controller/Adminhtml/Index/MassVerify.php
namespace Training\Review\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Ui\Component\MassAction\Filter;
use Training\Review\Model\ResourceModel\Review\CollectionFactory;

class MassVerify extends Action
{
    protected $filter;
    protected $collectionFactory;
    protected $reviewRepository;

    public function __construct(
        Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory,
        \Training\Review\Api\ReviewRepositoryInterface $reviewRepository
    ) {
        parent::__construct($context);
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
        $this->reviewRepository = $reviewRepository;
    }

    public function execute()
    {
        $collection = $this->filter->getCollection(
            $this->collectionFactory->create()
        );

        $verified = 0;
        foreach ($collection as $review) {
            try {
                $review->setIsVerified(true);
                $this->reviewRepository->save($review);
                $verified++;
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage(
                    "Failed to verify review {$review->getReviewId()}: " . $e->getMessage()
                );
            }
        }

        if ($verified > 0) {
            $this->messageManager->addSuccessMessage(
                __("$verified review(s) marked as verified")
            );
        }

        return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)
            ->setPath('*/*/index');
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed('Training_Review::review_edit');
    }
}
```

---


### Topic 6: Custom CLI Commands — bin/magento

**Why Custom CLI Commands?**

Custom commands let you write one-off scripts that run in Magento's full context — with dependency injection, service classes, and the database available. Common uses:
- Data cleanup / fixes
- Maintenance mode toggles
- Bulk data import without the UI
- Generating sample data for testing
- Running background jobs not triggered by cron

**Command Structure:**

```
bin/magento training:review:approve <review_id> [--force]
bin/magento training:review:reindex
bin/magento training:review:stats
```

**Directory Pattern:**

```
app/code/[Vendor]/[Module]/Console/Command/[CommandName].php
```

**Basic Command:**

```php
<?php
// Console/Command/ApproveReview.php
namespace Training\Review\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Training\Review\Api\ReviewRepositoryInterface;
use Magento\Framework\Console\Cli;

class ApproveReview extends Command
{
    protected $reviewRepository;
    protected $logger;

    public function __construct(
        ReviewRepositoryInterface $reviewRepository,
        \Psr\Log\LoggerInterface $logger
    ) {
        parent::__construct();
        $this->reviewRepository = $reviewRepository;
        $this->logger = $logger;
    }

    protected function configure(): void
    {
        $this->setName('training:review:approve')
            ->setDescription('Mark a review as verified/approved')
            ->addArgument('review_id', InputOption::VALUE_REQUIRED, 'Review ID')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip confirmation');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $reviewId = (int) $input->getArgument('review_id');
        if (!$reviewId) {
            $output->writeln('<error>Review ID required</error>');
            return Cli::RETURN_FAILURE;
        }

        try {
            $review = $this->reviewRepository->getById($reviewId);
            $review->setIsVerified(true);
            $this->reviewRepository->save($review);

            $output->writeln("<info>Review #{$reviewId} approved</info>");
            $this->logger->info("Review {$reviewId} approved via CLI");
            return Cli::RETURN_SUCCESS;
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            $output->writeln("<error>Review #{$reviewId} not found</error>");
            return Cli::RETURN_FAILURE;
        }
    }
}
```

**Register Command in `di.xml`:**

```xml
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:ObjectManager/etc/di.xsd">
    <type name="Magento\Framework\Console\CommandPool">
        <arguments>
            <argument name="commands" xsi:type="array">
                <item name="Training_Review" xsi:type="object">Training\Review\Console\Command\ApproveReview</item>
            </argument>
        </arguments>
    </type>
</config>
```

**Interactive Confirmation:**

```php
protected function execute(InputInterface $input, OutputInterface $output): int
{
    $reviewId = (int) $input->getArgument('review_id');

    if (!$input->getOption('force')) {
        $output->writeln("About to approve review #{$reviewId}. Use --force to skip.");
        return Cli::RETURN_FAILURE;
    }

    // ...
}
```

**Command with Table Output:**

```php
<?php
// Console/Command/ListReviews.php
namespace Training\Review\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\Console\Cli;
use Training\Review\Api\ReviewRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;

class ListReviews extends Command
{
    protected $reviewRepository;
    protected $searchCriteriaBuilder;

    public function __construct(
        ReviewRepositoryInterface $reviewRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        parent::__construct();
        $this->reviewRepository = $reviewRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    protected function configure(): void
    {
        $this->setName('training:review:list')
            ->setDescription('List reviews with optional product filter')
            ->addOption('product', 'p', InputOption::VALUE_OPTIONAL, 'Filter by product ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $criteria = $this->searchCriteriaBuilder->create();
        if ($productId = $input->getOption('product')) {
            $this->searchCriteriaBuilder->addFilter('product_id', $productId);
        }

        $reviews = $this->reviewRepository->getList($criteria);

        $table = new Table($output);
        $table->setHeaders(['ID', 'Product', 'Reviewer', 'Rating', 'Verified', 'Created']);

        foreach ($reviews->getItems() as $review) {
            $table->addRow([
                $review->getReviewId(),
                $review->getProductId(),
                $review->getReviewerName(),
                $review->getRating(),
                $review->getIsVerified() ? 'Yes' : 'No',
                $review->getCreatedAt(),
            ]);
        }

        $table->render();
        return Cli::RETURN_SUCCESS;
    }
}
```

**Key Notes:**

- Return `Cli::RETURN_SUCCESS` (0) on success, `Cli::RETURN_FAILURE` (1) on error
- Inject services via constructor — full Magento DI is available
- Use `$output->writeln()` for user-facing messages
- Log to `logger` for audit trail, not `$output` — output is for CLI, logs are for debugging
- Always validate and throw meaningful errors — CLI errors don't have a UI to show them

**Testing CLI Commands:**

```php
<?php
public function testApproveReviewCommand()
{
    $this->assertEquals(
        Cli::RETURN_SUCCESS,
        $this->application->run(
            new ArrayInput(['command' => 'training:review:approve', 'review_id' => '1']),
            new NullOutput()
        )
    );
}
```

---

## Reading List
## Reading List

- [Import/Export Framework](https://developer.adobe.com/commerce/php/development/components/import-export/) — Magento's import infrastructure
- [Indexers](https://developer.adobe.com/commerce/php/development/components/indexing/) — Custom indexers
- [Configure Cron Jobs](https://experienceleague.adobe.com/docs/commerce-operations/configuration-guide/crons/custom-cron.html) — Cron configuration

---

## Edge Cases & Troubleshooting

| Issue | Symptom | Solution |
|-------|---------|----------|
| CSV export empty | No data in file | Collection returns data — check repository |
| Import fails silently | No rows inserted | Check file encoding (UTF-8), field count matches header |
| Indexer stuck | `reindex` hangs | Check for circular observer → indexer loop |
| Cron not firing | Scheduled job doesn't run | Check system cron is running: `crontab -l` |
| Mass action 404 | Controller path wrong | `url path` should be `review/index/massVerify` (no leading `/`) |
| Import validation too strict | Valid rows rejected | Check data type — rating `"5"` string vs integer |

---

## Common Mistakes to Avoid

1. ❌ Import without validation → Bad data enters the system
2. ❌ Large import in one transaction → Memory exhausted; batch it
3. ❌ Indexer without `executeRow()` → Real-time mode fails
4. ❌ Cron schedule too frequent → Server overload
5. ❌ Forgetting cron permissions → Cron blocked by security module

---

*Week 7 of Magento 2 Zero to Hero Training Program*  
*For: Interns*  
*Language: English*
