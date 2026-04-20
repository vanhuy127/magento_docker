<?php
namespace Training\HelloWorld\Controller\Index;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Psr\Log\LoggerInterface;

class Index implements HttpGetActionInterface
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
            'route' => 'helloworld/index/index'
        ]);

        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);

        // 2. Tương tác với cấu hình trang (Page Config) để đặt tiêu đề thẻ <title>
        $resultPage->getConfig()->getTitle()->set('page index 1');

        // 3. Trả về kết quả để Magento render ra trình duyệt
        return $resultPage;
    }
}
