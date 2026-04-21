<?php
namespace Training\HelloWorld\Controller\Index;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;
use Psr\Log\LoggerInterface;

class Transactions implements HttpGetActionInterface {
    protected $pageFactory;
    protected $logger;

    public function __construct(
        PageFactory $pageFactory,
        LoggerInterface $logger
    ) {
        $this->pageFactory = $pageFactory;
        $this->logger = $logger;
    }

    public function execute() {
        $this->logger->info('Transactions controller accessed', [
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        return $this->pageFactory->create();
    }
}