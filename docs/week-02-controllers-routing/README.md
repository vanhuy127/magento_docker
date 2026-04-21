# Week 2: Controllers, Blocks, Templates & Layout XML

**Goal:** Build a complete page in Magento — from URL routing to rendered HTML — using controllers, blocks, templates, and layout XML.

---

## Topics Covered

- How Magento routes a URL to a controller action
- Controller implementation patterns (HttpGetActionInterface, HttpPostActionInterface)
- Block classes — PHP logic attached to templates
- PHTML templates — PHP-driven HTML rendering
- Layout XML — Magento's declarative layout system (handles, containers, references)
- PHPCS code quality — PSR-12 Magento coding standard
- Admin routes (preparation for Week 5)

---

## Reference Exercises

- **Exercise 2.1:** Create a controller that returns a custom message
- **Exercise 2.2:** Build a block that passes dynamic data to a template
- **Exercise 2.3:** Use layout XML to position elements on a page
- **Exercise 2.4:** Run PHPCS and fix any reported errors

---

## By End of Week 2 You Must Prove

- [ ] Working route with custom controller returning content
- [ ] Custom block passes data to a PHTML template
- [ ] Layout XML positions elements without modifying core
- [ ] PHPCS reports zero errors on your module code
- [ ] DoD assessment passed

---

## Assessment Criteria

| Test | Time | Criteria |
|------|------|----------|
| Routing | 20 min | Create route + controller that renders content |
| Block/Template | 20 min | Block passes data, template renders it |
| Layout XML | 20 min | Layout XML positions elements correctly |
| PHPCS | 15 min | Zero errors reported |

---

## Topics

---

### Topic 1: Request Flow

**How Magento Routes a URL:**

```
http://example.com/helloworld/index/index
              │         │      │      │
              │         │      │      └─── Action (Index)
              │         │      └─────────── Controller (Index)
              │         └─────────────────── Route ID (helloworld)
              └───────────────────────────── Front name (from routes.xml)
```

**The flow:**

1. URL hits `index.php` entry point
2. `Router` matches URL to a route ID and frontName
3. `Controller` is matched (controller path + action)
4. Controller's `execute()` runs
5. Controller returns a `Result` (page, JSON, redirect)
6. Result renders

**Router Matching in `routes.xml`:**

```xml
<!-- etc/frontend/routes.xml -->
<router id="standard">
    <route id="helloworld" frontName="helloworld">
        <module name="Training_HelloWorld"/>
    </route>
</router>
```

The `frontName` becomes the first URL segment. `route id` is the internal identifier.

---

### Topic 2: Controllers

**Controller Location Pattern:**

```
Controller/[Area]/[ControllerPath]/[Action].php
```

For `http://localhost/helloworld/index/index`:
- Area: empty (frontend) → `Controller/`
- ControllerPath: `Index` → `Index/`
- Action: `Index` → `Index.php`

```php
<?php
// Controller/Index/Index.php
namespace Training\HelloWorld\Controller\Index;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;

class Index implements HttpGetActionInterface
{
    protected $resultFactory;

    public function __construct(ResultFactory $resultFactory)
    {
        $this->resultFactory = $resultFactory;
    }

    public function execute()
    {
        /** @var \Magento\Framework\View\Result\Page $resultPage */
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->getConfig()->getTitle()->set('Hello World');
        return $resultPage;
    }
}
```

**For POST requests**, use `HttpPostActionInterface` instead.

**Layout Handle Convention:**

| URL | Layout Handle |
|-----|--------------|
| `helloworld/index/index` | `helloworld_index_index.xml` |
| `helloworld/index/edit` | `helloworld_index_edit.xml` |
| `helloworld/review/save` | `helloworld_review_save.xml` |

---

### Topic 3: Blocks

**Block Role:** Block classes hold PHP logic that prepares data for templates. They are instantiated by the layout system and available in templates via `$block`.

**Minimal Block:**

```php
<?php
// Block/Message.php
namespace Training\HelloWorld\Block;

use Magento\Framework\View\Element\Template;

class Message extends Template
{
    protected $message;

    public function __construct(Template\Context $context, array $data = [])
    {
        parent::__construct($context, $data);
        $this->message = 'Hello from the block!';
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getUppercaseMessage(): string
    {
        return strtoupper($this->message);
    }
}
```

**Key rule:** Blocks don't output HTML. Templates do. Blocks prepare data.

**Available in templates via `$block`:**

```php
<?php /** @var \Training\HelloWorld\Block\Message $block */ ?>
<h1><?= $block->escapeHtml($block->getMessage()) ?></h1>
<p><?= $block->escapeHtml($block->getUppercaseMessage()) ?></p>
```

**Always escape output** with `$block->escapeHtml()` to prevent XSS.

---

### Topic 4: Templates (PHTML)

**Template Location:**

```
view/[area]/templates/[Vendor]/[Module]/[file].phtml
```

For frontend templates: `view/frontend/templates/training/helloworld/message.phtml`

**Connecting Block to Template in Layout XML:**

```xml
<referenceContainer name="content">
    <block class="Training\HelloWorld\Block\Message"
           name="training.message"
           template="Training_HelloWorld::message.phtml"/>
</referenceContainer>
```

**Common Template Patterns:**

```php
<?php
/** @var \Training\HelloWorld\Block\Message $block */
/** @var \Magento\Framework\Escaper $escaper */
?>

<!-- Output escaping -->
<h1><?= $escaper->escapeHtml($block->getMessage()) ?></h1>

<!-- Conditional -->
<?php if ($block->getMessage()): ?>
    <p>Message: <?= $escaper->escapeHtml($block->getMessage()) ?></p>
<?php endif; ?>

<!-- Loop -->
<?php foreach ($block->getItems() as $item): ?>
    <li><?= $escaper->escapeHtml($item) ?></li>
<?php endforeach; ?>
```

**Template hint for debugging:**

```bash
bin/magento dev:template-hints:enable
bin/magento cache:flush
```

Then hover over elements in the browser to see which block/template renders each piece.

---

### Topic 5: Layout XML

**Layout XML Role:** Layout XML declares which blocks belong on a page, their order, and their configuration. It does not write HTML — it orchestrates blocks.

**Anatomy of a Layout Handle:**

```
helloworld_index_index.xml
└── helloworld        ← frontName (from routes.xml)
    └── index        ← controller path
        └── index   ← action name
```

**Layout File — `view/frontend/layout/helloworld_index_index.xml`:**

```xml
<?xml version="1.0"?>
<page xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/layout.xsd">
    <body>
        <referenceContainer name="page.wrapper">
            <container name="custom.container" htmlTag="div" htmlClass="custom-wrapper">
                <block class="Training\HelloWorld\Block\Message"
                       name="training.message"
                       template="Training_HelloWorld::message.phtml"/>
            </container>
        </referenceContainer>
    </body>
</page>
```

**Key Elements:**

| Element | Purpose |
|---------|---------|
| `<page>` | Root element, defines page type |
| `<body>` | All visible content lives here |
| `<referenceContainer>` | Reference existing containers (page.wrapper, content, etc.) |
| `<container>` | Group blocks with optional wrapper tag |
| `<block>` | Define a block and its template |
| `<move>` | Move a block to a different container |
| `<remove>` | Remove a block |

**Common Containers:**

| Container | Location |
|----------|---------|
| `page.wrapper` | Outer wrapper |
| `header` | Top header |
| `main.content` | Main content area |
| `content` | Content area (inside main.content) |
| `footer` | Bottom footer |

**Arguments (passing data to blocks):**

```xml
<block class="Training\HelloWorld\Block\Message"
       name="training.message"
       template="Training_HelloWorld::message.phtml">
    <arguments>
        <argument name="title" xsi:type="string">Hello!</argument>
    </arguments>
</block>
```

Access in block: `$this->getTitle()` or via layout: `$block->getLayout()->getBlock('training.message')->getTitle()`.

---

### Topic 6: PHPCS — Code Quality

**PHPCS (PHP CodeSniffer)** enforces coding standards. Magento uses PSR-12 + Magento-specific rules.

**Install PHPCS:**

```bash
composer require --dev magento/magento-coding-standard
vendor/bin/phpcs --version
```

**Run PHPCS:**

```bash
vendor/bin/phpcs --standard=Magento2 app/code/Training/HelloWorld
```

**Fixing Errors:**

```bash
# Auto-fix what PHPCS can fix
vendor/bin/phpcbf --standard=Magento2 app/code/Training/HelloWorld
```

**Common Issues to Fix:**

| Issue | PHPCS Code | Fix |
|-------|------------|-----|
| Missing namespace | `Magento2.PHP.Namespace` | Add `namespace Vendor\Module;` |
| Incorrect brace placement | `Generic.ControlStructures.ControlSignature` | Put `{` on same line |
| Missing docblock | `Magento2.CodeAnalysis.Markdown` | Add docblock above class |
| Line too long | `Generic.Files.LineLength` | Break into multiple lines |
| Missing use statement | `Magento2.PHP.Literal` | Add `use` for class references |

**Pre-commit Hook (optional but recommended):**

Add to `composer.json` scripts:

```json
"scripts": {
    "pre-commit": "phpcs --standard=Magento2 app/code/Training/"
}
```

Or configure in `.git/hooks/pre-commit`.

---

### Topic 7: Git Workflow for Team Development

**Why Git?** Real Magento projects involve multiple developers. Git enables collaboration, code review, and safe deployments.

**Basic Git Commands:**

```bash
git status                    # Check changes
git add .                     # Stage all changes
git commit -m "Add review module"  # Commit with message

# Create feature branch
git checkout -b feature/add-review-grid

# Switch branches
git checkout main
git checkout feature/add-review-grid
```

**Git Flow (Production Standard):**

```
main (production-ready)
  ↑
develop (integration branch)
  ↑
feature/add-review-grid (your work)
```

**Creating a Pull Request (PR):**

1. Push your branch: `git push -u origin feature/add-review-grid`
2. Go to GitHub/GitLab → Create Pull Request
3. Description: What you changed, why, testing done
4. Request code review from teammate
5. Address feedback, get approval, merge

**Commit Message Best Practices:**

```bash
# Good
git commit -m "feat(review): add admin grid with listing UI component"

# Bad
git commit -m "updates"  # Too vague!
git commit -m "fixed stuff"  # No context!
```

**Rule:** Write commit messages that explain "why", not just "what".

---

## Reading List

- [Magento 2 Request Flow](https://developer.adobe.com/commerce/php/development/build/request-flow/) — How routing works
- [Layouts Overview](https://developer.adobe.com/commerce/frontend-core/guide/layouts/) — Layout XML structure
- [PSR-12 Coding Standard](https://www.php-fig.org/psr/psr-12/) — Code style rules

---

## Edge Cases & Troubleshooting

| Issue | Symptom | Solution |
|-------|---------|----------|
| Route 404 | Controller not found | Check `routes.xml` frontName matches; run `setup:upgrade` |
| Template not found | Blank area on page | Check template path matches layout XML reference |
| Block method undefined | Fatal error in template | Check method exists in Block class |
| Layout changes not showing | Old layout persists | `bin/magento cache:flush` |
| PHPCS errors on generated code | Vendor files flagged | Run PHPCS only on `app/code/` |

---

## Common Mistakes to Avoid

1. ❌ Writing HTML directly in a controller → Use block + template pattern
2. ❌ Not escaping `$block->escapeHtml()` in templates → XSS vulnerability
3. ❌ Putting templates in wrong area folder → `frontend/` vs `adminhtml/`
4. ❌ Hardcoding URLs → Use `$block->getUrl()` for URLs
5. ❌ Editing layout XML in vendor folders → All custom layout goes in `app/code/`

---

*Week 2 of Magento 2 Zero to Hero Training Program*  
*For: Interns*  
*Language: English*
