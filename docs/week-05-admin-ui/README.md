# Week 5: Admin UI — Routes, Menus, Configuration, ACL & Grids

**Goal:** Build a complete admin interface for your module — navigation entry, configuration page, permission system, and data grid with edit form.

---

## Topics Covered

- Admin routes (`etc/adminhtml/routes.xml`) — backend URL routing
- Admin menu (`etc/adminhtml/menu.xml`) — sidebar navigation
- System configuration pages (`etc/adminhtml/system.xml`) — settings in Stores → Configuration
- ACL permissions (`etc/acl.xml`) — role-based access control
- UI Component listing grids (`view/adminhtml/ui_component/*.xml`) — data tables in admin
- UI Component form (`view/adminhtml/ui_component/*.xml`) — edit/create records
- Data providers for grids and forms
- File upload in admin — FileUploader, validation, storage
- Edit and Save controllers for admin data management

---

## Reference Exercises

- **Exercise 5.1:** Create an admin route and controller with `_isAllowed()` ACL check
- **Exercise 5.2:** Build admin menu with hierarchy and parent references
- **Exercise 5.3:** Create a system configuration section with 3+ fields
- **Exercise 5.4:** Implement ACL with different roles and test access restriction
- **Exercise 5.5 (optional):** Build an admin grid with DataProvider and action columns
- **Exercise 5.6 (optional):** Build an admin edit form with save functionality
- **Exercise 5.7:** Add a file upload controller, store relative path in database

---

## By End of Week 5 You Must Prove

- [ ] Admin route accessible at `/admin/review/index/index`
- [ ] Menu item visible in admin sidebar (parent: `Magento_Backend::content`)
- [ ] Menu item hidden when user lacks ACL permission
- [ ] Configuration page at Stores → Configuration with ≥3 fields
- [ ] Admin grid renders with data from repository (not hardcoded)
- [ ] Edit and Delete action buttons work from grid
- [ ] Admin edit form loads with data, Save persists via repository
- [ ] All controllers protected with `_isAllowed()` ACL checks
- [ ] File upload handled via FileUploader (extension/size validated)
- [ ] File path stored as relative path in database
- [ ] DoD assessment passed

---

## Assessment Criteria

| Test | Time | Criteria |
|------|------|----------|
| Admin Route + Menu | 20 min | Route works + menu item visible |
| System Config | 20 min | Stores → Config renders with 3 fields |
| ACL | 20 min | Restricted user denied; permitted user allowed |
| Admin Grid | 45 min | Grid renders from DB, edit/delete work |
| Admin Form (optional) | 45 min | Form loads data, Save persists correctly |

---

## Topics

---

### Topic 1: Admin Routes

**Admin URLs look different from frontend URLs:**

```
http://localhost/admin/review/index/index/key/xxx
         └─ frontName ┌─ controllerPath └─ action
```

**`etc/adminhtml/routes.xml`:**

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:App/etc/routes.xsd">
    <router id="admin">
        <route id="review" frontName="review">
            <module name="Training_Review" before="Magento_Backend"/>
        </route>
    </router>
</config>
```

Key differences from frontend routes:
- `router id="admin"` (not `standard`)
- `before="Magento_Backend"` — ensures your route takes priority in admin

**Admin Controller:**

```php
<?php
// Controller/Adminhtml/Index/Index.php
namespace Training\Review\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    protected $resultPageFactory;

    public function __construct(Context $context, PageFactory $resultPageFactory)
    {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Training_Review::review');
        $resultPage->getConfig()->getTitle()->prepend(__('Reviews'));
        return $resultPage;
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed('Training_Review::review');
    }
}
```

**Key rule:** Every admin controller must implement `_isAllowed()` returning `true` only if the current user has the required permission.

---

### Topic 2: Admin Menu

**`etc/adminhtml/menu.xml`:**

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Backend/etc/menu.xsd">
    <menu>
        <add id="Training_Review::reviews"
             title="Reviews"
             module="Training_Review"
             sortOrder="30"
             parent="Magento_Backend::content"
             action="review/index/index"
             resource="Training_Review::review"/>
    </menu>
</config>
```

| Attribute | Purpose |
|-----------|---------|
| `id` | Unique identifier (`Vendor_Module::resource_id`) |
| `title` | Display text in sidebar |
| `parent` | Parent menu item — use `Magento_Backend::content` for top-level |
| `action` | Controller path (`review/index/index`) |
| `resource` | ACL resource — user must have this permission to see the item |

**Submenu Item:**

```xml
<add id="Training_Review::pending"
     title="Pending Reviews"
     module="Training_Review"
     sortOrder="10"
     parent="Training_Review::reviews"
     action="review/index/pending"
     resource="Training_Review::review_pending"/>
```

**Rebuilding the Admin Menu Cache:**

```bash
bin/magento admin:menu:rebuild
```

---

### Topic 3: System Configuration

**Configuration hierarchy:**

```
Stores → Configuration → [Section] → [Group] → [Field]
```

**`etc/adminhtml/system.xml`:**

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Config:etc/system_file.xsd">
    <section id="review" translate="label" type="text" sortOrder="100"
             showInDefault="1" showInWebsite="1" showInStore="1">
        <label>Reviews Settings</label>
        <tab>catalog</tab>
        <resource>Training_Review::config</resource>

        <group id="general" translate="label" type="text" sortOrder="10"
               showInDefault="1" showInWebsite="1" showInStore="1">
            <label>General Settings</label>

            <field id="enabled" translate="label" type="select" sortOrder="10"
                   showInDefault="1" showInWebsite="1" showInStore="1">
                <label>Enable Reviews</label>
                <source_model>Magento\Config\Model\Config\Source\Yesno</source_model>
            </field>

            <field id="allow_guest" translate="label" type="select" sortOrder="20"
                   showInDefault="1" showInWebsite="1" showInStore="1">
                <label>Allow Guest Reviews</label>
                <source_model>Magento\Config\Model\Config\Source\Yesno</source_model>
            </field>

            <field id="api_key" translate="label" type="text" sortOrder="30"
                   showInDefault="1" showInWebsite="1" showInStore="1">
                <label>API Key</label>
                <comment>Used for external integration</comment>
            </field>
        </group>
    </section>
</config>
```

**Reading Config Values:**

```php
<?php
public function isEnabled(): bool
{
    return (bool) $this->scopeConfig->getValue(
        'review/general/enabled',
        \Magento\Store\Model\ScopeInterface::SCOPE_STORE
    );
}
```

**Available source models:**
- `Magento\Config\Model\Config\Source\Yesno` — Yes/No select
- `Magento\Config\Model\Config\Source\Enabledisable` — Enabled/Disabled
- `Magento\Config\Model\Config\Source\Locale\Currency` — Currency list
- Custom source model: implement `\Magento\Framework\Data\OptionSourceInterface`

---

### Topic 4: ACL — Roles & Permissions

**ACL Hierarchy in `etc/acl.xml`:**

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Acl/etc/acl.xsd">
    <acl>
        <resources>
            <resource id="Magento_Backend::admin">
                <resource id="Magento_Backend::content">
                    <resource id="Training_Review::review" title="Reviews" sortOrder="10">
                        <resource id="Training_Review::review_view"  title="View Reviews" sortOrder="10"/>
                        <resource id="Training_Review::review_edit"  title="Edit Reviews" sortOrder="20"/>
                        <resource id="Training_Review::review_delete" title="Delete Reviews" sortOrder="30"/>
                    </resource>
                </resource>
                <resource id="Magento_Backend::system">
                    <resource id="Training_Review::config" title="Reviews Configuration" sortOrder="40"/>
                </resource>
            </resource>
        </resources>
    </acl>
</config>
```

**Menu → ACL Connection:**

The `resource` attribute in `menu.xml` links the menu item to the ACL resource:

```xml
<add id="Training_Review::reviews"
     ...
     resource="Training_Review::review"/>
```

If user lacks `Training_Review::review`, the menu item is hidden.

**Checking ACL in Controllers:**

```php
protected function _isAllowed(): bool
{
    return $this->_authorization->isAllowed('Training_Review::review_edit');
}
```

**Assigning Roles:**

Admin → System → Permissions → User Roles → Create role → Assign resources.

---

### Topic 5: Admin UI Component Grids

**Grid Architecture:**

```
UI Component XML (listing) → DataProvider → Repository → Database
view/adminhtml/ui_component/training_review_review_listing.xml
```

**Listing XML:**

```xml
<?xml version="1.0"?>
<listing xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Ui:etc/ui_configuration.xsd">
    <argument name="data" xsi:type="array">
        <item name="js_config" xsi:type="array">
            <item name="provider" xsi:type="string">
                training_review_review_listing.training_review_review_listing_data_source
            </item>
        </item>
    </argument>

    <settings>
        <spinner>training_review_columns</spinner>
        <deps>
            <dep>training_review_review_listing.training_review_review_listing_data_source</dep>
        </deps>
    </settings>

    <dataSource name="training_review_review_listing_data_source"
                component="Magento_Ui/js/grid/provider">
        <settings>
            <updateUrl path="mui/index/render"/>
        </settings>
        <aclResource>Training_Review::review_view</aclResource>
        <dataProvider class="Training\Review\Ui\DataProvider\ReviewDataProvider"
                      name="training_review_review_listing_data_source">
            <settings>
                <requestFieldName>id</requestFieldName>
                <primaryFieldName>review_id</primaryFieldName>
            </settings>
        </dataProvider>
    </dataSource>

    <listingToolbar name="listing_top">
        <settings><sticky>true</sticky></settings>
        <bookmark name="bookmarks"/>
        <columnsControls name="columns_controls"/>
        <filterSearch name="fulltext"/>
        <filters name="listing_filters"/>
        <paging name="listing_paging"/>
        <exportButton name="export_button"/>
    </listingToolbar>

    <columns name="training_review_columns">
        <column name="review_id">
            <settings>
                <filter>textRange</filter>
                <sorting>asc</sorting>
                <label translate="true">ID</label>
            </settings>
        </column>
        <column name="reviewer_name">
            <settings>
                <filter>text</filter>
                <label translate="true">Reviewer</label>
            </settings>
        </column>
        <column name="rating">
            <settings>
                <filter>text</filter>
                <label translate="true">Rating</label>
            </settings>
        </column>
        <column name="created_at" class="Magento\Ui\Component\Listing\Columns\Date">
            <settings>
                <filter>dateRange</filter>
                <label translate="true">Created</label>
            </settings>
        </column>
        <actionsColumn name="actions"
                       class="Training\Review\Ui\Component\Listing\Column\ReviewActions">
            <settings>
                <indexField>review_id</indexField>
                <label translate="true">Actions</label>
            </settings>
        </actionsColumn>
    </columns>
</listing>
```

**Data Provider:**

```php
<?php
// Ui/DataProvider/ReviewDataProvider.php
namespace Training\Review\Ui\DataProvider;

use Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider;
use Training\Review\Model\ResourceModel\Review\CollectionFactory;

class ReviewDataProvider extends DataProvider
{
    private $collectionFactory;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collectionFactory = $collectionFactory;
    }

    public function getData(): array
    {
        $collection = $this->collectionFactory->create();
        return [
            'items' => $collection->getItems(),
            'totalRecords' => $collection->getSize()
        ];
    }
}
```

**Action Column (Edit/Delete):**

```php
<?php
// Ui/Component/Listing/Column/ReviewActions.php
namespace Training\Review\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class ReviewActions extends Column
{
    protected $urlBuilder;

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
        $this->urlBuilder = $urlBuilder;
    }

    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                $item[$this->getData('name')] = [
                    'edit' => [
                        'href' => $this->urlBuilder->getUrl(
                            'review/index/edit',
                            ['review_id' => $item['review_id']]
                        ),
                        'label' => __('Edit')
                    ],
                    'delete' => [
                        'href' => $this->urlBuilder->getUrl(
                            'review/index/delete',
                            ['review_id' => $item['review_id']]
                        ),
                        'label' => __('Delete'),
                        'confirm' => [
                            'title' => __('Delete Review'),
                            'message' => __('Delete this review?')
                        ]
                    ]
                ];
            }
        }
        return $dataSource;
    }
}
```

**Layout File — `view/adminhtml/layout/review_index_index.xml`:**

```xml
<?xml version="1.0"?>
<page xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Backend/etc/pages.xsd">
    <update handle="styles"/>
    <body>
        <referenceContainer name="content">
            <uiComponent name="training_review_review_listing"/>
        </referenceContainer>
    </body>
</page>
```

---

### Topic 6: Admin Form UI Component

**Form Architecture:**

```
UI Component Form XML → DataProvider → Repository → Database
view/adminhtml/ui_component/training_review_review_form.xml
```

**Form XML:**

```xml
<?xml version="1.0"?>
<form xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Ui:etc/ui_configuration.xsd">
    <argument name="data" xsi:type="array">
        <item name="js_config" xsi:type="array">
            <item name="provider" xsi:type="string">
                training_review_review_form.training_review_review_form_data_source
            </item>
        </item>
    </argument>

    <settings>
        <spinner>training_review_form_fields</spinner>
        <dataScope>data</dataScope>
        <namespace>training_review_review_form</namespace>
    </settings>

    <dataSource name="training_review_review_form_data_source">
        <settings>
            <submitUrl path="review/index/save"/>
        </settings>
        <aclResource>Training_Review::review_edit</aclResource>
        <dataProvider class="Training\Review\Ui\DataProvider\ReviewFormDataProvider"
                      name="training_review_review_form_data_source">
            <settings>
                <requestFieldName>review_id</requestFieldName>
                <primaryFieldName>review_id</primaryFieldName>
            </settings>
        </dataProvider>
    </dataSource>

    <fieldset name="review_details">
        <settings>
            <label translate="true">Review Information</label>
        </settings>

        <field name="review_id" formElement="input">
            <settings><disabled>true</disabled><label translate="true">ID</label></settings>
        </field>

        <field name="product_id" formElement="input">
            <settings>
                <label translate="true">Product ID</label>
                <validation><rule name="required" xsi:type="boolean">true</rule></validation>
            </settings>
        </field>

        <field name="reviewer_name" formElement="input">
            <settings>
                <label translate="true">Reviewer Name</label>
                <validation><rule name="required" xsi:type="boolean">true</rule></validation>
            </settings>
        </field>

        <field name="rating" formElement="select">
            <settings>
                <label translate="true">Rating</label>
                <options>
                    <option name="1" label="1 Star" value="1"/>
                    <option name="2" label="2 Stars" value="2"/>
                    <option name="3" label="3 Stars" value="3"/>
                    <option name="4" label="4 Stars" value="4"/>
                    <option name="5" label="5 Stars" value="5"/>
                </options>
            </settings>
        </field>

        <field name="review_text" formElement="textarea">
            <settings><label translate="true">Review Text</label><rows>4</rows></settings>
        </field>
    </fieldset>
</form>
```

**Form Data Provider:**

```php
<?php
// Ui/DataProvider/ReviewFormDataProvider.php
namespace Training\Review\Ui\DataProvider;

use Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider;
use Training\Review\Api\ReviewRepositoryInterface;

class ReviewFormDataProvider extends DataProvider
{
    protected $reviewRepository;

    public function __construct(
        $name, $primaryFieldName, $requestFieldName,
        ReviewRepositoryInterface $reviewRepository,
        array $meta = [], array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->reviewRepository = $reviewRepository;
    }

    public function getData(): array
    {
        $reviewId = (int) $this->getRequest()->getParam($this->getRequestFieldName());
        if (!$reviewId) return [];

        try {
            $review = $this->reviewRepository->getById($reviewId);
            return ['training_review_review_form' => ['data' => $review->getData()]];
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return [];
        }
    }
}
```

**Edit Controller:**

```php
<?php
// Controller/Adminhtml/Index/Edit.php
namespace Training\Review\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Training\Review\Api\ReviewRepositoryInterface;

class Edit extends Action
{
    protected $resultPageFactory;
    protected $reviewRepository;

    public function __construct(Context $context, PageFactory $resultPageFactory, ReviewRepositoryInterface $reviewRepository)
    {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->reviewRepository = $reviewRepository;
    }

    public function execute()
    {
        $reviewId = $this->getRequest()->getParam('review_id');
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Training_Review::review');
        $resultPage->getConfig()->getTitle()->prepend(
            $reviewId ? __('Edit Review #%1', $reviewId) : __('New Review')
        );
        return $resultPage;
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed('Training_Review::review_edit');
    }
}
```

**Save Controller:**

```php
<?php
// Controller/Adminhtml/Index/Save.php
namespace Training\Review\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Training\Review\Api\Data\ReviewInterfaceFactory;
use Training\Review\Api\ReviewRepositoryInterface;

class Save extends Action
{
    protected $reviewRepository;
    protected $reviewFactory;

    public function __construct(
        Context $context,
        ReviewRepositoryInterface $reviewRepository,
        ReviewInterfaceFactory $reviewFactory
    ) {
        parent::__construct($context);
        $this->reviewRepository = $reviewRepository;
        $this->reviewFactory = $reviewFactory;
    }

    public function execute()
    {
        $data = $this->getRequest()->getPostValue();
        if (!$data) return $this->_redirect('review/index/index');

        try {
            $reviewId = isset($data['review_id']) ? (int)$data['review_id'] : null;
            $review = $reviewId
                ? $this->reviewRepository->getById($reviewId)
                : $this->reviewFactory->create();

            $review->setProductId((int)$data['product_id']);
            $review->setReviewerName($data['reviewer_name']);
            $review->setRating((int)$data['rating']);
            $review->setReviewText($data['review_text'] ?? '');

            $this->reviewRepository->save($review);
            $this->messageManager->addSuccessMessage(__('Review saved'));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $this->_redirect('review/index/index');
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed('Training_Review::review_edit');
    }
}
```

---


---

### Topic 7: File & Image Upload in Admin Forms

**Overview:** Admin modules frequently need to accept file uploads — CSV import files, PDF attachments, images. Magento provides a structured `FileUploader` system that integrates with its media storage and handles validation, renaming, and security.

**The `FileUploaderFactory` Pattern:**

Unlike `move_uploaded_file()`, Magento's `FileUploader` handles:
- File validation (type, size, extensions)
- Moving to media storage with unique filenames
- Generating collision-free names
- Returning the relative path for database storage

**Controller — Handling File Upload:**

```php
<?php
// Controller/Adminhtml/Import/Upload.php
namespace Training\Review\Controller\Adminhtml\Import;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Message\ManagerInterface;
use Magento\MediaStorage\Model\File\UploaderFactory;

class Upload extends Action
{
    protected $uploaderFactory;
    protected $filesystem;
    protected $messageManager;

    public function __construct(
        Context $context,
        UploaderFactory $uploaderFactory,
        Filesystem $filesystem,
        ManagerInterface $messageManager
    ) {
        parent::__construct($context);
        $this->uploaderFactory = $uploaderFactory;
        $this->filesystem = $filesystem;
        $this->messageManager = $messageManager;
    }

    public function execute()
    {
        $fileData = $this->getRequest()->getFiles('import_file');
        if (!$fileData || !$fileData['tmp_name']) {
            $this->messageManager->addErrorMessage(__('No file uploaded'));
            return $this->_redirect('*/*/index');
        }

        try {
            $uploader = $this->uploaderFactory->create(['fileId' => 'import_file']);
            $uploader->setAllowedExtensions(['csv', 'xml']);
            $uploader->setAllowRenameFiles(true);
            $uploader->setFilesDispersion(false);
            $uploader->setAllowCreateFolders(true);

            $mediaDir = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
            $targetPath = $mediaDir->getAbsolutePath('import/training_review');

            $result = $uploader->save($targetPath);
            $fileName = $result['file'];

            $this->messageManager->addSuccessMessage(__('File uploaded: %1', $fileName));
            $this->_forward('import', null, null, ['file' => $fileName]);
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return $this->_redirect('*/*/index');
        }
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed('Training_Review::review_import');
    }
}
```

**Restrictive Uploader — Size and Extension Validation:**

```php
<?php
$uploader = $this->uploaderFactory->create(['fileId' => 'attachment']);
$uploader->setAllowedExtensions(['jpg', 'jpeg', 'png', 'gif', 'pdf']);
$uploader->setMaxSize('file', 5242880); // 5MB max
$uploader->setAllowCreateFolders(true);
```

**Storing File Path in Database:**

Always store the **relative path** — never the absolute path:

```php
<?php
// GOOD — portable across environments
$review->setAttachmentFile('import/training_review/' . $fileName);

// BAD — breaks when domain or mount point changes
$review->setAttachmentFile('/var/www/html/pub/media/import/training_review/' . $fileName);
```

**Retrieving File URL in Admin:**

```php
<?php
$filePath = $review->getAttachmentFile();
$mediaUrl = $this->storeManager->getStore()
    ->getBaseUrl(\Magento\Framework\Url::URL_TYPE_MEDIA);
$fileUrl = $mediaUrl . $filePath;
```

**Security Checklist:**

| Risk | Mitigation |
|------|------------|
| PHP shell upload | Block `php`, `phtml`, `exe`, `sh` extensions |
| Large file DoS | `setMaxSize()` — 5MB max is reasonable |
| Path traversal | Magento's `FileUploader` sanitizes paths |
| Overwriting files | `setAllowRenameFiles(true)` generates unique names |

---

## Reading List
## Reading List

- [Admin Routing](https://developer.adobe.com/commerce/php/development/components/routing/#admin-routes) — Admin route structure
- [UI Components](https://developer.adobe.com/commerce/frontend-core/ui-components/) — Listing and form components
- [System Configuration](https://developer.adobe.com/commerce/php/development/configuration/) — system.xml structure

---

## Edge Cases & Troubleshooting

| Issue | Symptom | Solution |
|-------|---------|----------|
| Route 404 | `/admin/review/...` returns 404 | Check `routes.xml` area=`admin`, `before="Magento_Backend"` |
| Menu not showing | Menu item absent from sidebar | ACL permission denied — verify `resource` in menu.xml |
| Config page blank | Stores → Config shows empty | Missing `system.xml` or wrong `showInDefault` flags |
| Grid empty | UI Component loads but no data | DataProvider not returning items in correct format |
| Grid loading forever | Spinner keeps spinning | Wrong `requestFieldName` in dataProvider |
| ACL blocking access | 403 on all actions | `_isAllowed()` returning false — verify ACL resource |

---

## Common Mistakes to Avoid

1. ❌ Forgetting `_isAllowed()` in admin controllers → Security hole
2. ❌ Menu `resource` not matching `acl.xml` → Menu visible to everyone or no one
3. ❌ Wrong `requestFieldName` in UI Component → Grid doesn't load
4. ❌ Forgetting `bin/magento c:f` after ACL changes → Stale cache
5. ❌ `routes.xml` using `frontend` area for admin routes → Route never matches

---

*Week 5 of Magento 2 Zero to Hero Training Program*  
*For: Interns*  
*Language: English*
