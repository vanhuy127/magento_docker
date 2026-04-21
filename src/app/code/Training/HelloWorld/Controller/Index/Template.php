<?php
namespace Training\HelloWorld\Controller\Index;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Psr\Log\LoggerInterface;

class Template implements HttpGetActionInterface
{
    protected $resultFactory;
    protected $logger;

    public function __construct(
        ResultFactory $resultFactory,
        LoggerInterface $logger
    ) {
        $this->resultFactory = $resultFactory;
        $this->logger = $logger;
    }

    public function execute()
    {
        // Ghi custom log cho Exercise 1.4
        $this->logger->info('Custom log entry for Exercise 1.4: Hello World module accessed', [
            'timestamp' => date('Y-m-d H:i:s'),
            'route' => 'helloworld/index/testblock'
        ]);

        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);

        return $resultPage;
    }
}
