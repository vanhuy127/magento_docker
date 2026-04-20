<?php
namespace Training\HelloWorld\Block;

use Magento\Framework\View\Element\Template;

class Message extends Template
{
    /**
     * @var string
     */
    protected $message;

    /**
     * @param Template\Context $context
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->message = 'Hello from the block!';
    }

    /**
     * Lấy thông điệp gốc
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Lấy thông điệp viết hoa
     * @return string
     */
    public function getUppercaseMessage(): string
    {
        return strtoupper($this->message);
    }
}
