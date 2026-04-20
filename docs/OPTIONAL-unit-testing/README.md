# Unit Testing in Magento 2

**Duration:** 2-3 Days (Optional but Recommended)  
**Philosophy:** Code Quality Assurance — Verify your code works correctly

---

## Overview

Unit testing is crucial for professional Magento development. This guide covers how to write and run tests for your custom modules.

---

## Prerequisites

- [ ] Module with working repository
- [ ] Understanding of PHPUnit
- [ ] Test environment configured

---

## Learning Objectives

By end of this section, you will be able to:

- [ ] Understand unit testing fundamentals
- [ ] Create unit tests for models
- [ ] Create integration tests for repositories
- [ ] Run tests and interpret results

---

## Day 1: Unit Testing Fundamentals

### Content

#### 1.1 Why Test?

| Benefit | Description |
|---------|-------------|
| **Confidence** | Changes won't break existing features |
| **Documentation** | Tests describe expected behavior |
| **Refactoring** | Safe to improve code |
| **Bug Detection** | Catch issues early |

#### 1.2 Test Structure

```php
<?php
namespace Training\HelloWorld\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use Training\HelloWorld\Model\HelloWorld as Model;

class HelloWorldTest extends TestCase
{
    public function testGetMessageReturnsString()
    {
        $model = new Model();
        $result = $model->getMessage();
        
        $this->assertIsString($result);
    }
    
    public function testSetMessageStoresValue()
    {
        $model = new Model();
        $testMessage = 'Test Message';
        
        $model->setMessage($testMessage);
        
        $this->assertEquals($testMessage, $model->getMessage());
    }
}
```

#### 1.3 Basic Test Types

| Type | What It Tests | Time to Run |
|------|---------------|-------------|
| **Unit** | Single method/function | Fast (<1s) |
| **Integration** | Multiple components | Medium |
| **Functional** | Full user flows | Slow |

### Exercise

- [ ] Create basic unit test for your model

---

## Day 2: Testing Models & Helpers

### Content

#### 2.1 Testing Models

```php
<?php
namespace Training\Review\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use Training\Review\Model\Review;
use Magento\Framework\DataObject;

class ReviewTest extends TestCase
{
    public function testGetReviewIdReturnsInteger()
    {
        $review = new Review();
        $review->setReviewId(123);
        
        $this->assertEquals(123, $review->getReviewId());
        $this->assertIsInt($review->getReviewId());
    }
    
    public function testGetRatingReturnsInteger()
    {
        $review = new Review();
        $review->setRating(5);
        
        $this->assertEquals(5, $review->getRating());
    }
    
    public function testRatingMustBeBetween1And5()
    {
        $this->expectException(\Exception::class);
        
        $review = new Review();
        $review->setRating(10); // Invalid
    }
}
```

#### 2.2 Testing Helpers

```php
<?php
namespace Training\HelloWorld\Test\Unit\Helper;

use PHPUnit\Framework\TestCase;
use Training\HelloWorld\Helper\Data as Helper;

class DataTest extends TestCase
{
    public function testGetGreetingReturnsCorrectFormat()
    {
        $helper = $this->createMock(Helper::class);
        
        // Using reflection to test private method
        $reflection = new \ReflectionClass($helper);
        $method = $reflection->getMethod('getGreeting');
        $method->setAccessible(true);
        
        $result = $method->invoke($helper, 'John');
        
        $this->assertStringContainsString('John', $result);
    }
}
```

#### 2.3 Mock Objects

```php
<?php
public function testRepositorySave()
{
    $review = $this->createMock(\Training\Review\Model\Review::class);
    $resourceModel = $this->createMock(\Training\Review\Model\ResourceModel\Review::class);
    
    $resourceModel->expects($this->once())
        ->method('save')
        ->with($review);
    
    $repository = new \Training\Review\Model\ReviewRepository(
        $resourceModel,
        $this->createMock(\Training\Review\Model\ReviewFactory::class),
        $this->createMock(\Magento\Framework\Api\SearchResultsInterfaceFactory::class)
    );
    
    $repository->save($review);
}
```

### Exercise

- [ ] Write tests for your custom model's methods

---

## Day 3: Integration Testing

### Content

#### 3.1 Integration Test Setup

```php
<?php
namespace Training\Review\Test\Integration;

use Magento\Test\Framework\IntegrationTestCase;
use Magento\TestFramework\ObjectManager;

class ReviewRepositoryTest extends IntegrationTestCase
{
    public function testGetByIdReturnsReview()
    {
        $objectManager = ObjectManager::getInstance();
        
        // Create review
        $review = $objectManager->create(\Training\Review\Model\Review::class);
        $review->setProductId(1);
        $review->setReviewerName('Test User');
        $review->setRating(5);
        $review->setReviewText('Great product!');
        $review->save();
        
        // Test repository
        $repository = $objectManager->create(\Training\Review\Api\ReviewRepositoryInterface::class);
        $loaded = $repository->getById($review->getReviewId());
        
        $this->assertEquals(1, $loaded->getProductId());
        $this->assertEquals('Test User', $loaded->getReviewerName());
        
        // Cleanup
        $repository->delete($loaded);
    }
}
```

#### 3.2 Fixture Files

```xml
<!-- Test/Integration/_files/review.yml -->
- reviewer_name: Test User
  product_id: 1
  rating: 5
  review_text: Great product!
```

#### 3.3 Running Tests

```bash
# Run unit tests
vendor/bin/phpunit app/code/Training/HelloWorld/Test/Unit/

# Run integration tests
vendor/bin/phpunit app/code/Training/HelloWorld/Test/Integration/

# Run specific test file
vendor/bin/phpunit app/code/Training/HelloWorld/Test/Unit/Model/HelloWorldTest.php

# Run with coverage
vendor/bin/phpunit --coverage-html coverage app/code/Training/HelloWorld/Test/Unit/
```

### Must-Know Commands

```bash
# Install PHPUnit
composer require --dev phpunit/phpunit:^9.0

# Run all tests
vendor/bin/phpunit app/code/Training/

# Run specific group
vendor/bin/phpunit --group integration app/code/Training/

# Generate coverage report
vendor/bin/phpunit --coverage-html coverage/
```

### Best Practices

| Practice | Why |
|----------|-----|
| Test one thing per method | Clear test intent |
| Use descriptive names | `testSaveReviewWithValidData` |
| Mock external dependencies | Isolated tests |
| Test edge cases | Empty, null, max values |
| Keep tests fast | Quick feedback loop |

### Exercise

- [ ] Write integration test for repository CRUD

---

## Testing Summary

### What You've Learned

- Unit testing fundamentals
- Testing models and helpers
- Mock objects
- Integration testing
- Running tests

### Integration with Course

| Week | Test Type | Integration |
|------|-----------|-------------|
| Week 2 | Unit | Test Block/Template logic |
| Week 3 | Unit | Test Model data handling |
| Week 4 | Integration | Test Repository CRUD |
| Week 5 | Unit | Test Plugin behavior |
| Any | All | After implementing any feature |

---

## Quick Reference Card

### Test Directory Structure

```
Training/HelloWorld/
└── Test/
    ├── Unit/
    │   ├── UnitTestCase.php
    │   └── Model/
    │       └── HelloWorldTest.php
    └── Integration/
        ├── IntegrationTestCase.php
        ├── _files/
        │   └── review.yml
        └── Model/
            └── ReviewRepositoryTest.php
```

### PHPUnit Assertions

```php
// Common assertions
$this->assertEquals($expected, $actual);
$this->assertTrue($condition);
$this->assertFalse($condition);
$this->assertNull($value);
$this->assertNotNull($value);
$this->assertIsArray($value);
$this->assertStringContainsString($needle, $haystack);
$this->expectException(\Exception::class);
```

### Common Fixtures

```xml
<!-- review.yml -->
- reviewer_name: John Doe
  product_id: 1
  rating: 5
  review_text: Excellent!
```

---

*Unit Testing Guide for Magento 2 Zero to Hero Training Program*  
*For: Interns*  
*Language: English*